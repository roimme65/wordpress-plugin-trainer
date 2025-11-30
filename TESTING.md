# WordPress Plugin Testing mit Docker

## 🚀 Schnellstart

### 1. Container starten
```bash
docker-compose up -d
```

Die Container werden gestartet und sind nach ca. 30-60 Sekunden bereit.

### 2. WordPress aufrufen

Öffne im Browser:
- **WordPress**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081 (optional, für Datenbank-Zugriff)

### 3. WordPress installieren

1. Wähle die Sprache (Deutsch)
2. Fülle das Installationsformular aus:
   - **Website-Titel**: Training Planner Test
   - **Benutzername**: admin
   - **Passwort**: (sicheres Passwort wählen)
   - **E-Mail**: deine@email.de

3. Klicke auf "WordPress installieren"
4. Melde dich mit deinen Zugangsdaten an

### 4. Plugin aktivieren

1. Gehe zu **Plugins > Installierte Plugins**
2. Suche nach **Training Planner**
3. Klicke auf **Aktivieren**

Das Plugin erstellt automatisch die Datenbanktabellen.

### 5. Plugin testen

#### Admin-Bereich testen:
1. Gehe zu **Training Planner** im Menü
2. Klicke auf **Monthly Planning**
3. Wähle einen Monat und klicke auf **Generate Sessions**
4. Weise Trainer zu (erstelle vorher Test-Benutzer)
5. Teste den **Export ICS** Button

#### Frontend testen:
1. Erstelle eine neue Seite: **Seiten > Erstellen**
2. Titel: "Trainer Dashboard"
3. Füge den Shortcode ein: `[training_planner_dashboard]`
4. Veröffentliche die Seite
5. Öffne die Seite im Frontend (als eingeloggter Benutzer)

#### Test-Benutzer erstellen:
1. Gehe zu **Benutzer > Neu hinzufügen**
2. Erstelle 2-3 Test-Trainer mit der Rolle "Abonnent" oder "Redakteur"

## 🛠️ Docker-Befehle

### Container verwalten
```bash
# Container starten
docker-compose up -d

# Container stoppen
docker-compose down

# Container stoppen und Daten löschen
docker-compose down -v

# Logs anzeigen
docker-compose logs -f

# WordPress-Logs
docker-compose logs -f wordpress

# Status prüfen
docker-compose ps
```

### Plugin aktualisieren
Das Plugin ist als Volume gemountet. Änderungen am Code in `plugin-target/wp-training-planner/` sind sofort im Container sichtbar. Lade einfach die WordPress-Seite neu.

### In Container einsteigen
```bash
# WordPress-Container
docker-compose exec wordpress bash

# Datenbank-Container
docker-compose exec db mysql -u wordpress -pwordpress wordpress
```

## 📦 Verfügbare Dienste

| Service | URL | Beschreibung |
|---------|-----|--------------|
| WordPress | http://localhost:8080 | Haupt-WordPress-Installation |
| phpMyAdmin | http://localhost:8081 | Datenbank-Management (optional) |

### phpMyAdmin Zugangsdaten:
- **Server**: db
- **Benutzer**: wordpress
- **Passwort**: wordpress

## 🧪 Test-Szenarien

### 1. Session-Generierung
- [ ] Generiere Sessions für aktuellen Monat
- [ ] Prüfe Sommer vs. Winter-Zeitplan
- [ ] Versuche Sessions für denselben Monat erneut zu generieren (sollte Fehler zeigen)

### 2. Trainer-Zuweisung
- [ ] Weise verschiedene Trainer zu
- [ ] Speichere Zuweisungen
- [ ] Prüfe Verfügbarkeitsanzeige

### 3. Frontend-Dashboard
- [ ] Als Trainer einloggen
- [ ] Verfügbarkeit setzen (Ja/Nein/Vielleicht)
- [ ] Zugewiesene Sessions bestätigen
- [ ] Zwischen Monaten navigieren

### 4. ICS-Export
- [ ] Exportiere einen Monat
- [ ] Öffne .ics Datei in Kalender-App
- [ ] Prüfe Zeitzone und Termine

### 5. Security-Tests
- [ ] Versuche ohne Login auf Admin-Bereich zuzugreifen
- [ ] Teste Nonce-Validierung (manipuliere Formulare)
- [ ] Prüfe XSS-Schutz (versuche HTML/JS in Felder einzugeben)

## 🐛 Troubleshooting

### Port bereits belegt
Wenn Port 8080 bereits verwendet wird, ändere in `docker-compose.yml`:
```yaml
ports:
  - "8090:80"  # Ändere 8080 zu 8090
```

### Plugin nicht sichtbar
```bash
# Berechtigungen setzen
docker-compose exec wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/wp-training-planner
```

### Container startet nicht
```bash
# Alte Container und Volumes entfernen
docker-compose down -v

# Images neu laden
docker-compose pull

# Neu starten
docker-compose up -d
```

### WordPress-Debug-Modus
Debug-Modus ist bereits aktiviert. Logs erscheinen in:
```bash
docker-compose logs -f wordpress
```

## 🧹 Aufräumen

Nach dem Testen alles löschen:
```bash
# Container und Volumes löschen
docker-compose down -v

# Images löschen (optional)
docker rmi wordpress:latest mysql:8.0 phpmyadmin:latest
```

## 📝 Notizen

- Das Plugin-Verzeichnis ist als Volume gemountet - Änderungen am Code sind sofort verfügbar
- WordPress-Daten bleiben bei `docker-compose down` erhalten
- Verwende `docker-compose down -v` um auch Datenbank-Daten zu löschen
- Der Debug-Modus zeigt PHP-Fehler direkt im Browser an
