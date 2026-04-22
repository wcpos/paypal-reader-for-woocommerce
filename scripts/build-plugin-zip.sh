#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
PLUGIN_SLUG="paypal-reader-for-woocommerce"
PLUGIN_FILE="$ROOT_DIR/${PLUGIN_SLUG}.php"
DIST_DIR="$ROOT_DIR/dist"
TMP_DIR=$(mktemp -d)
STAGE_DIR="$TMP_DIR/$PLUGIN_SLUG"
VERSION=$(grep -oE 'Version:[[:space:]]*[0-9]+(\.[0-9]+)*' "$PLUGIN_FILE" | head -1 | grep -oE '[0-9]+(\.[0-9]+)*')

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

mkdir -p "$STAGE_DIR" "$DIST_DIR"

for entry in assets includes "$PLUGIN_SLUG.php" README.md; do
  if [ -e "$ROOT_DIR/$entry" ]; then
    cp -R "$ROOT_DIR/$entry" "$STAGE_DIR/"
  fi
done

VERSIONED_ZIP="$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
LATEST_ZIP="$DIST_DIR/${PLUGIN_SLUG}.zip"
rm -f "$VERSIONED_ZIP" "$LATEST_ZIP"
(
  cd "$TMP_DIR"
  zip -rq "$VERSIONED_ZIP" "$PLUGIN_SLUG"
)
cp "$VERSIONED_ZIP" "$LATEST_ZIP"

echo "$VERSIONED_ZIP"
echo "$LATEST_ZIP"
