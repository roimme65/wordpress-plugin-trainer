#!/usr/bin/env bash
set -euo pipefail

# scripts/stop-import-stack.sh
# Stops and removes the import compose stack

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.import.yml"

echo "Stopping import compose stack..."
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
  docker compose -f "$COMPOSE_FILE" down
elif command -v docker-compose >/dev/null 2>&1; then
  docker-compose -f "$COMPOSE_FILE" down
else
  echo "ERROR: neither 'docker compose' plugin nor 'docker-compose' found."
fi

echo "Stopped import stack."
