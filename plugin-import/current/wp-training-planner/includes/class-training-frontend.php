<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Training_Planner_Frontend {
    public function __construct() {
        add_shortcode( 'training_planner_dashboard', array( $this, 'render_dashboard' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        // Use source constants if available
        if ( defined( 'TP_SRC_URL' ) && defined( 'TP_SRC_VERSION' ) ) {
            wp_enqueue_style( 'tp-style', TP_SRC_URL . 'assets/css/style.css', array(), TP_SRC_VERSION );
            return;
        }

        // Fallback: try original plugin constants
        if ( defined( 'TRAINING_PLANNER_URL' ) && defined( 'TRAINING_PLANNER_VERSION' ) ) {
            wp_enqueue_style( 'tp-style', TRAINING_PLANNER_URL . 'assets/css/style.css', array(), TRAINING_PLANNER_VERSION );
        }
    }

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

        // Parameters and data fetching omitted for brevity in source copy (use target plugin for full UI)
        $output .= '<div class="tp-dashboard"><p>' . esc_html__( 'Training Planner (source) - use the target plugin for full UI', 'training-planner' ) . '</p></div>';
        return $output;
    }

    private function handle_availability_submission( $user_id ) {
        // simplified; real logic lives in target plugin
        return;
    }
}
