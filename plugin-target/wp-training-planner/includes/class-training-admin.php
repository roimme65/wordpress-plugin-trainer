<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Training Planner Admin Class
 * Handles all admin-related functionality
 */
class Training_Planner_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_post_tp_export_ics', array( $this, 'handle_ics_export' ) );
    }

    /**
     * Handle ICS calendar export
     */
    public function handle_ics_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'training-planner' ) );
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'tp_export_ics' ) ) {
            wp_die( esc_html__( 'Security check failed', 'training-planner' ) );
        }
        
        $year  = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : date( 'Y' );
        $month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : date( 'n' );
        
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        
        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d AND assigned_trainer_id IS NOT NULL ORDER BY date, time", 
            $year,
            $month 
        ) );
        
        $ics_content = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Training Planner//DE',
            'METHOD:PUBLISH',
            'CALSCALE:GREGORIAN',
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Berlin',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        );
        
        foreach ( $sessions as $session ) {
            $start_timestamp = strtotime( $session->date . ' ' . $session->time );
            $start_dt        = gmdate( 'Ymd\THis', $start_timestamp );
            
            if ( $session->end_time ) {
                $end_timestamp = strtotime( $session->date . ' ' . $session->end_time );
            } else {
                $end_timestamp = $start_timestamp + 7200; // 2 hours default
            }
            $end_dt = gmdate( 'Ymd\THis', $end_timestamp );
            
            $trainer      = get_userdata( $session->assigned_trainer_id );
            $trainer_name = $trainer ? $trainer->display_name : __( 'Unknown', 'training-planner' );
            
            $ics_content[] = 'BEGIN:VEVENT';
            $ics_content[] = 'UID:' . $session->id . '-' . $session->date . '@training-planner';
            $ics_content[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
            $ics_content[] = 'DTSTART;TZID=Europe/Berlin:' . $start_dt;
            $ics_content[] = 'DTEND;TZID=Europe/Berlin:' . $end_dt;
            $ics_content[] = 'SUMMARY:' . $this->escape_ics( sprintf( __( 'Training: %s', 'training-planner' ), $trainer_name ) );
            $ics_content[] = 'DESCRIPTION:' . $this->escape_ics( sprintf( __( 'Thema: %s', 'training-planner' ), $session->topic ) );
            $ics_content[] = 'LOCATION:' . $this->escape_ics( $session->location );
            $ics_content[] = 'STATUS:CONFIRMED';
            $ics_content[] = 'END:VEVENT';
        }
        
        $ics_content[] = 'END:VCALENDAR';
        
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=training_plan_' . $year . '_' . $month . '.ics' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
        
        echo implode( "\r\n", $ics_content );
        exit;
    }

    /**
     * Escape text for ICS format
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escape_ics( $text ) {
        $text = str_replace( array( "\\", ",", ";", "\n" ), array( "\\\\", "\\,", "\\;", "\\n" ), $text );
        return $text;
    }

    /**
     * Register admin menu pages
     */
    public function register_menu() {
        add_menu_page(
            __( 'Training Planner', 'training-planner' ),
            __( 'Training Planner', 'training-planner' ),
            'manage_options',
            'training-planner',
            array( $this, 'render_dashboard' ),
            'dashicons-calendar-alt',
            20
        );

        add_submenu_page(
            'training-planner',
            __( 'Monthly Planning', 'training-planner' ),
            __( 'Monthly Planning', 'training-planner' ),
            'manage_options',
            'training-planner-planning',
            array( $this, 'render_planning' )
        );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! isset( $_POST['tp_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['tp_action'] ) );

        if ( $action === 'generate_sessions' ) {
            check_admin_referer( 'tp_generate_sessions' );
            $year   = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : date( 'Y' );
            $month  = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : date( 'n' );
            $result = Training_Planner_Logic::generate_month_sessions( $year, $month );
            
            if ( $result ) {
                add_settings_error( 'tp_messages', 'tp_generated', __( 'Sessions generated successfully.', 'training-planner' ), 'updated' );
            } else {
                add_settings_error( 'tp_messages', 'tp_exists', __( 'Sessions already exist for this month. Please delete them first if you want to regenerate.', 'training-planner' ), 'error' );
            }
        } elseif ( $action === 'delete_session' ) {
            check_admin_referer( 'tp_delete_session' );
            global $wpdb;
            $table_sessions = $wpdb->prefix . 'training_sessions';
            $id             = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
            
            if ( $id > 0 ) {
                $wpdb->delete( $table_sessions, array( 'id' => $id ), array( '%d' ) );
                add_settings_error( 'tp_messages', 'tp_deleted', __( 'Session deleted.', 'training-planner' ), 'updated' );
            }
        } elseif ( $action === 'save_assignments' ) {
            check_admin_referer( 'tp_save_assignments' );
            global $wpdb;
            $table_sessions = $wpdb->prefix . 'training_sessions';
            $table_plans    = $wpdb->prefix . 'training_monthly_plans';
            
            $year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : date( 'Y' );
            $month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : date( 'n' );

            if ( isset( $_POST['trainer'] ) && is_array( $_POST['trainer'] ) ) {
                foreach ( $_POST['trainer'] as $session_id => $trainer_id ) {
                    $session_id = absint( $session_id );
                    $trainer_id = $trainer_id ? absint( $trainer_id ) : null;
                    
                    $wpdb->update(
                        $table_sessions,
                        array( 'assigned_trainer_id' => $trainer_id ),
                        array( 'id' => $session_id ),
                        array( '%d' ),
                        array( '%d' )
                    );
                }
            }
            
            if ( isset( $_POST['publish_plan'] ) ) {
                $wpdb->update(
                    $table_plans,
                    array( 'is_published' => 1 ),
                    array(
                        'year'  => $year,
                        'month' => $month,
                    ),
                    array( '%d' ),
                    array( '%d', '%d' )
                );
                add_settings_error( 'tp_messages', 'tp_published', __( 'Plan published.', 'training-planner' ), 'updated' );
            } else {
                add_settings_error( 'tp_messages', 'tp_saved', __( 'Assignments saved.', 'training-planner' ), 'updated' );
            }
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        
        // Simple list of upcoming sessions
        $sessions = $wpdb->get_results( 
            "SELECT * FROM $table_sessions WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 50" 
        );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Training Planner Dashboard', 'training-planner' ); ?></h1>
            <?php settings_errors( 'tp_messages' ); ?>
            
            <h2><?php esc_html_e( 'Upcoming Sessions', 'training-planner' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'training-planner' ); ?></th>
                        <th><?php esc_html_e( 'Time', 'training-planner' ); ?></th>
                        <th><?php esc_html_e( 'Topic', 'training-planner' ); ?></th>
                        <th><?php esc_html_e( 'Trainer', 'training-planner' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'training-planner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $sessions ) : ?>
                        <?php foreach ( $sessions as $session ) : 
                            $trainer      = $session->assigned_trainer_id ? get_userdata( $session->assigned_trainer_id ) : null;
                            $trainer_name = $trainer ? $trainer->display_name : '-';
                            $date_obj     = date_create( $session->date );
                            $weekday      = $date_obj ? date_i18n( 'l', $date_obj->getTimestamp() ) : '';
                        ?>
                            <tr>
                                <td><?php echo esc_html( $session->date ); ?> (<?php echo esc_html( $weekday ); ?>)</td>
                                <td><?php echo esc_html( substr( $session->time, 0, 5 ) ) . ' - ' . esc_html( substr( $session->end_time, 0, 5 ) ); ?></td>
                                <td><?php echo esc_html( $session->topic ); ?></td>
                                <td><?php echo esc_html( $trainer_name ); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="tp_action" value="delete_session">
                                        <input type="hidden" name="session_id" value="<?php echo absint( $session->id ); ?>">
                                        <?php wp_nonce_field( 'tp_delete_session' ); ?>
                                        <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'training-planner' ) ); ?>')"><?php esc_html_e( 'Delete', 'training-planner' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No upcoming sessions found.', 'training-planner' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render planning page
     */
    public function render_planning() {
        global $wpdb;
        $table_sessions     = $wpdb->prefix . 'training_sessions';
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_plans        = $wpdb->prefix . 'training_monthly_plans';

        $year  = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : date( 'Y' );
        $month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : date( 'n' );
        
        // Navigation
        $prev_month = $month - 1;
        $prev_year  = $year;
        if ( $prev_month < 1 ) {
            $prev_month = 12;
            $prev_year--;
        }
        
        $next_month = $month + 1;
        $next_year  = $year;
        if ( $next_month > 12 ) {
            $next_month = 1;
            $next_year++;
        }
        
        $plan = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM $table_plans WHERE year = %d AND month = %d", 
            $year,
            $month 
        ) );
        
        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d ORDER BY date ASC, time ASC", 
            $year,
            $month 
        ) );

        $all_users = get_users( array( 'orderby' => 'display_name' ) );

        $export_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=tp_export_ics&year=' . $year . '&month=' . $month ),
            'tp_export_ics'
        );

        ?>
        <div class="wrap">
            <h1><?php
                /* translators: %s: Month and year */
                echo esc_html( sprintf( __( 'Monthly Planning: %s', 'training-planner' ), date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ) ) );
            ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=training-planner-planning&year=<?php echo absint( $prev_year ); ?>&month=<?php echo absint( $prev_month ); ?>" class="button"><?php esc_html_e( 'Previous Month', 'training-planner' ); ?></a>
                    <a href="?page=training-planner-planning&year=<?php echo absint( $next_year ); ?>&month=<?php echo absint( $next_month ); ?>" class="button"><?php esc_html_e( 'Next Month', 'training-planner' ); ?></a>
                </div>
                <div class="alignright actions">
                     <form method="post" style="display:inline;">
                        <input type="hidden" name="tp_action" value="generate_sessions">
                        <input type="hidden" name="year" value="<?php echo absint( $year ); ?>">
                        <input type="hidden" name="month" value="<?php echo absint( $month ); ?>">
                        <?php wp_nonce_field( 'tp_generate_sessions' ); ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Sessions', 'training-planner' ); ?></button>
                    </form>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Export ICS', 'training-planner' ); ?></a>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="tp_action" value="save_assignments">
                <input type="hidden" name="year" value="<?php echo absint( $year ); ?>">
                <input type="hidden" name="month" value="<?php echo absint( $month ); ?>">
                <?php wp_nonce_field( 'tp_save_assignments' ); ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'training-planner' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'training-planner' ); ?></th>
                            <th><?php esc_html_e( 'Topic', 'training-planner' ); ?></th>
                            <th><?php esc_html_e( 'Assigned Trainer', 'training-planner' ); ?></th>
                            <th><?php esc_html_e( 'Availability', 'training-planner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $sessions ) : ?>
                            <?php foreach ( $sessions as $session ) : 
                                $date_obj = date_create( $session->date );
                                $weekday  = $date_obj ? date_i18n( 'l', $date_obj->getTimestamp() ) : '';
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $session->date ); ?> (<?php echo esc_html( $weekday ); ?>)</td>
                                    <td><?php echo esc_html( substr( $session->time, 0, 5 ) ); ?></td>
                                    <td><?php echo esc_html( $session->topic ); ?></td>
                                    <td>
                                        <select name="trainer[<?php echo absint( $session->id ); ?>]">
                                            <option value=""><?php esc_html_e( '-- Select Trainer --', 'training-planner' ); ?></option>
                                            <?php foreach ( $all_users as $user ) : ?>
                                                <option value="<?php echo absint( $user->ID ); ?>" <?php selected( $session->assigned_trainer_id, $user->ID ); ?>>
                                                    <?php echo esc_html( $user->display_name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php 
                                        // Show availability for this session
                                        $availabilities = $wpdb->get_results( $wpdb->prepare( 
                                            "SELECT user_id, status FROM $table_availability WHERE session_id = %d", 
                                            $session->id 
                                        ) );
                                        $avail_str = array();
                                        foreach ( $availabilities as $av ) {
                                            $u = get_userdata( $av->user_id );
                                            if ( $u ) {
                                                $color = 'black';
                                                if ( $av->status === 'Yes' ) {
                                                    $color = 'green';
                                                } elseif ( $av->status === 'Maybe' ) {
                                                    $color = 'orange';
                                                } elseif ( $av->status === 'No' ) {
                                                    $color = 'red';
                                                }
                                                
                                                $avail_str[] = sprintf(
                                                    '<span style="color:%s">%s (%s)</span>',
                                                    esc_attr( $color ),
                                                    esc_html( $u->display_name ),
                                                    esc_html( $av->status )
                                                );
                                            }
                                        }
                                        echo implode( ', ', $avail_str );
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5"><?php esc_html_e( 'No sessions generated for this month.', 'training-planner' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_assignments" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Assignments', 'training-planner' ); ?>">
                    <input type="submit" name="publish_plan" id="publish" class="button" value="<?php esc_attr_e( 'Publish Plan', 'training-planner' ); ?>" <?php echo ( $plan && $plan->is_published ) ? 'disabled' : ''; ?>>
                    <?php if ( $plan && $plan->is_published ) : ?>
                        <span class="description"><?php esc_html_e( 'Plan is published.', 'training-planner' ); ?></span>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }
}
