# Training Planner WordPress Plugin

Ein WordPress-Plugin für die Planung von Trainingseinheiten und die Verwaltung der Trainerverfügbarkeit.

## 📋 Features

- **Monatliche Trainingsplanung**: Automatische Generierung von Trainingseinheiten basierend auf Sommer-/Winterplan
- **Trainerverwaltung**: Zuweisung von Trainern zu Trainingseinheiten
- **Verfügbarkeitsabfrage**: Trainer können ihre Verfügbarkeit (Ja/Nein/Vielleicht) angeben
- **ICS-Export**: Exportiere den Trainingsplan für Kalender-Apps
- **Frontend-Dashboard**: Trainer-Dashboard via Shortcode für eingeloggte Benutzer
- **WordPress-Kompatibel**: Folgt WordPress-Coding-Standards und Best Practices

## 🚀 Installation

1. Lade den `wp-training-planner` Ordner in das WordPress-Plugin-Verzeichnis hoch (`wp-content/plugins/`)
2. Melde dich im WordPress-Admin-Dashboard an
3. Gehe zu **Plugins > Installierte Plugins**
4. Aktiviere **Training Planner**

Das Plugin erstellt automatisch die benötigten Datenbanktabellen bei der Aktivierung.

## 📖 Verwendung

### Admin (Backend)

1. Gehe zu **Training Planner** im Admin-Menü
2. **Dashboard**: Übersicht der anstehenden Trainingseinheiten
3. **Monthly Planning**:
   - Wähle Monat und Jahr
   - Klicke auf **Generate Sessions** um die Standard-Trainingseinheiten zu erstellen
   - Weise Trainer über die Dropdown-Menüs zu
   - Sehe die Verfügbarkeit der Trainer (Ja/Nein/Vielleicht)
   - Klicke auf **Save Assignments** zum Speichern
   - Klicke auf **Publish Plan** um den Monat als final zu markieren
   - Klicke auf **Export ICS** um den Plan als Kalender-Datei herunterzuladen

### Trainer (Frontend)

1. Erstelle eine neue Seite in WordPress (z.B. "Trainer Dashboard")
2. Füge den Shortcode `[training_planner_dashboard]` zum Seiteninhalt hinzu
3. Trainer müssen eingeloggt sein, um diese Seite zu sehen
4. Trainer können:
   - Trainingseinheiten für den aktuellen/ausgewählten Monat sehen
   - Ihre Verfügbarkeit angeben (Ja/Nein/Vielleicht)
   - Zugewiesene Trainingseinheiten bestätigen

## ⚙️ Trainingslogik

### Saisons
- **Sommer**: April - September
- **Winter**: Oktober - März

### Standardplan

**Mittwoch:**
- Sommer: 17:30-19:30 (Jugend), 19:30-22:00 (Freies Spiel)
- Winter: 20:00-22:00 (Freies Spiel)

**Freitag:**
- Sommer: 17:30-19:30 (Jugend), 19:30-22:00 (Erwachsene)
- Winter: 17:00-19:00 (Jugend), 20:30-22:15 (Erwachsene)

**Samstag:**
- Sommer: 10:00-12:00 (Offen)
- Winter: 10:00-12:00 (Jugend)

**Standardort:** Sporthalle Gymnasium, Tettnang

## 🔧 Technische Details

### Systemanforderungen
- WordPress: 5.0 oder höher
- PHP: 7.4 oder höher
- MySQL: 5.6 oder höher

### Datenbanktabellen
- `wp_training_sessions` - Trainingseinheiten
- `wp_training_availability` - Trainerverfügbarkeit
- `wp_training_survey_status` - Umfragestatus
- `wp_training_monthly_plans` - Monatsplanung

### Security Features
- Nonce-Verifizierung für alle Formulare
- Input-Sanitization mit WordPress-Funktionen
- Prepared Statements für Datenbankabfragen
- Capability-Checks für Admin-Funktionen
- XSS-Schutz durch Escaping

### Internationalisierung
- Text Domain: `training-planner`
- Bereit für Übersetzungen
- Deutsche Strings als Standard

## 📝 Changelog

### Version 1.1
- ✅ Syntax-Fehler in `class-training-logic.php` behoben
- ✅ Textdomain für Übersetzungen hinzugefügt
- ✅ Security verbessert (Nonce-Checks, Sanitization, Escaping)
- ✅ Input-Validierung verbessert
- ✅ ICS-Export mit vollständiger Timezone-Definition
- ✅ Deaktivierungs-Hook hinzugefügt
- ✅ Code-Dokumentation erweitert
- ✅ CSS-Styling verbessert
- ✅ WordPress-Coding-Standards implementiert

### Version 1.0
- Initiale Version
- Grundfunktionen für Trainingsplanung

## 👤 Autor

**Antigravity**

## 📄 Lizenz

GPL v2 oder höher - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## 🐛 Support

Bei Fragen oder Problemen erstelle bitte ein Issue im GitHub-Repository.

## 🔜 Geplante Features

- E-Mail-Benachrichtigungen für Trainer
- Kalender-Integration
- Statistiken und Berichte
- Multi-Language-Support
- Export als PDF
