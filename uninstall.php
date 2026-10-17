<?php
/**
 * Verification System Uninstall Procedure
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Data removal only happens if explicit confirmation option is set or requested.
// By default in specification: "Do not delete user data, attendance records, or existing pages when deactivated. Provide uninstall option with explicit confirmation before deleting plugin data."
if (get_option('vs_uninstall_confirm_delete_data', false)) {
    global $wpdb;

    $tables = array(
        'vs_branches',
        'vs_employees',
        'vs_schedules',
        'vs_attendance',
        'vs_leave_requests',
        'vs_salary_configs',
        'vs_payroll_periods',
        'vs_payroll_records',
        'vs_passkeys',
        'vs_password_recoveries',
        'vs_notifications',
        'vs_audit_logs'
    );

    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }

    // Delete options
    delete_option('vs_db_version');
    delete_option('vs_page_id');
    delete_option('vs_uninstall_confirm_delete_data');
    delete_option('vs_settings');
}
