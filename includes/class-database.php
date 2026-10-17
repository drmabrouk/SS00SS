<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Database {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tbl_branches = $wpdb->prefix . 'vs_branches';
        $tbl_employees = $wpdb->prefix . 'vs_employees';
        $tbl_schedules = $wpdb->prefix . 'vs_schedules';
        $tbl_attendance = $wpdb->prefix . 'vs_attendance';
        $tbl_leave_requests = $wpdb->prefix . 'vs_leave_requests';
        $tbl_salary_configs = $wpdb->prefix . 'vs_salary_configs';
        $tbl_payroll_periods = $wpdb->prefix . 'vs_payroll_periods';
        $tbl_payroll_records = $wpdb->prefix . 'vs_payroll_records';
        $tbl_passkeys = $wpdb->prefix . 'vs_passkeys';
        $tbl_password_recoveries = $wpdb->prefix . 'vs_password_recoveries';
        $tbl_notifications = $wpdb->prefix . 'vs_notifications';
        $tbl_audit_logs = $wpdb->prefix . 'vs_audit_logs';

        $sql = array();

        $sql[] = "CREATE TABLE {$tbl_branches} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_code varchar(50) NOT NULL UNIQUE,
            name varchar(191) NOT NULL,
            address text DEFAULT NULL,
            latitude decimal(10, 8) NOT NULL,
            longitude decimal(11, 8) NOT NULL,
            geofence_radius int(10) UNSIGNED NOT NULL DEFAULT 100,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_employees} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL UNIQUE,
            employee_code varchar(50) NOT NULL UNIQUE,
            phone varchar(30) NOT NULL UNIQUE,
            branch_id bigint(20) UNSIGNED DEFAULT NULL,
            job_title varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            photo_url text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY branch_id (branch_id),
            KEY phone (phone),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_schedules} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            target_type varchar(20) NOT NULL, -- 'branch' or 'employee'
            target_id bigint(20) UNSIGNED NOT NULL,
            day_of_week tinyint(3) UNSIGNED NOT NULL, -- 0 (Sun) to 6 (Sat)
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            is_rest_day tinyint(1) NOT NULL DEFAULT 0,
            grace_period_minutes int(10) UNSIGNED NOT NULL DEFAULT 15,
            effective_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY target (target_type, target_id, day_of_week)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_attendance} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            branch_id bigint(20) UNSIGNED NOT NULL,
            attendance_date date NOT NULL,
            clock_in datetime DEFAULT NULL,
            clock_out datetime DEFAULT NULL,
            clock_in_lat decimal(10, 8) DEFAULT NULL,
            clock_in_lng decimal(11, 8) DEFAULT NULL,
            clock_in_accuracy decimal(8, 2) DEFAULT NULL,
            clock_in_distance decimal(10, 2) DEFAULT NULL,
            clock_out_lat decimal(10, 8) DEFAULT NULL,
            clock_out_lng decimal(11, 8) DEFAULT NULL,
            clock_out_accuracy decimal(8, 2) DEFAULT NULL,
            clock_out_distance decimal(10, 2) DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'present', -- present, late, early_departure, incomplete, absent, leave, rest_day
            is_manual_override tinyint(1) NOT NULL DEFAULT 0,
            override_reason text DEFAULT NULL,
            override_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY branch_id (branch_id),
            KEY attendance_date (attendance_date),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_leave_requests} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            leave_type varchar(50) NOT NULL, -- annual, sick, unpaid, exceptional
            start_date date NOT NULL,
            end_date date NOT NULL,
            reason text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending', -- pending, approved, rejected
            action_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_salary_configs} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL UNIQUE,
            basic_salary decimal(12, 2) NOT NULL DEFAULT 0.00,
            fixed_allowances decimal(12, 2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'AED',
            payment_frequency varchar(20) NOT NULL DEFAULT 'monthly',
            contractual_working_days int NOT NULL DEFAULT 22,
            contractual_daily_hours decimal(4, 2) NOT NULL DEFAULT 8.00,
            overtime_rate_multiplier decimal(4, 2) NOT NULL DEFAULT 1.25,
            effective_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_payroll_periods} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            period_name varchar(100) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft', -- draft, under_review, approved, paid, closed
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_payroll_records} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id bigint(20) UNSIGNED NOT NULL,
            employee_id bigint(20) UNSIGNED NOT NULL,
            basic_salary decimal(12, 2) NOT NULL,
            allowances decimal(12, 2) NOT NULL DEFAULT 0.00,
            overtime_pay decimal(12, 2) NOT NULL DEFAULT 0.00,
            bonuses decimal(12, 2) NOT NULL DEFAULT 0.00,
            lateness_deduction decimal(12, 2) NOT NULL DEFAULT 0.00,
            absence_deduction decimal(12, 2) NOT NULL DEFAULT 0.00,
            unpaid_leave_deduction decimal(12, 2) NOT NULL DEFAULT 0.00,
            other_deductions decimal(12, 2) NOT NULL DEFAULT 0.00,
            gross_earnings decimal(12, 2) NOT NULL,
            total_deductions decimal(12, 2) NOT NULL,
            net_salary decimal(12, 2) NOT NULL,
            amount_paid decimal(12, 2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY period_employee (period_id, employee_id),
            KEY employee_id (employee_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_passkeys} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            credential_id varchar(255) NOT NULL UNIQUE,
            public_key text NOT NULL,
            sign_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            user_handle varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_password_recoveries} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone varchar(30) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            code_hash varchar(255) NOT NULL,
            attempts int UNSIGNED NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            is_used tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY phone (phone),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_notifications} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(191) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            type varchar(50) NOT NULL DEFAULT 'info',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tbl_audit_logs} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) DEFAULT NULL,
            object_id bigint(20) UNSIGNED DEFAULT NULL,
            details text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('vs_db_version', VS_VERSION);
    }
}
