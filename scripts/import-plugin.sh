#!/usr/bin/env bash
set -euo pipefail

# scripts/import-plugin.sh
# Usage: scripts/import-plugin.sh <path-to-plugin-zip-or-folder>
# Copies the plugin into plugin-import/ and starts the import compose stack (docker-compose.import.yml)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMPORT_DIR="$ROOT_DIR/plugin-import"
COMPOSE_FILE="$ROOT_DIR/docker-compose.import.yml"

if [ $# -lt 1 ]; then
  echo "Usage: $0 <path-to-plugin-zip-or-folder>"
  exit 1
fi

SOURCE="$1"

if [ ! -d "$IMPORT_DIR" ]; then
  mkdir -p "$IMPORT_DIR"
fi

echo "Cleaning existing import folder..."
# keep none to avoid collisions: remove contents
rm -rf "$IMPORT_DIR"/*

if [ -f "$SOURCE" ]; then
  # assume zip
  case "$SOURCE" in
    *.zip)
      echo "Extracting zip to import directory..."
      unzip -q "$SOURCE" -d "$IMPORT_DIR"
      ;;
    *)
      echo "Copying file into import directory..."
      cp "$SOURCE" "$IMPORT_DIR/"
      ;;
  esac
elif [ -d "$SOURCE" ]; then
  echo "Copying folder into import directory..."
  # copy contents — if plugin folder then use its name
  cp -r "$SOURCE"/* "$IMPORT_DIR/"
else
  echo "Source path not found: $SOURCE"
  exit 2
fi

echo "Files in plugin-import/:
$(ls -la "$IMPORT_DIR")"

echo "Starting import docker-compose stack..."
# Use docker-compose (standalone) if available, otherwise docker compose
if command -v docker-compose >/dev/null 2>&1; then
  docker-compose -f "$COMPOSE_FILE" up -d --build
else
  docker compose -f "$COMPOSE_FILE" up -d --build
fi

echo "Import WP instance should be available at http://localhost:8082 (phpMyAdmin: http://localhost:8083)"
