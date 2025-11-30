<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Training Planner Logic Class
 * Handles the business logic for generating training sessions
 */
class Training_Planner_Logic {

    /**
     * Generate training sessions for a specific month
     *
     * @param int $year The year
     * @param int $month The month (1-12)
     * @return bool True if sessions were generated, false if they already exist
     */
    public static function generate_month_sessions( $year, $month ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        $table_plans = $wpdb->prefix . 'training_monthly_plans';

        // Validate input
        $year = absint( $year );
        $month = absint( $month );
        
        if ( $month < 1 || $month > 12 ) {
            return false;
        }

        // Determine season (Summer: Apr-Sep, Winter: Oct-Mar)
        $is_summer = ( $month >= 4 && $month <= 9 );

        // Check if sessions already exist for this month
        $existing_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d",
            $year,
            $month
        ) );

        if ( $existing_count > 0 ) {
            return false; // Sessions already exist
        }

        // Get number of days in month (compatible without calendar extension)
        $num_days = date( 't', mktime( 0, 0, 0, $month, 1, $year ) );

        for ( $day = 1; $day <= $num_days; $day++ ) {
            $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
            $timestamp = strtotime( $date_str );
            $weekday = date( 'N', $timestamp ); // 1 (for Monday) through 7 (for Sunday)

            // Wednesday (3)
            if ( $weekday == 3 ) {
                if ( $is_summer ) {
                    self::create_session( $date_str, '17:30:00', '19:30:00', __( 'Training für Jugendliche', 'training-planner' ) );
                    self::create_session( $date_str, '19:30:00', '22:00:00', __( 'Freies Spiel', 'training-planner' ) );
                } else {
                    self::create_session( $date_str, '20:00:00', '22:00:00', __( 'Freies Spiel', 'training-planner' ) );
                }
            }
            // Friday (5)
            elseif ( $weekday == 5 ) {
                if ( $is_summer ) {
                    self::create_session( $date_str, '17:30:00', '19:30:00', __( 'Training für Jugendliche', 'training-planner' ) );
                    self::create_session( $date_str, '19:30:00', '22:00:00', __( 'Training für Erwachsene', 'training-planner' ) );
                } else {
                    self::create_session( $date_str, '17:00:00', '19:00:00', __( 'Training für Jugendliche', 'training-planner' ) );
                    self::create_session( $date_str, '20:30:00', '22:15:00', __( 'Training für Erwachsene', 'training-planner' ) );
                }
            }
            // Saturday (6)
            elseif ( $weekday == 6 ) {
                if ( $is_summer ) {
                    self::create_session( $date_str, '10:00:00', '12:00:00', __( 'Offen', 'training-planner' ) );
                } else {
                    self::create_session( $date_str, '10:00:00', '12:00:00', __( 'Training für Jugendliche', 'training-planner' ) );
                }
            }
        }

        // Create or get MonthlyPlan
        $existing_plan = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table_plans WHERE year = %d AND month = %d",
            $year,
            $month
        ) );

        if ( ! $existing_plan ) {
            $wpdb->insert(
                $table_plans,
                array(
                    'year'         => $year,
                    'month'        => $month,
                    'is_published' => 0,
                ),
                array( '%d', '%d', '%d' )
            );
        }

        return true;
    }

    /**
     * Create a single training session
     *
     * @param string $date Date in Y-m-d format
     * @param string $time Start time in H:i:s format
     * @param string $end_time End time in H:i:s format
     * @param string $topic Session topic/description
     */
    private static function create_session( $date, $time, $end_time, $topic ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';

        $wpdb->insert(
            $table_sessions,
            array(
                'date'     => sanitize_text_field( $date ),
                'time'     => sanitize_text_field( $time ),
                'end_time' => sanitize_text_field( $end_time ),
                'topic'    => sanitize_text_field( $topic ),
                'location' => __( 'Sporthalle Gymnasium, Tettnang', 'training-planner' ), // Default location
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }
}
