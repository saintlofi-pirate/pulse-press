#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(php -r '$file = file_get_contents($argv[1]); if (preg_match("/Version:\s*([^\n]+)/", $file, $m)) { echo trim($m[1]); }' "$ROOT_DIR/moonfarmer-reactions-lead-capture.php")"

if [[ -z "$VERSION" ]]; then
  echo "Could not read plugin version from moonfarmer-reactions-lead-capture.php" >&2
  exit 1
fi

BUILD_DIR="$ROOT_DIR/build"
WORK_DIR="$BUILD_DIR/release-work"
PLUGIN_SLUG="moonfarmer-reactions-lead-capture"
SRC_DIR="$WORK_DIR/source/$PLUGIN_SLUG"
PACKAGE_DIR="$WORK_DIR/package/$PLUGIN_SLUG"
ZIP_PATH="$BUILD_DIR/$PLUGIN_SLUG-$VERSION.zip"

rm -rf "$WORK_DIR" "$ZIP_PATH"
mkdir -p "$SRC_DIR" "$PACKAGE_DIR"

rsync -a --delete \
  --exclude '/.git' \
  --exclude '/node_modules' \
  --exclude '/vendor' \
  --exclude '/dist' \
  --exclude '/build' \
  "$ROOT_DIR/" "$SRC_DIR/"

(
  cd "$SRC_DIR"
  npm ci
  npm run build
  composer install --no-dev --optimize-autoloader --no-interaction
)

rsync -a --delete --exclude-from "$SRC_DIR/.distignore" "$SRC_DIR/" "$PACKAGE_DIR/"
printf '.distignore\n' > "$PACKAGE_DIR/.distignore"

if command -v wp >/dev/null 2>&1 && wp help dist-archive >/dev/null 2>&1; then
  wp dist-archive "$PACKAGE_DIR" "$ZIP_PATH" --plugin-dirname="$PLUGIN_SLUG" --create-target-dir
else
  (
    cd "$WORK_DIR/package"
    zip -qr "$ZIP_PATH" "$PLUGIN_SLUG"
  )
fi

echo "$ZIP_PATH"
