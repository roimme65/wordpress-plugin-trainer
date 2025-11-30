#!/usr/bin/env bash
set -euo pipefail

# Start helper for the test environment
# Attempts to run `docker compose up -d --build` and falls back to sudo if daemon is not accessible.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker ist nicht installiert. Bitte installiere Docker: https://docs.docker.com/get-docker/"
  exit 2
fi

echo "Versuche, den Docker-Daemon zu erreichen..."
if docker info >/dev/null 2>&1; then
  echo "Docker-Daemon erreichbar — starte Compose-Services..."
  docker compose up -d --build
else
  echo "Docker-Daemon nicht erreichbar (keine Berechtigung?). Versuche mit sudo..."
  sudo docker compose up -d --build
fi

echo "Fertig — prüfe mit 'docker ps' oder 'docker compose ps' den Status der Container."
