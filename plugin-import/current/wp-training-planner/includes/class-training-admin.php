<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Training_Planner_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_post_tp_export_ics', array( $this, 'handle_ics_export' ) );
        // Handler to export plugin as zip (admin_post)
        add_action( 'admin_post_tp_export_plugin_zip', array( $this, 'handle_export_zip' ) );
    }

    public function handle_ics_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'training-planner' ) );
        }

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
        );

        foreach ( $sessions as $session ) {
            $start_timestamp = strtotime( $session->date . ' ' . $session->time );
            $start_dt        = gmdate( 'Ymd\THis', $start_timestamp );

            if ( $session->end_time ) {
                $end_timestamp = strtotime( $session->date . ' ' . $session->end_time );
            } else {
                $end_timestamp = $start_timestamp + 7200;
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

    private function escape_ics( $text ) {
        return str_replace( array( "\\", ",", ";", "\n" ), array( "\\\\", "\\,", "\\;", "\\n" ), $text );
    }

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

    public function render_dashboard() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';

        $sessions = $wpdb->get_results( "SELECT * FROM $table_sessions WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 50" );

        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Plugin export', 'training-planner' ); ?></h2>
            <p><?php esc_html_e( 'Export the current plugin source as a downloadable ZIP (includes the plugin folder).', 'training-planner' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tp_export_plugin_zip', 'tp_export_plugin_zip_nonce' ); ?>
                <input type="hidden" name="action" value="tp_export_plugin_zip">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Download plugin ZIP', 'training-planner' ); ?></button>
            </form>
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
                                <td><!-- actions placeholder --></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No upcoming sessions', 'training-planner' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle exporting this plugin directory as a zip file
     */
    public function handle_export_zip() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'training-planner' ) );
        }

        if ( ! isset( $_POST['tp_export_plugin_zip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tp_export_plugin_zip_nonce'] ) ), 'tp_export_plugin_zip' ) ) {
            wp_die( esc_html__( 'Security check failed', 'training-planner' ) );
        }

        // Determine plugin root folder (one level up from includes/)
        $plugin_root = dirname( dirname( __FILE__ ) );
        $plugin_slug = basename( $plugin_root );

        // Build a zip filename including version if available
        $version = defined( 'TP_SRC_VERSION' ) ? TP_SRC_VERSION : 'dev';
        $zip_basename = sprintf( '%s-%s-%s.zip', $plugin_slug, sanitize_file_name( $version ), date( 'Ymd-His' ) );

        // Create temp file
        $tmpfile = wp_tempnam( $zip_basename );
        if ( ! $tmpfile ) {
            wp_die( esc_html__( 'Unable to create temporary file for export.', 'training-planner' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $tmpfile, ZipArchive::CREATE ) !== true ) {
            wp_die( esc_html__( 'Unable to create zip archive.', 'training-planner' ) );
        }

        // Recursively add files, preserving a top-level folder with plugin slug
        $root_in_zip = $plugin_slug;
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );

        foreach ( $iterator as $file ) {
            $filePath = $file->getPathname();
            // compute relative path inside plugin
            $relativePath = str_replace( $plugin_root . DIRECTORY_SEPARATOR, '', $filePath );
            $zipPath = $root_in_zip . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relativePath );

            if ( $file->isDir() ) {
                $zip->addEmptyDir( $zipPath );
            } else {
                $zip->addFile( $filePath, $zipPath );
            }
        }

        $zip->close();

        // Send ZIP to browser
        if ( file_exists( $tmpfile ) ) {
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . basename( $zip_basename ) . '"' );
            header( 'Content-Length: ' . filesize( $tmpfile ) );
            readfile( $tmpfile );
            // cleanup
            @unlink( $tmpfile );
            exit;
        }

        wp_die( esc_html__( 'Failed to generate plugin ZIP.', 'training-planner' ) );
    }

    public function render_planning() {
        // lightweight version for source copy; admin UI in target contains full functionality
        echo '<div class="wrap"><h1>' . esc_html__( 'Training Planner - Monthly Planning (source)', 'training-planner' ) . '</h1></div>';
    }
}
