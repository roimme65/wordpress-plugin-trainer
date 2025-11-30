# WordPress Training Planner - Development & Testing Environment

Ein vollständiges WordPress-Plugin für die Verwaltung von Trainingseinheiten mit Docker-basierter Entwicklungs- und Testumgebung.

## 📦 Projekt-Struktur

```
wordpress-plugin-trainer/
├── plugin-target/              # Hauptentwicklung (verbesserte Version)
│   └── wp-training-planner/    # Produktives Plugin
├── plugin-import/              # Test-Umgebung für Vergleiche
│   ├── source/                 # ZIP-Dateien zum Import
│   ├── current/                # Aktuell getestetes Plugin
│   ├── archive/                # Archivierte Versionen
│   └── logs/                   # Import-Logs
├── scripts/                    # Automatisierungs-Scripts
│   ├── import-plugin.sh        # Plugin-Import
│   ├── package-plugin.sh       # Plugin-Verpackung
│   └── stop-import-stack.sh    # Import-Stack stoppen
├── docker-compose.yml          # Hauptentwicklung (Port 8080)
└── docker-compose.import.yml   # Vergleichstests (Port 8082)
```

## 🚀 Schnellstart - Hauptentwicklung

### 1. Entwicklungsumgebung starten

```bash
docker-compose up -d
```

**Verfügbar nach 30-60 Sekunden:**
- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8081

### 2. WordPress installieren

1. Öffne http://localhost:8080
2. Wähle Sprache (Deutsch)
3. Fülle Installationsformular aus:
   - **Website-Titel**: Training Planner Dev
   - **Benutzername**: admin
   - **Passwort**: sicheres Passwort
   - **E-Mail**: deine@email.de
4. Klicke "WordPress installieren"

### 3. Plugin aktivieren

1. Gehe zu **Plugins > Installierte Plugins**
2. Finde **Training Planner**
3. Klicke **Aktivieren**

Das Plugin erstellt automatisch alle Datenbanktabellen.

### 4. Test-Benutzer anlegen

```bash
./create-trainers.sh
```

Erstellt 5 Trainer-Accounts:
- Benutzername: `trainer1` bis `trainer5`
- Passwort: `trainer123`

### 5. Plugin testen

#### Admin-Bereich:
1. **Training Planner > Monthly Planning**
2. Wähle Monat und Jahr
3. Klicke **Generate Sessions**
4. Weise Trainer zu
5. Klicke **Save Assignments**
6. Optional: **Publish Plan** und **Export ICS**

#### Frontend (Trainer-Dashboard):
1. Erstelle neue Seite: **Seiten > Erstellen**
2. Titel: "Trainer Dashboard"
3. Füge Shortcode ein: `[training_planner_dashboard]`
4. Veröffentliche Seite
5. Teste als Trainer (Inkognito-Modus mit trainer1/trainer123)

## 🔄 Plugin-Import zum Vergleichstesten

Die Import-Umgebung ermöglicht es, **andere Plugin-Versionen** parallel zur Hauptentwicklung zu testen.

### Voraussetzungen

- ZIP-Datei des zu testenden Plugins
- Hauptentwicklung läuft bereits (oder getrennt testbar)

### Import-Workflow

#### Schritt 1: Plugin-ZIP vorbereiten

Platziere die Plugin-ZIP-Datei in einem Verzeichnis oder verwende einen direkten Pfad:

```bash
# Beispiel: Plugin als ZIP vorliegen
ls ~/Downloads/mein-plugin.zip
```

#### Schritt 2: Import-Script ausführen

**Basis-Import (ohne automatische Aktivierung):**
```bash
./scripts/import-plugin.sh ~/Downloads/mein-plugin.zip
```

**Mit automatischer Plugin-Aktivierung:**
```bash
./scripts/import-plugin.sh ~/Downloads/mein-plugin.zip --activate
```

**Mit spezifischem Plugin-Slug:**
```bash
./scripts/import-plugin.sh ~/Downloads/mein-plugin.zip --activate --activate-slug=mein-plugin-name
```

**Hilfe anzeigen:**
```bash
./scripts/import-plugin.sh --help
```

#### Schritt 3: Was passiert beim Import?

Das Script führt automatisch folgende Schritte aus:

1. **Verzeichnisse vorbereiten:**
   - Erstellt `plugin-import/source/`, `current/`, `archive/`, `logs/`
   
2. **Plugin extrahieren:**
   - Entpackt ZIP (unterstützt auch Windows-Backslash-Pfade)
   - Erkennt Plugin-Struktur automatisch
   - Normalisiert Verzeichnis-Layout

3. **In current/ installieren:**
   - Kopiert Plugin nach `plugin-import/current/`
   - Archiviert Original-ZIP mit Zeitstempel

4. **Docker-Stack starten:**
   - Startet `docker-compose.import.yml`
   - WordPress verfügbar auf Port **8082**
   - phpMyAdmin auf Port **8083**

5. **Optional: Plugin aktivieren (mit `--activate`):**
   - Installiert WP-CLI im Container
   - Aktiviert das Plugin
   - Führt Health-Check durch
   - Deaktiviert automatisch bei Fehlern

#### Schritt 4: Import-Umgebung nutzen

**Import-WordPress aufrufen:**
- WordPress: http://localhost:8082
- phpMyAdmin: http://localhost:8083

**WordPress-Installation:**
- Falls neu: Installiere WordPress wie in Hauptumgebung
- Das importierte Plugin ist unter **Plugins** verfügbar

**Plugin manuell aktivieren (falls nicht `--activate` verwendet):**
1. Gehe zu **Plugins > Installierte Plugins**
2. Aktiviere das importierte Plugin

#### Schritt 5: Logs überprüfen

Alle Import-Vorgänge werden protokolliert:

```bash
# Neuestes Log anzeigen
ls -lt plugin-import/logs/
cat plugin-import/logs/import-*.log
```

### Import-Optionen im Detail

| Option | Beschreibung | Beispiel |
|--------|-------------|----------|
| `<zip-path>` | Pfad zur Plugin-ZIP | `./scripts/import-plugin.sh ~/plugin.zip` |
| `--activate` oder `-a` | Plugin automatisch aktivieren | `--activate` |
| `--activate-slug=<name>` | Spezifischer Plugin-Slug | `--activate-slug=wp-training-planner` |
| `--force-root-clean` | Erzwingt root-Cleanup bei Berechtigungsproblemen | `--force-root-clean` |
| `--help` oder `-h` | Zeigt Hilfe an | `--help` |

### Import-Stack verwalten

**Status prüfen:**
```bash
docker-compose -f docker-compose.import.yml ps
```

**Logs anzeigen:**
```bash
docker-compose -f docker-compose.import.yml logs -f wordpress_import
```

**Neustart:**
```bash
docker-compose -f docker-compose.import.yml restart
```

**Stoppen und entfernen:**
```bash
./scripts/stop-import-stack.sh
# oder
docker-compose -f docker-compose.import.yml down
```

**Mit Daten löschen:**
```bash
docker-compose -f docker-compose.import.yml down -v
```

### Neues Plugin importieren

**Vorheriges Plugin entfernen und neues importieren:**

```bash
# Import-Stack stoppen
./scripts/stop-import-stack.sh

# Altes Plugin entfernen
rm -rf plugin-import/current/*

# Neues Plugin importieren
./scripts/import-plugin.sh ~/neues-plugin.zip --activate
```

### Troubleshooting beim Import

#### Problem: Berechtigungsfehler beim Cleanup

**Symptom:** `unable to fully clean plugin-import/current/`

**Lösung:**
```bash
# Mit --force-root-clean erneut versuchen
./scripts/import-plugin.sh plugin.zip --force-root-clean

# Oder manuell mit Docker bereinigen
docker run --rm -v "$(pwd)/plugin-import":/work --user root alpine sh -c "rm -rf /work/current/*"
```

#### Problem: Plugin nicht in Liste sichtbar

**Lösung:**
```bash
# Berechtigungen korrigieren
docker-compose -f docker-compose.import.yml exec wordpress_import chown -R www-data:www-data /var/www/html/wp-content/plugins/
```

#### Problem: Aktivierung schlägt fehl

**Symptom:** Plugin aktiviert sich nicht automatisch

**Lösung:**
1. Logs prüfen: `cat plugin-import/logs/import-*.log`
2. WordPress-Logs: `docker-compose -f docker-compose.import.yml logs wordpress_import`
3. Manuell im Browser aktivieren
4. Debug-Modus prüfen (bereits aktiviert in docker-compose.import.yml)

#### Problem: Port 8082 bereits belegt

**Lösung:**
```yaml
# In docker-compose.import.yml ändern:
ports:
  - "8090:80"  # Ändere 8082 zu 8090
```

## 🔧 Entwicklungs-Workflow

### Parallel-Entwicklung

**Szenario:** Hauptentwicklung in `plugin-target/` und Vergleichstest in `plugin-import/`

```bash
# Terminal 1: Hauptentwicklung
docker-compose up

# Terminal 2: Import-Test
./scripts/import-plugin.sh alternative-version.zip --activate

# Jetzt verfügbar:
# - Hauptversion: http://localhost:8080
# - Test-Version: http://localhost:8082
```

### Live-Entwicklung

Änderungen in `plugin-target/wp-training-planner/` sind **sofort** in der Hauptumgebung (Port 8080) sichtbar - einfach Browser-Seite neu laden!

### Plugin-Verpackung

```bash
# Erstellt ZIP aus plugin-target/
./scripts/package-plugin.sh
# Output: exports/wp-training-planner-<timestamp>.zip
```

## 📊 Umgebungs-Übersicht

| Umgebung | Docker-Datei | WordPress | phpMyAdmin | Plugin-Quelle |
|----------|--------------|-----------|------------|---------------|
| **Entwicklung** | docker-compose.yml | :8080 | :8081 | plugin-target/ (live) |
| **Import/Test** | docker-compose.import.yml | :8082 | :8083 | plugin-import/current/ |

## 🛠️ Docker-Befehle

### Beide Umgebungen

```bash
# Alle Container anzeigen
docker ps

# Alle Logs
docker-compose logs -f
docker-compose -f docker-compose.import.yml logs -f

# Cleanup (alle Container stoppen)
docker-compose down
./scripts/stop-import-stack.sh
```

### Datenbank-Zugriff

**Via phpMyAdmin:**
- Entwicklung: http://localhost:8081
- Import: http://localhost:8083
- Credentials: wordpress / wordpress

**Via CLI:**
```bash
# Entwicklungs-DB
docker-compose exec db mysql -u wordpress -pwordpress wordpress

# Import-DB
docker-compose -f docker-compose.import.yml exec db_import mysql -u wordpress -pwordpress wordpress
```

## 🧪 Test-Szenarien

### 1. Feature-Vergleich
- Importiere alte Plugin-Version auf Port 8082
- Entwickle neue Features auf Port 8080
- Vergleiche beide Versionen nebeneinander

### 2. Kompatibilitätstest
- Importiere Plugin mit verschiedenen WordPress-Versionen
- Teste verschiedene PHP-Versionen
- Prüfe Theme-Kompatibilität

### 3. Migration-Test
- Importiere Produktions-Plugin-Export
- Teste Datenbank-Migration
- Prüfe Kompatibilität mit neuer Version

## 📝 Best Practices

### Import-Workflow
1. **Immer Logs prüfen** nach dem Import
2. **Health-Checks** durchführen (automatisch mit `--activate`)
3. **Archiv nutzen** - alte Versionen in `plugin-import/archive/`
4. **Paralleltests** - beide Umgebungen gleichzeitig nutzen

### Entwicklung
1. **Live-Reload** nutzen - Änderungen sofort sichtbar
2. **Git committen** vor größeren Tests
3. **Debug-Modus** ist aktiviert - Fehler werden angezeigt
4. **Separate Browser** für verschiedene User-Tests

## 🚨 Wichtige Hinweise

- ⚠️ `docker-compose down -v` löscht **alle Daten** (Datenbank, WordPress-Dateien)
- ✅ `docker-compose restart` behält Daten
- 🔒 Standard-Passwörter nur für Entwicklung verwenden
- 📦 Import-Archiv wächst - regelmäßig bereinigen

## 🐛 Debugging

### PHP-Fehler anzeigen
```bash
docker-compose logs -f wordpress
docker-compose -f docker-compose.import.yml logs -f wordpress_import
```

### Datenbank prüfen
```bash
docker-compose exec db mysql -u wordpress -pwordpress wordpress -e "SHOW TABLES LIKE 'wp_training%';"
```

### Plugin-Verzeichnis prüfen
```bash
# Entwicklung
docker-compose exec wordpress ls -la /var/www/html/wp-content/plugins/

# Import
docker-compose -f docker-compose.import.yml exec wordpress_import ls -la /var/www/html/wp-content/plugins/
```

## 📚 Weitere Ressourcen

- Plugin-Dokumentation: `plugin-target/wp-training-planner/README.md`
- WordPress Codex: https://codex.wordpress.org/
- Docker Compose Docs: https://docs.docker.com/compose/

## 🤝 Contribution

Bei Fragen oder Problemen:
1. Logs prüfen: `plugin-import/logs/`
2. Docker-Logs: `docker-compose logs`
3. Issue erstellen im Repository

---

**Version:** 1.1  
**Autor:** Antigravity  
**Lizenz:** GPL v2 or later
