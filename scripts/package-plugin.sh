#!/usr/bin/env bash
set -euo pipefail

# scripts/package-plugin.sh
# Packages plugin-source/wp-training-planner into exports/<name>-<timestamp>.zip

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Override which plugin folder to package.
# Default remains plugin-source/wp-training-planner.
PLUGIN_DIR_REL="${PLUGIN_DIR:-plugin-source/wp-training-planner}"
PLUGIN_SRC="$ROOT_DIR/$PLUGIN_DIR_REL"
EXPORT_DIR="$ROOT_DIR/exports"

if [ ! -d "$PLUGIN_SRC" ]; then
  echo "No plugin source found at $PLUGIN_SRC"
  exit 1
fi

mkdir -p "$EXPORT_DIR"

PLUGIN_SLUG=$(basename "$PLUGIN_SRC")
TS=$(date -u +%Y%m%d-%H%M%S)
ZIP_NAME="${PLUGIN_SLUG}-${TS}.zip"
ZIP_PATH="$EXPORT_DIR/$ZIP_NAME"

echo "Creating ZIP: $ZIP_PATH"

pushd "$PLUGIN_SRC" >/dev/null
# create a ZIP that contains top-level folder $PLUGIN_SLUG/...
TMPDIR="$(mktemp -d)"
cp -r . "$TMPDIR/$PLUGIN_SLUG"
pushd "$TMPDIR" >/dev/null
zip -r "$ZIP_PATH" "$PLUGIN_SLUG" >/dev/null
popd >/dev/null
rm -rf "$TMPDIR"
popd >/dev/null

echo "Created $ZIP_PATH"
# Also create a 'latest.zip' for easy downloads from the exports folder
ln -f "$ZIP_PATH" "$EXPORT_DIR/${PLUGIN_SLUG}-latest.zip" || cp "$ZIP_PATH" "$EXPORT_DIR/${PLUGIN_SLUG}-latest.zip"

echo "Created $(basename "$EXPORT_DIR/${PLUGIN_SLUG}-latest.zip")"
