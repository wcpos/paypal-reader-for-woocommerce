#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
COMPOSE=(docker compose -f "$SCRIPT_DIR/docker-compose.yml")
BASE_URL="http://localhost:8091"

cleanup() {
  local code=$?
  if [ $code -ne 0 ]; then
    printf '\nSmoke test failed. Recent docker logs:\n' >&2
    "${COMPOSE[@]}" logs --tail=120 >&2 || true
  fi
  return $code
}
trap cleanup EXIT

echo "Starting Docker environment..."
"${COMPOSE[@]}" up -d db wordpress

echo "Running WordPress setup..."
"${COMPOSE[@]}" run --rm wp-cli >/tmp/paypal-reader-e2e-setup.log
cat /tmp/paypal-reader-e2e-setup.log

echo "Creating a fresh pending WooCommerce order..."
ORDER_JSON=$("${COMPOSE[@]}" run --rm --entrypoint wp wp-cli eval '
$product_ids = get_posts(array("post_type" => "product", "post_status" => "publish", "numberposts" => 1, "fields" => "ids"));
if (empty($product_ids)) {
    fwrite(STDERR, "No published product found.\n");
    exit(1);
}
$product = wc_get_product($product_ids[0]);
$order = wc_create_order(array("status" => "pending"));
$order->add_product($product, 1);
$order->set_address(array(
    "first_name" => "Mock",
    "last_name" => "Customer",
    "email" => "mock@example.com",
    "address_1" => "123 Test St",
    "city" => "San Francisco",
    "postcode" => "94105",
    "country" => "US",
), "billing");
$order->calculate_totals();
echo wp_json_encode(array(
    "order_id" => $order->get_id(),
    "order_key" => $order->get_order_key(),
    "total_minor" => (int) round(((float) $order->get_total()) * 100),
));
' --path=/var/www/html)

echo "$ORDER_JSON"
ORDER_ID=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys, json; print(json.load(sys.stdin)["order_id"])')
ORDER_KEY=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys, json; print(json.load(sys.stdin)["order_key"])')
ORDER_TOTAL_MINOR=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys, json; print(json.load(sys.stdin)["total_minor"])')
ORDER_PAY_URL="$BASE_URL/checkout/order-pay/$ORDER_ID/?pay_for_order=true&key=$ORDER_KEY"

echo "Fetching order-pay page: $ORDER_PAY_URL"
PAGE_HTML=$(curl -fsSL "$ORDER_PAY_URL")
[[ "$PAGE_HTML" == *paypal-reader-terminal* ]]
[[ "$PAGE_HTML" == *paypalReaderData* ]]

PAYPAL_READER_JSON=$(printf '%s' "$PAGE_HTML" | python3 -c 'import re, sys; html=sys.stdin.read(); m=re.search(r"var paypalReaderData = (\{.*?\});", html, re.S); print(m.group(1) if m else "")')
if [ -z "$PAYPAL_READER_JSON" ]; then
  echo "Failed to extract paypalReaderData from order-pay page" >&2
  exit 1
fi

NONCE=$(printf '%s' "$PAYPAL_READER_JSON" | python3 -c 'import sys, json; print(json.load(sys.stdin)["nonce"])')
AJAX_URL=$(printf '%s' "$PAYPAL_READER_JSON" | python3 -c 'import sys, json; print(json.load(sys.stdin)["ajaxUrl"])')

echo "Verifying mock reader list..."
READERS_JSON=$(curl -fsSL -X POST "$AJAX_URL" \
  --data-urlencode "action=paypal_reader_get_readers" \
  --data-urlencode "nonce=$NONCE" \
  --data-urlencode "order_id=$ORDER_ID" \
  --data-urlencode "order_key=$ORDER_KEY")
printf '%s' "$READERS_JSON" | python3 -c 'import sys, json; data=json.load(sys.stdin); assert data["success"] is True; assert data["data"]["mode"] == "mock"; assert len(data["data"]["readers"]) == 1; print(data["data"]["readers"][0]["id"])' >/tmp/paypal-reader-id.txt
READER_ID=$(cat /tmp/paypal-reader-id.txt)

echo "Starting mock payment on reader $READER_ID..."
START_JSON=$(curl -fsSL -X POST "$AJAX_URL" \
  --data-urlencode "action=paypal_reader_start_payment" \
  --data-urlencode "nonce=$NONCE" \
  --data-urlencode "order_id=$ORDER_ID" \
  --data-urlencode "order_key=$ORDER_KEY" \
  --data-urlencode "reader_id=$READER_ID" \
  --data-urlencode "amount=$ORDER_TOTAL_MINOR")
printf '%s' "$START_JSON" | python3 -c 'import sys, json; data=json.load(sys.stdin); assert data["success"] is True; assert data["data"]["state"] == "pending"; print(data["data"]["attempt_id"])' >/tmp/paypal-reader-attempt.txt
ATTEMPT_ID=$(cat /tmp/paypal-reader-attempt.txt)
echo "Started attempt: $ATTEMPT_ID"

FINAL_STATE="pending"
for _ in 1 2 3 4 5 6 7 8 9 10; do
  STATUS_JSON=$(curl -fsSL -X POST "$AJAX_URL" \
    --data-urlencode "action=paypal_reader_check_payment_status" \
    --data-urlencode "nonce=$NONCE" \
    --data-urlencode "order_id=$ORDER_ID" \
    --data-urlencode "order_key=$ORDER_KEY")
  FINAL_STATE=$(printf '%s' "$STATUS_JSON" | python3 -c 'import sys, json; data=json.load(sys.stdin); assert data["success"] is True; print(data["data"]["state"])')
  echo "Payment state: $FINAL_STATE"
  if [ "$FINAL_STATE" = "completed" ]; then
    break
  fi
  sleep 1
done

if [ "$FINAL_STATE" != "completed" ]; then
  echo "Payment never reached completed state" >&2
  exit 1
fi

echo "Running gateway process_payment inside WordPress..."
PROCESS_JSON=$("${COMPOSE[@]}" run --rm -e PRWC_ORDER_ID="$ORDER_ID" --entrypoint wp wp-cli eval '
$order_id = (int) getenv("PRWC_ORDER_ID");
$gateway = new WCPOS\WooCommercePOS\PayPalReader\Gateway();
$result = $gateway->process_payment($order_id);
$order = wc_get_order($order_id);
echo wp_json_encode(array(
    "result" => $result,
    "order_status" => $order ? $order->get_status() : null,
    "payment_state" => $order ? $order->get_meta("_prwc_payment_state") : null,
    "transaction_id" => $order ? $order->get_transaction_id() : null,
));
' --path=/var/www/html)

echo "$PROCESS_JSON"
printf '%s' "$PROCESS_JSON" | python3 -c 'import sys, json; data=json.load(sys.stdin); assert data["result"]["result"] == "success"; assert data["payment_state"] == "completed"; assert data["transaction_id"] == "mock-card-payment-uuid"; assert data["order_status"] in ("processing", "completed"); print("Gateway processing verified")'

echo "Smoke test completed successfully."
