<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Training Planner Frontend Class
 * Handles frontend shortcode and user interactions
 */
class Training_Planner_Frontend {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( 'training_planner_dashboard', array( $this, 'render_dashboard' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_enqueue_style( 'tp-style', TRAINING_PLANNER_URL . 'assets/css/style.css', array(), TRAINING_PLANNER_VERSION );
    }

    /**
     * Render trainer dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_dashboard( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Bitte melden Sie sich an, um auf die Trainingsplanung zuzugreifen.', 'training-planner' ) . '</p>';
        }

        $user_id = get_current_user_id();
        $output  = '';

        // Handle Form Submission
        if ( isset( $_POST['tp_submit_availability'] ) && isset( $_POST['tp_nonce'] ) ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tp_nonce'] ) ), 'tp_submit_availability' ) ) {
                $this->handle_availability_submission( $user_id );
                $output .= '<div class="tp-message tp-success">' . esc_html__( 'Verfügbarkeit gespeichert.', 'training-planner' ) . '</div>';
            } else {
                $output .= '<div class="tp-message tp-error">' . esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'training-planner' ) . '</div>';
            }
        }

        // Get Parameters
        $year  = isset( $_GET['tp_year'] ) ? absint( $_GET['tp_year'] ) : date( 'Y' );
        $month = isset( $_GET['tp_month'] ) ? absint( $_GET['tp_month'] ) : date( 'n' );

        // Validate month
        if ( $month < 1 || $month > 12 ) {
            $month = date( 'n' );
        }

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
        
        // Get Data
        global $wpdb;
        $table_sessions     = $wpdb->prefix . 'training_sessions';
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_plans        = $wpdb->prefix . 'training_monthly_plans';

        $plan         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_plans WHERE year = %d AND month = %d", $year, $month ) );
        $is_published = $plan && $plan->is_published;

        $sessions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_sessions WHERE YEAR(date) = %d AND MONTH(date) = %d ORDER BY date ASC, time ASC", 
            $year,
            $month 
        ) );

        // Get User Availabilities
        $availabilities_raw = $wpdb->get_results( $wpdb->prepare( 
            "SELECT session_id, status, comment FROM $table_availability WHERE user_id = %d", 
            $user_id 
        ) );
        $user_availabilities = array();
        foreach ( $availabilities_raw as $av ) {
            $user_availabilities[ $av->session_id ] = $av;
        }

        // Build navigation URLs with proper escaping
        $current_url = remove_query_arg( array( 'tp_year', 'tp_month' ) );
        $prev_url    = add_query_arg( array( 'tp_year' => $prev_year, 'tp_month' => $prev_month ), $current_url );
        $next_url    = add_query_arg( array( 'tp_year' => $next_year, 'tp_month' => $next_month ), $current_url );

        // Render UI
        ob_start();
        ?>
        <div class="tp-dashboard">
            <div class="tp-controls">
                <a href="<?php echo esc_url( $prev_url ); ?>" class="tp-btn">&laquo; <?php esc_html_e( 'Zurück', 'training-planner' ); ?></a>
                <h2><?php echo esc_html( date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ) ); ?></h2>
                <a href="<?php echo esc_url( $next_url ); ?>" class="tp-btn"><?php esc_html_e( 'Weiter', 'training-planner' ); ?> &raquo;</a>
            </div>

            <?php if ( empty( $sessions ) ) : ?>
                <p><?php esc_html_e( 'Keine Trainingseinheiten für diesen Monat geplant.', 'training-planner' ); ?></p>
            <?php else : ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'tp_submit_availability', 'tp_nonce' ); ?>
                    <input type="hidden" name="tp_submit_availability" value="1">
                    <input type="hidden" name="tp_year" value="<?php echo absint( $year ); ?>">
                    <input type="hidden" name="tp_month" value="<?php echo absint( $month ); ?>">

                    <div class="tp-session-list">
                        <?php foreach ( $sessions as $session ) : 
                            $my_avail    = isset( $user_availabilities[ $session->id ] ) ? $user_availabilities[ $session->id ]->status : '';
                            $is_assigned = ( $session->assigned_trainer_id == $user_id );
                            $date_obj    = date_create( $session->date );
                            $weekday     = $date_obj ? date_i18n( 'l', $date_obj->getTimestamp() ) : '';
                            $date_str    = $date_obj ? date_i18n( 'd.m.Y', $date_obj->getTimestamp() ) : $session->date;
                        ?>
                            <div class="tp-session-card <?php echo $is_assigned ? 'tp-assigned' : ''; ?>">
                                <div class="tp-session-info">
                                    <h3><?php echo esc_html( $weekday . ', ' . $date_str ); ?></h3>
                                    <div class="tp-session-meta">
                                        <strong><?php esc_html_e( 'Zeit:', 'training-planner' ); ?></strong> 
                                        <?php echo esc_html( substr( $session->time, 0, 5 ) . ' - ' . substr( $session->end_time, 0, 5 ) ); ?><br>
                                        <strong><?php esc_html_e( 'Thema:', 'training-planner' ); ?></strong> 
                                        <?php echo esc_html( $session->topic ); ?>
                                    </div>
                                    <?php if ( $is_assigned ) : ?>
                                        <div class="tp-assigned-notice">
                                            <?php esc_html_e( 'Sie sind eingeteilt!', 'training-planner' ); ?>
                                            <?php if ( ! $session->assignment_confirmed ) : ?>
                                                <button type="submit" name="confirm_session_<?php echo absint( $session->id ); ?>" value="1" class="tp-btn tp-btn-confirm"><?php esc_html_e( 'Bestätigen', 'training-planner' ); ?></button>
                                            <?php else : ?>
                                                <span class="tp-confirmed"><?php esc_html_e( '(Bestätigt)', 'training-planner' ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="tp-availability-form">
                                    <label class="tp-radio-label">
                                        <input type="radio" name="status[<?php echo absint( $session->id ); ?>]" value="Yes" <?php checked( $my_avail, 'Yes' ); ?>>
                                        <span class="tp-status-yes"><?php esc_html_e( 'Ja', 'training-planner' ); ?></span>
                                    </label>
                                    <label class="tp-radio-label">
                                        <input type="radio" name="status[<?php echo absint( $session->id ); ?>]" value="Maybe" <?php checked( $my_avail, 'Maybe' ); ?>>
                                        <span class="tp-status-maybe"><?php esc_html_e( 'Vielleicht', 'training-planner' ); ?></span>
                                    </label>
                                    <label class="tp-radio-label">
                                        <input type="radio" name="status[<?php echo absint( $session->id ); ?>]" value="No" <?php checked( $my_avail, 'No' ); ?>>
                                        <span class="tp-status-no"><?php esc_html_e( 'Nein', 'training-planner' ); ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="tp-submit-wrapper">
                        <button type="submit" class="tp-btn tp-btn-primary"><?php esc_html_e( 'Speichern', 'training-planner' ); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
        $output .= ob_get_clean();
        return $output;
    }

    /**
     * Handle availability form submission
     *
     * @param int $user_id User ID
     */
    private function handle_availability_submission( $user_id ) {
        global $wpdb;
        $table_availability = $wpdb->prefix . 'training_availability';
        $table_sessions     = $wpdb->prefix . 'training_sessions';

        if ( isset( $_POST['status'] ) && is_array( $_POST['status'] ) ) {
            foreach ( $_POST['status'] as $session_id => $status ) {
                $session_id = absint( $session_id );
                $status     = sanitize_text_field( $status );
                
                // Validate status
                if ( ! in_array( $status, array( 'Yes', 'Maybe', 'No' ), true ) ) {
                    continue;
                }
                
                // Check if exists
                $exists = $wpdb->get_var( $wpdb->prepare( 
                    "SELECT id FROM $table_availability WHERE user_id = %d AND session_id = %d", 
                    $user_id,
                    $session_id 
                ) );

                if ( $exists ) {
                    $wpdb->update(
                        $table_availability,
                        array( 'status' => $status ),
                        array( 'id' => $exists ),
                        array( '%s' ),
                        array( '%d' )
                    );
                } else {
                    $wpdb->insert(
                        $table_availability,
                        array(
                            'user_id'    => $user_id,
                            'session_id' => $session_id,
                            'status'     => $status,
                        ),
                        array( '%d', '%d', '%s' )
                    );
                }
            }
        }
        
        // Handle confirmations
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'confirm_session_' ) === 0 ) {
                $session_id = absint( str_replace( 'confirm_session_', '', $key ) );
                
                // Verify user is assigned to this session
                $assigned = $wpdb->get_var( $wpdb->prepare(
                    "SELECT assigned_trainer_id FROM $table_sessions WHERE id = %d",
                    $session_id
                ) );
                
                if ( $assigned == $user_id ) {
                    $wpdb->update(
                        $table_sessions,
                        array( 'assignment_confirmed' => 1 ),
                        array(
                            'id'                  => $session_id,
                            'assigned_trainer_id' => $user_id,
                        ),
                        array( '%d' ),
                        array( '%d', '%d' )
                    );
                }
            }
        }
    }
}
