# Training Planner WordPress Plugin

This plugin replaces the Python-based training planning tool. It integrates directly into WordPress.

## Installation

1. Copy the `wp-training-planner` folder to your WordPress plugins directory (`wp-content/plugins/`).
2. Log in to your WordPress Admin Dashboard.
3. Go to **Plugins > Installed Plugins**.
4. Activate **Training Planner**.

## Usage

### Admin (Backend)
- Go to **Training Planner** in the admin menu.
- **Dashboard**: View upcoming sessions and delete them if necessary.
- **Monthly Planning**:
    - Select a month and year.
    - Click **Generate Sessions** to create the standard schedule for that month (based on Summer/Winter logic).
    - Assign trainers to sessions using the dropdowns.
    - View trainer availability (Yes/No/Maybe) in the table.
    - Click **Save Assignments** to save changes.
    - Click **Publish Plan** to mark the month as final.
    - Click **Export ICS** to download the schedule for your calendar.

### Trainer (Frontend)
- Create a new page in WordPress (e.g., "Trainer Dashboard").
- Add the shortcode `[training_planner_dashboard]` to the page content.
- Trainers must be logged in to view this page.
- Trainers can:
    - View sessions for the current/selected month.
    - Set their availability (Yes/No/Maybe).
    - Confirm their assigned sessions.

## Logic
- **Summer Season**: April - September
- **Winter Season**: October - March
- **Schedule**:
    - Wednesdays: 17:30-19:30 (Jugend), 19:30-22:00 (Freies Spiel) [Summer] / 20:00-22:00 [Winter]
    - Fridays: 17:30-19:30 (Jugend), 19:30-22:00 (Erwachsene) [Summer] / 17:00-19:00, 20:30-22:15 [Winter]
    - Saturdays: 10:00-12:00 (Offen/Jugend)
