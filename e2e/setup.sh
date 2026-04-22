#!/bin/sh
set -eu

cd /var/www/html

MOCK_CANCEL_BEHAVIOR=${PRWC_MOCK_CANCEL_BEHAVIOR:-canceled}

echo "Waiting for WordPress files to be ready..."
until wp core version --path=/var/www/html >/dev/null 2>&1; do
  sleep 2
done

if ! wp core is-installed --path=/var/www/html >/dev/null 2>&1; then
  echo "Installing WordPress..."
  wp core install \
    --path=/var/www/html \
    --url="http://localhost:8091" \
    --title="PayPal Reader Test Site" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
else
  echo "WordPress already installed."
fi

if ! wp plugin is-installed woocommerce --path=/var/www/html >/dev/null 2>&1; then
  echo "Installing WooCommerce..."
  wp plugin install woocommerce --activate --path=/var/www/html
else
  wp plugin activate woocommerce --path=/var/www/html >/dev/null 2>&1 || true
fi

echo "Activating PayPal Reader plugin..."
wp plugin activate paypal-reader-for-woocommerce --path=/var/www/html >/dev/null 2>&1 || true

echo "Configuring WooCommerce..."
wp option update woocommerce_store_address "123 Test St" --path=/var/www/html
wp option update woocommerce_store_city "San Francisco" --path=/var/www/html
wp option update woocommerce_default_country "US:CA" --path=/var/www/html
wp option update woocommerce_store_postcode "94105" --path=/var/www/html
wp option update woocommerce_currency "USD" --path=/var/www/html
wp rewrite structure '/%postname%/' --hard --path=/var/www/html
wp rewrite flush --hard --path=/var/www/html
wp option update woocommerce_paypal_reader_for_woocommerce_settings \
  '{"enabled":"yes","title":"PayPal Reader","description":"Pay in person using PayPal Reader.","mode":"mock","mock_reader_name":"WCPOS Mock Reader","mock_cancel_behavior":"'"${MOCK_CANCEL_BEHAVIOR}"'"}' \
  --format=json --path=/var/www/html

PRODUCT_ID=$(wp post list --post_type=product --post_status=publish --format=ids --path=/var/www/html | awk '{print $1}')
if [ -z "$PRODUCT_ID" ]; then
  echo "Creating test product..."
  wp wc product create \
    --name="Test Product" \
    --type=simple \
    --regular_price=10.00 \
    --path=/var/www/html \
    --user=admin >/tmp/product.json
  cat /tmp/product.json
else
  echo "Test product already exists: $PRODUCT_ID"
fi

echo "Setup complete!"
