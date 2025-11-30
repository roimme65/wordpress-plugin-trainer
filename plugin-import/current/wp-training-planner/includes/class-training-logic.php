<?php

class Training_Planner_Logic {

    public static function generate_month_sessions($year, $month) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        $table_plans = $wpdb->prefix . 'training_monthly_plans';

        // Determine season (Summer: Apr-Sep, Winter: Oct-Mar)
        $is_summer = ($month >= 4 && $month <= 9);

        // Check if sessions already exist for this month
        $existing_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d",
            $year, $month
        ));

        if ($existing_count > 0) {
            return false; // Sessions already exist
        }

        $num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $num_days; $day++) {
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $timestamp = strtotime($date_str);
            $weekday = date('N', $timestamp); // 1 (for Monday) through 7 (for Sunday)

            // Wednesday (3)
            if ($weekday == 3) {
                if ($is_summer) {
                    self::create_session($date_str, '17:30:00', '19:30:00', 'Training für Jugendliche');
                    self::create_session($date_str, '19:30:00', '22:00:00', 'Freies Spiel');
                } else {
                    self::create_session($date_str, '20:00:00', '22:00:00', 'Freies Spiel');
                }
            }
            // Friday (5)
            elseif ($weekday == 5) {
                if ($is_summer) {
                    self::create_session($date_str, '17:30:00', '19:30:00', 'Training für Jugendliche');
                    self::create_session($date_str, '19:30:00', '22:00:00', 'Training für Erwachsene');
                } else {
                    self::create_session($date_str, '17:00:00', '19:00:00', 'Training für Jugendliche');
                    self::create_session($date_str, '20:30:00', '22:15:00', 'Training für Erwachsene');
                }
            }
            // Saturday (6)
            elseif ($weekday == 6) {
                if ($is_summer) {
                    self::create_session($date_str, '10:00:00', '12:00:00', 'Offen');
                } else {
                    self::create_session($date_str, '10:00:00', '12:00:00', 'Training für Jugendliche');
                }
            }
        }

        // Create or get MonthlyPlan
        $existing_plan = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_plans WHERE year = %d AND month = %d",
            $year, $month
        ));

        if (!$existing_plan) {
            $wpdb->insert(
                $table_plans,
                array(
                    'year' => $year,
                    'month' => $month,
                    'is_published' => 0
                ),
                array('%d', '%d', '%d')
            );
        }
        }
        
        return true;
    }

    private static function create_session($date, $time, $end_time, $topic) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';

        $wpdb->insert(
            $table_sessions,
            array(
                'date' => $date,
                'time' => $time,
                'end_time' => $end_time,
                'topic' => $topic,
                'location' => 'Sporthalle Gymnasium, Tettnang' // Default location
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
}
