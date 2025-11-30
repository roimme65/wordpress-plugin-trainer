# Plugin Import - Quick Reference

Dieser Ordner dient zum Importieren und Testen von Plugin-Versionen in einer separaten WordPress-Umgebung.

## 📁 Verzeichnisstruktur

```
plugin-import/
├── source/     ← ZIP-Dateien hier ablegen
├── current/    → Aktuell getestetes Plugin
├── archive/    → Archivierte Versionen (automatisch)
└── logs/       → Import-Logs (automatisch)
```

## 🚀 Schnell-Import

### 1. Plugin-ZIP bereitstellen

**Option A: In source/ ablegen**
```bash
cp ~/Downloads/mein-plugin.zip plugin-import/source/
```

**Option B: Direkter Pfad**
```bash
# ZIP liegt irgendwo
./scripts/import-plugin.sh ~/Downloads/mein-plugin.zip
```

### 2. Import ausführen

**Basis-Import:**
```bash
./scripts/import-plugin.sh plugin-import/source/mein-plugin.zip
```

**Mit automatischer Aktivierung:**
```bash
./scripts/import-plugin.sh plugin-import/source/mein-plugin.zip --activate
```

**Ohne ZIP-Pfad (nutzt neustes ZIP aus source/):**
```bash
./scripts/import-plugin.sh --activate
```

### 3. Testen

- WordPress: http://localhost:8082
- phpMyAdmin: http://localhost:8083
- Credentials: wordpress / wordpress

## 📝 Import-Optionen

| Option | Beschreibung |
|--------|--------------|
| `--activate` | Plugin automatisch aktivieren |
| `--activate-slug=name` | Spezifischen Plugin-Slug verwenden |
| `--force-root-clean` | Erzwungenes Cleanup bei Berechtigungsproblemen |

## 🔄 Workflow-Beispiele

### Neues Plugin testen
```bash
# 1. ZIP in source/ legen
cp ~/plugin-v2.zip plugin-import/source/

# 2. Import mit Aktivierung
./scripts/import-plugin.sh --activate

# 3. Im Browser testen: http://localhost:8082
```

### Mehrere Versionen vergleichen
```bash
# Version 1 importieren
./scripts/import-plugin.sh plugin-v1.zip --activate
# Testen...

# Import-Stack stoppen
./scripts/stop-import-stack.sh

# Version 2 importieren
./scripts/import-plugin.sh plugin-v2.zip --activate
# Erneut testen...
```

### Alte Versionen im Archiv
```bash
# Archivierte Versionen anzeigen
ls -lh plugin-import/archive/

# Alte Version erneut importieren
./scripts/import-plugin.sh plugin-import/archive/plugin-20251130-*.zip --activate
```

## 🛠️ Stack-Verwaltung

**Status prüfen:**
```bash
docker-compose -f docker-compose.import.yml ps
```

**Logs anzeigen:**
```bash
docker-compose -f docker-compose.import.yml logs -f wordpress_import
```

**Stack stoppen:**
```bash
./scripts/stop-import-stack.sh
```

**Neustart (Daten behalten):**
```bash
docker-compose -f docker-compose.import.yml restart
```

**Alles löschen (inkl. Datenbank):**
```bash
docker-compose -f docker-compose.import.yml down -v
```

## ⚠️ Wichtige Hinweise

- **source/**: Lege hier deine ZIP-Dateien ab (oder verwende direkten Pfad)
- **current/**: Wird automatisch geleert vor jedem Import
- **archive/**: Speichert alle importierten ZIPs mit Zeitstempel
- **logs/**: Import-Logs für Debugging

## 🐛 Troubleshooting

**Problem: Berechtigungsfehler**
```bash
./scripts/import-plugin.sh plugin.zip --force-root-clean
```

**Problem: Plugin nicht sichtbar**
```bash
docker-compose -f docker-compose.import.yml exec wordpress_import \
  chown -R www-data:www-data /var/www/html/wp-content/plugins/
```

**Problem: Port 8082 belegt**
```bash
# In docker-compose.import.yml Ports ändern:
# ports: - "8090:80"
```

## 📚 Mehr Infos

Ausführliche Dokumentation: siehe Haupt-README.md im Projekt-Root
