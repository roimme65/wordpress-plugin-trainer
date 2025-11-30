#!/usr/bin/env bash
set -euo pipefail

# scripts/import-plugin.sh
# Usage: scripts/import-plugin.sh <path-to-plugin-zip-or-folder>
# Copies the plugin into plugin-import/ and starts the import compose stack (docker-compose.import.yml)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMPORT_DIR="$ROOT_DIR/plugin-import"
SOURCE_DIR="$IMPORT_DIR/source"
CURRENT_DIR="$IMPORT_DIR/current"
ARCHIVE_DIR="$IMPORT_DIR/archive"
LOGS_DIR="$IMPORT_DIR/logs"
COMPOSE_FILE="$ROOT_DIR/docker-compose.import.yml"


for d in "$IMPORT_DIR" "$SOURCE_DIR" "$CURRENT_DIR" "$ARCHIVE_DIR" "$LOGS_DIR"; do
  mkdir -p "$d"
done

TIMESTAMP=$(date -u +%Y%m%d-%H%M%S)

# flags
ACTIVATE=false
ACTIVATE_SLUG=""

FORCE_ROOT_CLEAN=false

SOURCE=""

# parse options: allow --activate, --activate-slug=<slug>, --force-root-clean
while [ "$#" -gt 0 ]; do
  case "$1" in
    --activate|-a)
      ACTIVATE=true
      shift
      ;;
    --activate-slug=*)
      ACTIVATE_SLUG="${1#--activate-slug=}"
      shift
      ;;
    --force-root-clean)
      FORCE_ROOT_CLEAN=true
      shift
      ;;
    --help|-h)
      echo "Usage: $0 [<zip-path>] [--activate] [--activate-slug=slug] [--force-root-clean]"
      exit 0
      ;;
    *)
      # assume it's the source path
      if [ -z "${SOURCE:-}" ]; then
        SOURCE="$1"
      else
        echo "Unknown positional argument: $1" >&2
        exit 1
      fi
      shift
      ;;
  esac
done

# If a source path was provided, copy it into SOURCE_DIR
if [ -n "$SOURCE" ] && [ -f "$SOURCE" ]; then
  cp "$SOURCE" "$SOURCE_DIR/"
  SOURCE="$SOURCE_DIR/$(basename "$SOURCE")"
fi

# select newest zip in source dir if no SOURCE provided
if [ -z "$SOURCE" ]; then
  newest=$(ls -1t "$SOURCE_DIR"/*.zip 2>/dev/null | head -n1 || true)
  if [ -z "$newest" ]; then
    echo "No zip files found in $SOURCE_DIR. Place a zip there or provide a path."
    exit 2
  fi
  SOURCE="$newest"
fi

if [ ! -f "$SOURCE" ]; then
  echo "Source path not found: $SOURCE"
  exit 2
fi

echo "Preparing import from: $SOURCE"

LOGFILE="$LOGS_DIR/import-$TIMESTAMP.log"

# clean current dir (but keep .gitkeep if present)
safe_clean_current() {
  echo "Cleaning current dir: $CURRENT_DIR" | tee -a "$LOGFILE"
  if find "$CURRENT_DIR" -mindepth 1 -maxdepth 1 -not -name '.gitkeep' -exec rm -rf {} + 2>&1 | tee -a "$LOGFILE"; then
    return 0
  fi

  echo "Warning: unable to fully clean $CURRENT_DIR due to permission errors" | tee -a "$LOGFILE"
  # Try automatic root cleanup (use ephemeral container to chown+remove)
  echo "Attempting automatic root cleanup (ephemeral container)" | tee -a "$LOGFILE"
  docker run --rm -v "$IMPORT_DIR":/work --user root alpine sh -c "chown -R $(id -u):$(id -g) /work || true; rm -rf /work/current/*" >>"$LOGFILE" 2>&1 || true
  # try again
  if find "$CURRENT_DIR" -mindepth 1 -maxdepth 1 -not -name '.gitkeep' -exec rm -rf {} + 2>&1 | tee -a "$LOGFILE"; then
    return 0
  fi

  echo "ERROR: could not clean $CURRENT_DIR even after root cleanup." | tee -a "$LOGFILE"
  exit 5
}

safe_clean_current

# extract zip into a temp dir to inspect structure
TMPDIR=$(mktemp -d)
echo "Extracting to tmp: $TMPDIR" | tee -a "$LOGFILE"

# detect if archive uses Windows-style backslashes in member names and skip unzip
if command -v python3 >/dev/null 2>&1; then
  if python3 - "$SOURCE" <<'PY' >/dev/null 2>&1
import zipfile,sys
z=zipfile.ZipFile(sys.argv[1])
has_bs=any('\\' in info.filename for info in z.infolist())
sys.exit(2 if has_bs else 0)
PY
  then
    # no backslashes detected — try normal unzip
    if ! unzip -q "$SOURCE" -d "$TMPDIR" 2>>"$LOGFILE"; then
      echo "unzip failed — attempting python fallback extraction" | tee -a "$LOGFILE"
      UNZIP_FAILED=1
    else
      UNZIP_FAILED=0
    fi
  else
    # python detected backslashes (exit 2) — don't run unzip (it writes odd entries), go to python fallback
    echo "Archive appears to use Windows backslashes — using python extractor" | tee -a "$LOGFILE"
    UNZIP_FAILED=1
  fi
else
  # no python available — try unzip and fall back if it fails
  if ! unzip -q "$SOURCE" -d "$TMPDIR" 2>>"$LOGFILE"; then
    echo "unzip failed — attempting python fallback extraction" | tee -a "$LOGFILE"
    UNZIP_FAILED=1
  else
    UNZIP_FAILED=0
  fi
fi

if [ "${UNZIP_FAILED:-0}" -eq 1 ]; then
  if command -v python3 >/dev/null 2>&1; then
    # cleanup any partial/unzip-created files before python fallback
    rm -rf "$TMPDIR"/* 2>>"$LOGFILE" || true
    # Use a python extractor that normalizes Windows backslashes in zip entries
    if python3 - "$SOURCE" "$TMPDIR" <<'PY' >>"$LOGFILE" 2>&1
import zipfile,os,sys,shutil
src = sys.argv[1]
out = sys.argv[2]
with zipfile.ZipFile(src) as z:
    for info in z.infolist():
        # convert Windows-style backslashes into forward slashes
        name = info.filename.replace('\\', '/')
        # Normalize and prevent absolute paths
        name = name.lstrip('/\\')
        if name.endswith('/'):
            os.makedirs(os.path.join(out, name), exist_ok=True)
        else:
            path = os.path.join(out, name)
            os.makedirs(os.path.dirname(path), exist_ok=True)
            with z.open(info) as r, open(path, 'wb') as w:
                shutil.copyfileobj(r, w)
PY
    then
      echo "python extraction succeeded" | tee -a "$LOGFILE"
    else
      echo "python extraction failed" | tee -a "$LOGFILE"
      echo "ERROR: unzip failed. See $LOGFILE" | tee -a "$LOGFILE"
      exit 3
    fi
  else
    echo "python3 not found, cannot fallback. See $LOGFILE" | tee -a "$LOGFILE"
    echo "ERROR: unzip failed and no python fallback available" | tee -a "$LOGFILE"
    exit 3
  fi
fi

# find top-level entries
mapfile -t top_entries < <(find "$TMPDIR" -mindepth 1 -maxdepth 1 -printf '%f\n' 2>/dev/null)

# Determine plugin folder name
if [ -d "$TMPDIR/${top_entries[0]}" ] && [ ${#top_entries[@]} -eq 1 ]; then
  # single top-level folder - use it
  PLUGIN_DIR_NAME="${top_entries[0]}"
else
  # no single folder - create a folder named after zip basename (without ext)
  BASENAME=$(basename "$SOURCE")
  PLUGIN_DIR_NAME="${BASENAME%.*}"
  mkdir -p "$TMPDIR/$PLUGIN_DIR_NAME"
  # move everything into that folder
  find "$TMPDIR" -mindepth 1 -maxdepth 1 -not -name "$PLUGIN_DIR_NAME" -exec mv {} "$TMPDIR/$PLUGIN_DIR_NAME/" \; 2>>"$LOGFILE" || true
fi

echo "Moving plugin to current: $PLUGIN_DIR_NAME" | tee -a "$LOGFILE"
mv "$TMPDIR/$PLUGIN_DIR_NAME" "$CURRENT_DIR/" 2>>"$LOGFILE" || { echo "MOVE FAILED" | tee -a "$LOGFILE"; exit 4; }

# archive the zip with timestamp
ARCHIVE_NAME="$(basename "$SOURCE" .zip)-$TIMESTAMP.zip"
mv "$SOURCE" "$ARCHIVE_DIR/$ARCHIVE_NAME" 2>>"$LOGFILE" || { echo "ARCHIVE MOVE FAILED" | tee -a "$LOGFILE"; }

# cleanup tmp
rm -rf "$TMPDIR"

echo "Import completed. Archive: $ARCHIVE_DIR/$ARCHIVE_NAME" | tee -a "$LOGFILE"

echo "Files in plugin-import/:
$(ls -la "$IMPORT_DIR")"

echo "Starting import docker-compose stack..."
# Use docker-compose (standalone) if available, otherwise docker compose
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
  docker compose -f "$COMPOSE_FILE" up -d --build
elif command -v docker-compose >/dev/null 2>&1; then
  docker-compose -f "$COMPOSE_FILE" up -d --build
else
  echo "ERROR: neither 'docker compose' plugin nor 'docker-compose' found. Install one of them."
  exit 3
fi

echo "Import WP instance should be available at http://localhost:8082 (phpMyAdmin: http://localhost:8083)"

# Optionally activate the plugin inside the import WP instance using WP-CLI
if [ "$ACTIVATE" = "true" ]; then
  # detect plugin slug if not provided
  if [ -z "$ACTIVATE_SLUG" ]; then
    # take first folder name from current
    ACTIVATE_SLUG=$(find "$CURRENT_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | head -n1 || true)
  fi
  if [ -z "$ACTIVATE_SLUG" ]; then
    echo "No plugin folder found in $CURRENT_DIR to activate" | tee -a "$LOGFILE"
  else
    echo "Attempting to activate plugin '$ACTIVATE_SLUG' in import WP instance" | tee -a "$LOGFILE"

    # Build commands for wp-cli installation and plugin management
    INSTALL_CMD='if ! command -v wp >/dev/null 2>&1; then if command -v curl >/dev/null 2>&1; then curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp; else php -r "copy(\"https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar\", \"/usr/local/bin/wp\");"; fi; chmod +x /usr/local/bin/wp || true; fi'

    ACTIVATE_CMD="/usr/local/bin/wp --allow-root plugin activate '$ACTIVATE_SLUG' --path=/var/www/html"
    DEACTIVATE_CMD="/usr/local/bin/wp --allow-root plugin deactivate '$ACTIVATE_SLUG' --path=/var/www/html"
    IS_ACTIVE_CMD="/usr/local/bin/wp --allow-root plugin is-active '$ACTIVATE_SLUG' --path=/var/www/html >/dev/null 2>&1"

    # Run install+activate and capture exit status
    if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
      docker compose -f "$COMPOSE_FILE" exec --user root -T wordpress_import sh -lc "$INSTALL_CMD; $ACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE"
      ACT_EXIT=${PIPESTATUS[0]:-1}
    elif command -v docker-compose >/dev/null 2>&1; then
      docker-compose -f "$COMPOSE_FILE" exec --user root -T wp-training-planner-wp-import sh -lc "$INSTALL_CMD; $ACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE"
      ACT_EXIT=${PIPESTATUS[0]:-1}
    else
      echo "Cannot run WP-CLI activation: no docker compose tool found" | tee -a "$LOGFILE"
      ACT_EXIT=2
    fi

    # verify activation status
    if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
      docker compose -f "$COMPOSE_FILE" exec --user root -T wordpress_import sh -c "$IS_ACTIVE_CMD" 2>>"$LOGFILE"
      IS_ACTIVE_EXIT=${PIPESTATUS[0]:-1}
    elif command -v docker-compose >/dev/null 2>&1; then
      docker-compose -f "$COMPOSE_FILE" exec --user root -T wp-training-planner-wp-import sh -c "$IS_ACTIVE_CMD" 2>>"$LOGFILE"
      IS_ACTIVE_EXIT=${PIPESTATUS[0]:-1}
    else
      IS_ACTIVE_EXIT=2
    fi

    if [ "$ACT_EXIT" -ne 0 ] || [ "$IS_ACTIVE_EXIT" -ne 0 ]; then
      echo "Plugin activation failed (exit: $ACT_EXIT / active check: $IS_ACTIVE_EXIT). Attempting deactivate and logging the error." | tee -a "$LOGFILE"
      if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
        docker compose -f "$COMPOSE_FILE" exec --user root -T wordpress_import sh -lc "$DEACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE" || true
      elif command -v docker-compose >/dev/null 2>&1; then
        docker-compose -f "$COMPOSE_FILE" exec --user root -T wp-training-planner-wp-import sh -lc "$DEACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE" || true
      fi
      echo "Activation failed — plugin will not be active. See logs: $LOGFILE" | tee -a "$LOGFILE"
      ACTIVATION_OK=false
    else
      echo "Activation appears successful" | tee -a "$LOGFILE"
      ACTIVATION_OK=true
    fi

    # do a site health check (HTTP GET) with retries
    HEALTH_URL="http://localhost:8082/"
    echo "Running health check against $HEALTH_URL" | tee -a "$LOGFILE"
    HEALTH_OK=1
    for i in 1 2 3 4 5 6; do
      if command -v curl >/dev/null 2>&1; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$HEALTH_URL" || echo "000")
        if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
          echo "Health check: HTTP $HTTP_CODE (OK)" | tee -a "$LOGFILE"
          HEALTH_OK=0
          break
        else
          echo "Health check attempt $i: HTTP $HTTP_CODE" | tee -a "$LOGFILE"
        fi
      else
        echo "curl not available on host — skipping health check" | tee -a "$LOGFILE"
        break
      fi
      sleep 2
    done

    if [ "$ACTIVATION_OK" = true ] && [ "$HEALTH_OK" -ne 0 ]; then
      echo "Warning: activation succeeded but site healthcheck failed" | tee -a "$LOGFILE"
      # if activation succeeded but site shows critical errors, try to deactivate plugin to restore site
      echo "Attempting to deactivate plugin due to failed healthcheck" | tee -a "$LOGFILE"
      if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 2>/dev/null; then
        docker compose -f "$COMPOSE_FILE" exec --user root -T wordpress_import sh -lc "$DEACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE" || true
      elif command -v docker-compose >/dev/null 2>&1; then
        docker-compose -f "$COMPOSE_FILE" exec --user root -T wp-training-planner-wp-import sh -lc "$DEACTIVATE_CMD" 2>&1 | tee -a "$LOGFILE" || true
      fi
      echo "Deactivation attempted after failed healthcheck; check logs for details" | tee -a "$LOGFILE"
    fi
  fi
fi
