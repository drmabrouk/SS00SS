<?php
/**
 * Plugin Name: Verification System
 * Plugin URI: https://example.com/verification-system
 * Description: WordPress Verification, Attendance, Payroll & Workforce Management System.
 * Version: 1.0.0
 * Author: Verification System Team
 * Author URI: https://example.com
 * Text Domain: verification-system
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VS_VERSION', '1.0.0');
define('VS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VS_PLUGIN_FILE', __FILE__);

require_once VS_PLUGIN_DIR . 'includes/class-plugin.php';

function vs_run_plugin() {
    $plugin = VS_Plugin::get_instance();
    $plugin->run();
}

register_activation_hook(__FILE__, array('VS_Installer', 'activate'));
register_deactivation_hook(__FILE__, array('VS_Installer', 'deactivate'));

vs_run_plugin();
