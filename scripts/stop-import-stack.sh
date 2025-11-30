#!/usr/bin/env bash
set -euo pipefail

# scripts/stop-import-stack.sh
# Stops and removes the import compose stack

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.import.yml"

echo "Stopping import compose stack..."
if command -v docker-compose >/dev/null 2>&1; then
  docker-compose -f "$COMPOSE_FILE" down
else
  docker compose -f "$COMPOSE_FILE" down
fi

echo "Stopped import stack."
