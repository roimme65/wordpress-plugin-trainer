#!/usr/bin/env bash
#
# WordPress Training Planner - Automatische Release-Erstellung
#
# Verwendung: ./scripts/create-release.sh [major|minor|patch] [--auto]
#
# Beispiel: ./scripts/create-release.sh patch
# Beispiel: ./scripts/create-release.sh patch --auto
#
# Ablauf:
# - prüft sauberes Git-Working-Tree + Branch main
# - bumped VERSION (SemVer)
# - aktualisiert plugin-target/wp-training-planner/wp-training-planner.php (Header + Const)
# - schreibt releases/vX.Y.Z.md und CHANGELOG.md
# - committed, tagged (vX.Y.Z) und pusht
#
# Hinweis:
# - Das GitHub Release wird danach automatisch via GitHub Actions (Tag-Push) erstellt.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

default_version_from_plugin() {
  local plugin_file="plugin-target/wp-training-planner/wp-training-planner.php"
  if [[ -f "$plugin_file" ]]; then
    local v
    v=$(grep -E '^ \* Version:' "$plugin_file" | head -n 1 | sed -E 's/^ \* Version:[[:space:]]*//') || true
    if [[ -n "${v:-}" ]]; then
      # Normalize: 1.1 -> 1.1.0
      if [[ "$v" =~ ^[0-9]+\.[0-9]+$ ]]; then
        echo "${v}.0"
        return 0
      fi
      if [[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "$v"
        return 0
      fi
    fi
  fi
  echo "1.0.0"
}

BUMP_TYPE="patch"
AUTO_MODE=false

for arg in "$@"; do
  case "$arg" in
    --auto) AUTO_MODE=true ;;
    major|minor|patch) BUMP_TYPE="$arg" ;;
    *)
      print_error "Ungültiges Argument: $arg"
      echo "Verwendung: $0 [major|minor|patch] [--auto]"
      exit 1
      ;;
  esac
done

if [[ ! -d .git ]] || [[ ! -f "scripts/package-plugin.sh" ]]; then
  print_error "Bitte aus dem Repository-Root ausführen"
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  print_error "Git-Arbeitsverzeichnis nicht sauber. Bitte committe/stashe alle Änderungen."
  git status --short
  exit 1
fi

CURRENT_BRANCH=$(git branch --show-current)
if [[ "$CURRENT_BRANCH" != "main" ]]; then
  print_error "Nicht auf main Branch. Aktuell auf: $CURRENT_BRANCH"
  exit 1
fi

if [[ ! -f VERSION ]]; then
  default_v=$(default_version_from_plugin)
  echo "$default_v" > VERSION
  print_warning "VERSION Datei erstellt mit ${default_v}"
fi

CURRENT_VERSION=$(cat VERSION | tr -d ' \t\n\r')
print_info "Aktuelle Version: v${CURRENT_VERSION}"

IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
MAJOR=${MAJOR:-0}; MINOR=${MINOR:-0}; PATCH=${PATCH:-0}

case "$BUMP_TYPE" in
  major) NEW_MAJOR=$((MAJOR + 1)); NEW_MINOR=0; NEW_PATCH=0 ;;
  minor) NEW_MAJOR=$MAJOR; NEW_MINOR=$((MINOR + 1)); NEW_PATCH=0 ;;
  patch) NEW_MAJOR=$MAJOR; NEW_MINOR=$MINOR; NEW_PATCH=$((PATCH + 1)) ;;
  *) print_error "Unbekannter bump type: $BUMP_TYPE"; exit 1 ;;
esac

NEW_VERSION="${NEW_MAJOR}.${NEW_MINOR}.${NEW_PATCH}"
print_info "Neue Version: v${NEW_VERSION} (${BUMP_TYPE} bump)"

if [[ "$AUTO_MODE" = false ]]; then
  read -p "$(echo -e ${YELLOW}Möchtest du mit dem Release v${NEW_VERSION} fortfahren? [y/N] ${NC})" -n 1 -r
  echo
  if [[ ! "$REPLY" =~ ^[Yy]$ ]]; then
    print_warning "Release abgebrochen"
    exit 0
  fi
else
  print_info "Auto-Mode: Fahre automatisch fort mit v${NEW_VERSION}"
fi

print_info "Aktualisiere VERSION Datei..."
echo "$NEW_VERSION" > VERSION
print_success "VERSION aktualisiert"

PLUGIN_FILE="plugin-target/wp-training-planner/wp-training-planner.php"
if [[ ! -f "$PLUGIN_FILE" ]]; then
  print_error "Plugin-Datei nicht gefunden: $PLUGIN_FILE"
  exit 1
fi

print_info "Aktualisiere Plugin-Version in ${PLUGIN_FILE}..."
# Header: * Version: X
sed -i -E "s/^( \* Version:).*/\1 ${NEW_VERSION}/" "$PLUGIN_FILE"
# Const: define( 'TRAINING_PLANNER_VERSION', 'X' );
sed -i -E "s/^(define\( 'TRAINING_PLANNER_VERSION', ')[^']*('\ );)/\1${NEW_VERSION}\2/" "$PLUGIN_FILE"
print_success "Plugin-Version aktualisiert"

mkdir -p releases
RELEASE_NOTES_FILE="releases/v${NEW_VERSION}.md"
if [[ ! -f "$RELEASE_NOTES_FILE" ]]; then
  print_info "Erstelle Release-Notes: ${RELEASE_NOTES_FILE}"
  cat > "$RELEASE_NOTES_FILE" << EOF
# Release v${NEW_VERSION}

**Veröffentlicht:** $(date +"%-d. %B %Y" 2>/dev/null || date +"%d. %B %Y")

## 📦 Inhalt

- WordPress Plugin "Training Planner" (Version ${NEW_VERSION})
- ZIP-Asset wird automatisch durch GitHub Actions erzeugt

## Änderungen

- (bitte ergänzen)
EOF
fi

print_info "Aktualisiere CHANGELOG.md..."
if [[ ! -f CHANGELOG.md ]]; then
  cat > CHANGELOG.md << 'EOF'
# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

---
EOF
fi

{
  head -n 7 CHANGELOG.md
  cat << EOFCHG

## [${NEW_VERSION}] - $(date +"%Y-%m-%d")

### Siehe
- Detaillierte Release-Notes: [releases/v${NEW_VERSION}.md](releases/v${NEW_VERSION}.md)

---
EOFCHG
  tail -n +8 CHANGELOG.md
} > CHANGELOG.md.new
mv CHANGELOG.md.new CHANGELOG.md
print_success "CHANGELOG.md aktualisiert"

print_info "Erstelle Git-Commit..."
git add VERSION "$PLUGIN_FILE" "$RELEASE_NOTES_FILE" CHANGELOG.md
git commit -m "Release v${NEW_VERSION}

- Bump version to v${NEW_VERSION}
- Add release notes
- Update CHANGELOG"
print_success "Commit erstellt"

print_info "Erstelle Git-Tag v${NEW_VERSION}..."
git tag -a "v${NEW_VERSION}" -m "Release ${NEW_VERSION}"
print_success "Tag erstellt: v${NEW_VERSION}"

print_info "Pushe zu GitHub..."
git push origin main
git push origin "v${NEW_VERSION}"
print_success "Gepusht (Commit + Tag)"

echo ""
print_success "🎉 Release v${NEW_VERSION} wurde erstellt."
print_info "GitHub Actions veröffentlicht das GitHub Release automatisch nach dem Tag-Push."
