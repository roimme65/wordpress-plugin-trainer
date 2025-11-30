<?php

class Training_Planner_Frontend {

    public function __construct() {
        add_shortcode( 'training_planner_dashboard', array( $this, 'render_dashboard' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'tp-style', TRAINING_PLANNER_URL . 'assets/css/style.css', array(), '1.0' );
    }

    public function render_dashboard( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Bitte melden Sie sich an, um auf die Trainingsplanung zuzugreifen.</p>';
        }

        $user_id = get_current_user_id();
        $output = '';

        // Handle Form Submission
        if ( isset( $_POST['tp_submit_availability'] ) && isset( $_POST['tp_nonce'] ) && wp_verify_nonce( $_POST['tp_nonce'], 'tp_submit_availability' ) ) {
            $this->handle_availability_submission( $user_id );
            $output .= '<div class="tp-message">Verfügbarkeit gespeichert.</div>';
        }

        // Get Parameters
        $year = isset( $_GET['tp_year'] ) ? intval( $_GET['tp_year'] ) : date( 'Y' );
        $month = isset( $_GET['tp_month'] ) ? intval( $_GET['tp_month'] ) : date( 'n' );

        // Navigation
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
        
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) { $next_month = 1; $next_year++; }
        
        // Get Data
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'training_sessions';
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_plans = $wpdb->prefix . 'training_monthly_plans';

        $plan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_plans WHERE year = %d AND month = %d", $year, $month ) );
        $is_published = $plan && $plan->is_published;

        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d ORDER BY date ASC, time ASC", 
            $year, $month 
        ) );

        // Get User Availabilities
        $availabilities_raw = $wpdb->get_results( $wpdb->prepare( 
            "SELECT session_id, status, comment FROM $table_availability WHERE user_id = %d", 
            $user_id 
        ) );
        $user_availabilities = [];
        foreach ( $availabilities_raw as $av ) {
            $user_availabilities[$av->session_id] = $av;
        }

        // Render UI
        ob_start();
        ?>
        <div class="tp-dashboard">
            <div class="tp-controls">
                <a href="?tp_year=<?php echo $prev_year; ?>&tp_month=<?php echo $prev_month; ?>" class="tp-btn">&laquo; Zurück</a>
                <h2><?php echo date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ); ?></h2>
                <a href="?tp_year=<?php echo $next_year; ?>&tp_month=<?php echo $next_month; ?>" class="tp-btn">Weiter &raquo;</a>
            </div>

            <?php if ( empty( $sessions ) ) : ?>
                <p>Keine Trainingseinheiten für diesen Monat geplant.</p>
            <?php else : ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'tp_submit_availability', 'tp_nonce' ); ?>
                    <input type="hidden" name="tp_submit_availability" value="1">
                    <input type="hidden" name="tp_year" value="<?php echo $year; ?>">
                    <input type="hidden" name="tp_month" value="<?php echo $month; ?>">

                    <div class="tp-session-list">
                        <?php foreach ( $sessions as $session ) : 
                            $my_avail = isset( $user_availabilities[$session->id] ) ? $user_availabilities[$session->id]->status : '';
                            $is_assigned = ($session->assigned_trainer_id == $user_id);
                        ?>
                            <div class="tp-session-card" style="<?php echo $is_assigned ? 'border-color: #46b450; border-width: 2px;' : ''; ?>">
                                <div class="tp-session-info">
                                    <h3><?php echo date_i18n( 'l, d.m.Y', strtotime( $session->date ) ); ?></h3>
                                    <div class="tp-session-meta">
                                        <?php echo substr($session->time, 0, 5) . ' - ' . substr($session->end_time, 0, 5); ?><br>
                                        <?php echo esc_html( $session->topic ); ?>
                                    </div>
                                    <?php if ( $is_assigned ) : ?>
                                        <div style="margin-top: 10px; color: #46b450; font-weight: bold;">
                                            Sie sind eingeteilt!
                                            <?php if ( ! $session->assignment_confirmed ) : ?>
                                                <button type="submit" name="confirm_session_<?php echo $session->id; ?>" value="1" class="button button-small">Bestätigen</button>
                                            <?php else: ?>
                                                (Bestätigt)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="tp-availability-form">
                                    <label><input type="radio" name="status[<?php echo $session->id; ?>]" value="Yes" <?php checked( $my_avail, 'Yes' ); ?>> Ja</label>
                                    <label><input type="radio" name="status[<?php echo $session->id; ?>]" value="Maybe" <?php checked( $my_avail, 'Maybe' ); ?>> Vielleicht</label>
                                    <label><input type="radio" name="status[<?php echo $session->id; ?>]" value="No" <?php checked( $my_avail, 'No' ); ?>> Nein</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="tp-btn tp-btn-yes active">Speichern</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
        $output .= ob_get_clean();
        return $output;
    }

    private function handle_availability_submission( $user_id ) {
        global $wpdb;
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_sessions = $wpdb->prefix . 'training_sessions';

        if ( isset( $_POST['status'] ) && is_array( $_POST['status'] ) ) {
            foreach ( $_POST['status'] as $session_id => $status ) {
                $session_id = intval( $session_id );
                $status = sanitize_text_field( $status );
                
                // Check if exists
                $exists = $wpdb->get_var( $wpdb->prepare( 
                    "SELECT id FROM $table_availability WHERE user_id = %d AND session_id = %d", 
                    $user_id, $session_id 
                ) );

                if ( $exists ) {
                    $wpdb->update(
                        $table_availability,
                        array( 'status' => $status ),
                        array( 'id' => $exists )
                    );
                } else {
                    $wpdb->insert(
                        $table_availability,
                        array(
                            'user_id' => $user_id,
                            'session_id' => $session_id,
                            'status' => $status
                        )
                    );
                }
            }
        }
        
        // Handle confirmations embedded in the form (a bit hacky but works for single button presses if we check keys)
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'confirm_session_' ) === 0 ) {
                $session_id = intval( str_replace( 'confirm_session_', '', $key ) );
                $wpdb->update(
                    $table_sessions,
                    array( 'assignment_confirmed' => 1 ),
                    array( 'id' => $session_id, 'assigned_trainer_id' => $user_id )
                );
            }
        }
    }

    private function handle_confirmation( $user_id ) {
        // Separate handler if needed, but currently handled in handle_availability_submission for simplicity
    }
}
