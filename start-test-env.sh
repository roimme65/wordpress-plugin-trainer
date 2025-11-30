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
  # prefer docker compose plugin if available
  if docker compose version >/dev/null 2>&1 2>/dev/null; then
    docker compose up -d --build
  elif command -v docker-compose >/dev/null 2>&1; then
    docker-compose up -d --build
  else
    echo "ERROR: neither 'docker compose' plugin nor 'docker-compose' found."
    exit 3
  fi
else
  echo "Docker-Daemon nicht erreichbar (keine Berechtigung?). Versuche mit sudo..."
  if sudo docker compose version >/dev/null 2>&1 2>/dev/null; then
    sudo docker compose up -d --build
  elif sudo command -v docker-compose >/dev/null 2>&1; then
    sudo docker-compose up -d --build
  else
    echo "ERROR: sudo cannot run docker compose or docker-compose."
    exit 4
  fi
fi

echo "Fertig — prüfe mit 'docker ps' oder 'docker compose ps' den Status der Container."
