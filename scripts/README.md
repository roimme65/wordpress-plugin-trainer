# Scripts

Dieses Verzeichnis enthält Automatisierungs-Skripte für Entwicklung, Packaging und Releases.

## Release-Automation

### GitHub Actions (automatisch)

- Bei einem Tag-Push `v*` (z.B. `v1.1.0`) läuft der Workflow `.github/workflows/release.yml`.
- Der Workflow baut ein ZIP aus `plugin-target/wp-training-planner` und erstellt ein GitHub Release.
- Assets im Release:
  - `wp-training-planner-vX.Y.Z.zip`
  - `wp-training-planner-latest.zip`

### Lokales Release-Skript (Vorbild-Style)

`scripts/create-release.sh` automatisiert den lokalen Teil:

- Bump von `VERSION` (SemVer)
- Update der Plugin-Version in `plugin-target/wp-training-planner/wp-training-planner.php` (Header + `TRAINING_PLANNER_VERSION`)
- Anlegen/Updaten von `CHANGELOG.md`
- Anlegen von Release Notes unter `releases/vX.Y.Z.md`
- Commit, Tag und Push

Verwendung:

```bash
./scripts/create-release.sh patch
# oder
./scripts/create-release.sh minor --auto
```

Voraussetzungen:
- sauberes Git Working Tree
- Branch `main`
- Push-Rechte auf das GitHub-Repo

## Packaging

`scripts/package-plugin.sh` erzeugt ein ZIP unter `exports/`.

Standard: `plugin-source/wp-training-planner`

Override:

```bash
PLUGIN_DIR=plugin-target/wp-training-planner ./scripts/package-plugin.sh
```
