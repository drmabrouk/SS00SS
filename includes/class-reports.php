<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Reports {

    public static function init() {
        add_action('wp_ajax_vs_get_attendance_report', array(__CLASS__, 'ajax_get_attendance_report'));
        add_action('wp_ajax_vs_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));
    }

    public static function get_employee_compliance_stats($employee_id) {
        global $wpdb;
        $att_table = $wpdb->prefix . 'vs_attendance';

        $total_days = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$att_table} WHERE employee_id = %d AND status != 'leave' AND status != 'rest_day'", $employee_id));
        $present_days = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$att_table} WHERE employee_id = %d AND status IN ('present', 'late', 'early_departure', 'late_and_early_departure')", $employee_id));

        $compliance_pct = ($total_days > 0) ? round(($present_days / $total_days) * 100, 1) : 100.0;

        return array(
            'total_scheduled_days' => (int)$total_days,
            'attended_days' => (int)$present_days,
            'compliance_percentage' => $compliance_pct
        );
    }

    public static function ajax_get_attendance_report() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

        global $wpdb;
        $att_table = $wpdb->prefix . 'vs_attendance';
        $emp_table = $wpdb->prefix . 'vs_employees';
        $branch_table = $wpdb->prefix . 'vs_branches';

        if (VS_Permissions::is_employee()) {
            $emp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$emp_table} WHERE user_id = %d", $user_id));
            if (!$emp) {
                wp_send_json_success(array('attendance' => array()));
                return;
            }
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, b.name as branch_name FROM {$att_table} a LEFT JOIN {$branch_table} b ON a.branch_id = b.id WHERE a.employee_id = %d AND a.attendance_date BETWEEN %s AND %s ORDER BY a.attendance_date DESC",
                $emp->id,
                $start_date,
                $end_date
            ));
        } else {
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, e.employee_code, u.display_name, b.name as branch_name FROM {$att_table} a JOIN {$emp_table} e ON a.employee_id = e.id JOIN {$wpdb->users} u ON e.user_id = u.ID LEFT JOIN {$branch_table} b ON a.branch_id = b.id WHERE a.attendance_date BETWEEN %s AND %s ORDER BY a.attendance_date DESC",
                $start_date,
                $end_date
            ));
        }

        wp_send_json_success(array('attendance' => $records));
    }

    public static function ajax_get_dashboard_stats() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $att_table = $wpdb->prefix . 'vs_attendance';
        $branch_table = $wpdb->prefix . 'vs_branches';
        $leave_table = $wpdb->prefix . 'vs_leave_requests';

        $today = date('Y-m-d');

        $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$emp_table} WHERE status = 'active'");
        $present_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$att_table} WHERE attendance_date = %s AND clock_in IS NOT NULL", $today));
        $late_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$att_table} WHERE attendance_date = %s AND status LIKE '%%late%%'", $today));
        $pending_leave = $wpdb->get_var("SELECT COUNT(*) FROM {$leave_table} WHERE status = 'pending'");
        $total_branches = $wpdb->get_var("SELECT COUNT(*) FROM {$branch_table} WHERE status = 'active'");

        wp_send_json_success(array(
            'stats' => array(
                'total_employees' => (int)$total_employees,
                'present_today' => (int)$present_today,
                'late_today' => (int)$late_today,
                'pending_leave' => (int)$pending_leave,
                'total_branches' => (int)$total_branches
            )
        ));
    }
}

VS_Reports::init();
