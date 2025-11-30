<?php
/**
 * Plugin Name: Training Planner (Source)
 * Description: Source copy of the Training Planner plugin (development copy)
 * Version: 1.1-dev
 * Author: Antigravity
 * Text Domain: training-planner
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TP_SRC_PATH', plugin_dir_path( __FILE__ ) );
define( 'TP_SRC_URL', plugin_dir_url( __FILE__ ) );
define( 'TP_SRC_VERSION', '1.1-dev' );

require_once TP_SRC_PATH . 'includes/class-training-logic.php';
require_once TP_SRC_PATH . 'includes/class-training-admin.php';
require_once TP_SRC_PATH . 'includes/class-training-frontend.php';

// Activation: create required tables using dbDelta (robust SQL formatting)
register_activation_hook( __FILE__, 'tp_src_activate' );

function tp_src_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $prefix = $wpdb->prefix;

    $sql  = "CREATE TABLE IF NOT EXISTS `{$prefix}training_sessions` (\n";
    $sql .= "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\n";
    $sql .= "  `date` date NOT NULL,\n";
    $sql .= "  `time` time NOT NULL,\n";
    $sql .= "  `end_time` time DEFAULT NULL,\n";
    $sql .= "  `location` varchar(100) DEFAULT '',\n";
    $sql .= "  `topic` varchar(200) DEFAULT '',\n";
    $sql .= "  `assigned_trainer_id` bigint(20) unsigned DEFAULT NULL,\n";
    $sql .= "  `assignment_confirmed` tinyint(1) DEFAULT 0,\n";
    $sql .= "  PRIMARY KEY (`id`),\n";
    $sql .= "  KEY `date_time` (`date`,`time`),\n";
    $sql .= "  KEY `assigned_trainer_id` (`assigned_trainer_id`)\n";
    $sql .= ") $charset_collate;\n";

    $sql .= "CREATE TABLE IF NOT EXISTS `{$prefix}training_availability` (\n";
    $sql .= "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\n";
    $sql .= "  `user_id` bigint(20) unsigned NOT NULL,\n";
    $sql .= "  `session_id` mediumint(9) NOT NULL,\n";
    $sql .= "  `status` varchar(20) NOT NULL,\n";
    $sql .= "  `comment` varchar(200) DEFAULT '',\n";
    $sql .= "  PRIMARY KEY (`id`),\n";
    $sql .= "  KEY `user_id` (`user_id`),\n";
    $sql .= "  KEY `session_id` (`session_id`),\n";
    $sql .= "  UNIQUE KEY `user_session` (`user_id`,`session_id`)\n";
    $sql .= ") $charset_collate;\n";

    $sql .= "CREATE TABLE IF NOT EXISTS `{$prefix}training_survey_status` (\n";
    $sql .= "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\n";
    $sql .= "  `user_id` bigint(20) unsigned NOT NULL,\n";
    $sql .= "  `year` int(4) NOT NULL,\n";
    $sql .= "  `month` int(2) NOT NULL,\n";
    $sql .= "  `is_submitted` tinyint(1) DEFAULT 0,\n";
    $sql .= "  PRIMARY KEY (`id`),\n";
    $sql .= "  UNIQUE KEY `user_year_month` (`user_id`,`year`,`month`)\n";
    $sql .= ") $charset_collate;\n";

    $sql .= "CREATE TABLE IF NOT EXISTS `{$prefix}training_monthly_plans` (\n";
    $sql .= "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\n";
    $sql .= "  `year` int(4) NOT NULL,\n";
    $sql .= "  `month` int(2) NOT NULL,\n";
    $sql .= "  `is_published` tinyint(1) DEFAULT 0,\n";
    $sql .= "  PRIMARY KEY (`id`),\n";
    $sql .= "  UNIQUE KEY `year_month` (`year`,`month`)\n";
    $sql .= ") $charset_collate;\n";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // dbDelta expects properly formatted CREATE TABLE statements; pass combined SQL
    dbDelta( $sql );
}

// Optional: uninstall hook to remove tables when plugin is uninstalled
register_uninstall_hook( __FILE__, 'tp_src_uninstall' );

function tp_src_uninstall() {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $tables = array(
        "{$prefix}training_sessions",
        "{$prefix}training_availability",
        "{$prefix}training_survey_status",
        "{$prefix}training_monthly_plans",
    );

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `$table`" );
    }
}

// Initialize
add_action( 'plugins_loaded', 'tp_src_init' );

function tp_src_init() {
    load_plugin_textdomain( 'training-planner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    new Training_Planner_Admin();
    new Training_Planner_Frontend();
}
