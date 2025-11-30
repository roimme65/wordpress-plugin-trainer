<?php

class Training_Planner_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_post_tp_export_ics', array( $this, 'handle_ics_export' ) );
    }

    public function handle_ics_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $year = intval( $_GET['year'] );
        $month = intval( $_GET['month'] );
        
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        
        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d AND assigned_trainer_id IS NOT NULL", 
            $year, $month 
        ) );
        
        $ics_content = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//Trainingsplanung//DE",
            "METHOD:PUBLISH",
            "BEGIN:VTIMEZONE",
            "TZID:Europe/Berlin",
            // Simplified Timezone (In production, use a library or full definition)
            "END:VTIMEZONE"
        ];
        
        foreach ( $sessions as $session ) {
            $start_dt = date('Ymd\THis', strtotime("$session->date $session->time"));
            $end_dt = $session->end_time ? date('Ymd\THis', strtotime("$session->date $session->end_time")) : date('Ymd\THis', strtotime("$session->date $session->time") + 7200);
            
            $trainer = get_userdata($session->assigned_trainer_id);
            $trainer_name = $trainer ? $trainer->display_name : 'Unknown';
            
            $ics_content[] = "BEGIN:VEVENT";
            $ics_content[] = "UID:{$session->date}-{$session->id}@trainingsplanung";
            $ics_content[] = "DTSTAMP:" . date('Ymd\THis\Z');
            $ics_content[] = "DTSTART;TZID=Europe/Berlin:{$start_dt}";
            $ics_content[] = "DTEND;TZID=Europe/Berlin:{$end_dt}";
            $ics_content[] = "SUMMARY:Training: {$trainer_name}";
            $ics_content[] = "DESCRIPTION:Thema: {$session->topic}";
            $ics_content[] = "LOCATION:{$session->location}";
            $ics_content[] = "STATUS:CONFIRMED";
            $ics_content[] = "END:VEVENT";
        }
        
        $ics_content[] = "END:VCALENDAR";
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=training_plan_' . $year . '_' . $month . '.ics');
        
        echo implode("\r\n", $ics_content);
        exit;
    }

    public function register_menu() {
        add_menu_page(
            'Training Planner',
            'Training Planner',
            'manage_options',
            'training-planner',
            array( $this, 'render_dashboard' ),
            'dashicons-calendar-alt',
            20
        );

        add_submenu_page(
            'training-planner',
            'Monthly Planning',
            'Monthly Planning',
            'manage_options',
            'training-planner-planning',
            array( $this, 'render_planning' )
        );
    }

    public function handle_form_submissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['tp_action'] ) ) {
            if ( $_POST['tp_action'] == 'generate_sessions' ) {
                check_admin_referer( 'tp_generate_sessions' );
                $year = intval( $_POST['year'] );
                $month = intval( $_POST['month'] );
                $result = Training_Planner_Logic::generate_month_sessions( $year, $month );
                
                if ( $result ) {
                    add_settings_error( 'tp_messages', 'tp_generated', 'Sessions generated successfully.', 'updated' );
                } else {
                    add_settings_error( 'tp_messages', 'tp_exists', 'Sessions already exist for this month. Please delete them first if you want to regenerate.', 'error' );
                }
            }
            elseif ( $_POST['tp_action'] == 'delete_session' ) {
                check_admin_referer( 'tp_delete_session' );
                global $wpdb;
                $table_sessions = $wpdb->prefix . 'training_sessions';
                $id = intval( $_POST['session_id'] );
                $wpdb->delete( $table_sessions, array( 'id' => $id ) );
                add_settings_error( 'tp_messages', 'tp_deleted', 'Session deleted.', 'updated' );
            }
            elseif ( $_POST['tp_action'] == 'save_assignments' ) {
                check_admin_referer( 'tp_save_assignments' );
                global $wpdb;
                $table_sessions = $wpdb->prefix . 'training_sessions';
                $table_plans = $wpdb->prefix . 'training_monthly_plans';
                
                $year = intval($_POST['year']);
                $month = intval($_POST['month']);

                if ( isset( $_POST['trainer'] ) && is_array( $_POST['trainer'] ) ) {
                    foreach ( $_POST['trainer'] as $session_id => $trainer_id ) {
                        $trainer_id = $trainer_id ? intval( $trainer_id ) : null;
                        $wpdb->update(
                            $table_sessions,
                            array( 'assigned_trainer_id' => $trainer_id ),
                            array( 'id' => intval( $session_id ) )
                        );
                    }
                }
                
                if ( isset( $_POST['publish_plan'] ) ) {
                    $wpdb->update(
                        $table_plans,
                        array( 'is_published' => 1 ),
                        array( 'year' => $year, 'month' => $month )
                    );
                     add_settings_error( 'tp_messages', 'tp_published', 'Plan published.', 'updated' );
                } else {
                     add_settings_error( 'tp_messages', 'tp_saved', 'Assignments saved.', 'updated' );
                }
            }
        }
    }

    public function render_dashboard() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        
        // Simple list of upcoming sessions
        $sessions = $wpdb->get_results( "SELECT * FROM $table_sessions WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 50" );
        
        ?>
        <div class="wrap">
            <h1>Training Planner Dashboard</h1>
            <?php settings_errors( 'tp_messages' ); ?>
            
            <h2>Upcoming Sessions</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Topic</th>
                        <th>Trainer</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $sessions ) : ?>
                        <?php foreach ( $sessions as $session ) : 
                            $trainer = $session->assigned_trainer_id ? get_userdata( $session->assigned_trainer_id ) : null;
                            $trainer_name = $trainer ? $trainer->display_name : '-';
                        ?>
                            <tr>
                                <td><?php echo esc_html( $session->date ); ?> (<?php echo date('l', strtotime($session->date)); ?>)</td>
                                <td><?php echo esc_html( substr($session->time, 0, 5) ) . ' - ' . esc_html( substr($session->end_time, 0, 5) ); ?></td>
                                <td><?php echo esc_html( $session->topic ); ?></td>
                                <td><?php echo esc_html( $trainer_name ); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="tp_action" value="delete_session">
                                        <input type="hidden" name="session_id" value="<?php echo $session->id; ?>">
                                        <?php wp_nonce_field( 'tp_delete_session' ); ?>
                                        <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">No upcoming sessions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_planning() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_plans = $wpdb->prefix . 'training_monthly_plans';

        $year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : date( 'Y' );
        $month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : date( 'n' );
        
        // Navigation
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
        
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) { $next_month = 1; $next_year++; }
        
        $plan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_plans WHERE year = %d AND month = %d", $year, $month ) );
        
        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d ORDER BY date ASC, time ASC", 
            $year, $month 
        ) );

        $trainers = get_users( array( 'role__not_in' => array( 'administrator' ) ) ); // Assuming non-admins are trainers, or filter by role
        // Better: get all users who can be trainers. For now, let's just get all users.
        $all_users = get_users();

        ?>
        <div class="wrap">
            <h1>Monthly Planning: <?php echo date( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=training-planner-planning&year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="button">Previous Month</a>
                    <a href="?page=training-planner-planning&year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="button">Next Month</a>
                </div>
                <div class="alignright actions">
                     <form method="post" style="display:inline;">
                        <input type="hidden" name="tp_action" value="generate_sessions">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <input type="hidden" name="month" value="<?php echo $month; ?>">
                        <?php wp_nonce_field( 'tp_generate_sessions' ); ?>
                        <button type="submit" class="button button-primary">Generate Sessions</button>
                    </form>
                    <a href="<?php echo admin_url('admin-post.php?action=tp_export_ics&year=' . $year . '&month=' . $month); ?>" class="button">Export ICS</a>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="tp_action" value="save_assignments">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <?php wp_nonce_field( 'tp_save_assignments' ); ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Topic</th>
                            <th>Assigned Trainer</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $sessions ) : ?>
                            <?php foreach ( $sessions as $session ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $session->date ); ?> (<?php echo date('l', strtotime($session->date)); ?>)</td>
                                    <td><?php echo esc_html( substr($session->time, 0, 5) ); ?></td>
                                    <td><?php echo esc_html( $session->topic ); ?></td>
                                    <td>
                                        <select name="trainer[<?php echo $session->id; ?>]">
                                            <option value="">-- Select Trainer --</option>
                                            <?php foreach ( $all_users as $user ) : ?>
                                                <option value="<?php echo $user->ID; ?>" <?php selected( $session->assigned_trainer_id, $user->ID ); ?>>
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
                                        $avail_str = [];
                                        foreach ($availabilities as $av) {
                                            $u = get_userdata($av->user_id);
                                            if ($u) {
                                                $color = 'black';
                                                if ($av->status == 'Yes') $color = 'green';
                                                elseif ($av->status == 'Maybe') $color = 'orange';
                                                elseif ($av->status == 'No') $color = 'red';
                                                
                                                $avail_str[] = "<span style='color:$color'>" . esc_html($u->display_name) . " ({$av->status})</span>";
                                            }
                                        }
                                        echo implode(', ', $avail_str);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5">No sessions generated for this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_assignments" id="submit" class="button button-primary" value="Save Assignments">
                    <input type="submit" name="publish_plan" id="publish" class="button" value="Publish Plan" <?php echo ($plan && $plan->is_published) ? 'disabled' : ''; ?>>
                    <?php if ($plan && $plan->is_published): ?>
                        <span class="description">Plan is published.</span>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }
}
