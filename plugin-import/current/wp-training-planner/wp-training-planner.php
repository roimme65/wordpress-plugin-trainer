<?php
/**
 * Plugin Name: Training Planner
 * Description: A tool for planning training sessions and managing trainer availability.
 * Version: 1.0
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'TRAINING_PLANNER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRAINING_PLANNER_URL', plugin_dir_url( __FILE__ ) );

// Include core classes
require_once TRAINING_PLANNER_PATH . 'includes/class-training-logic.php';
require_once TRAINING_PLANNER_PATH . 'includes/class-training-admin.php';
require_once TRAINING_PLANNER_PATH . 'includes/class-training-frontend.php';

// Activation Hook
register_activation_hook( __FILE__, 'training_planner_activate' );

function training_planner_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_sessions = $wpdb->prefix . 'training_sessions';
    $table_availability = $wpdb->prefix . 'training_availability';
    $table_survey = $wpdb->prefix . 'training_survey_status';
    $table_plans = $wpdb->prefix . 'training_monthly_plans';

    $sql_sessions = "CREATE TABLE $table_sessions (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date date NOT NULL,
        time time NOT NULL,
        end_time time DEFAULT NULL,
        location varchar(100) DEFAULT '',
        topic varchar(200) DEFAULT '',
        assigned_trainer_id bigint(20) unsigned DEFAULT NULL,
        assignment_confirmed tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_availability = "CREATE TABLE $table_availability (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        session_id mediumint(9) NOT NULL,
        status varchar(20) NOT NULL,
        comment varchar(200) DEFAULT '',
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY session_id (session_id)
    ) $charset_collate;";

    $sql_survey = "CREATE TABLE $table_survey (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        year int(4) NOT NULL,
        month int(2) NOT NULL,
        is_submitted tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY user_year_month (user_id, year, month)
    ) $charset_collate;";

    $sql_plans = "CREATE TABLE $table_plans (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        year int(4) NOT NULL,
        month int(2) NOT NULL,
        is_published tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY year_month (year, month)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sessions );
    dbDelta( $sql_availability );
    dbDelta( $sql_survey );
    dbDelta( $sql_plans );
}

// Initialize classes
function training_planner_init() {
    new Training_Planner_Admin();
    new Training_Planner_Frontend();
}
add_action( 'plugins_loaded', 'training_planner_init' );
