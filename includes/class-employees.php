<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Employees {

    public static function init() {
        add_action('wp_ajax_vs_get_employee_profile', array(__CLASS__, 'ajax_get_profile'));
        add_action('wp_ajax_vs_update_employee_profile', array(__CLASS__, 'ajax_update_profile'));
    }

    public static function ajax_get_profile() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $emp_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        $current_user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $branch_table = $wpdb->prefix . 'vs_branches';
        $sal_table = $wpdb->prefix . 'vs_salary_configs';

        if ($emp_id) {
            $employee = $wpdb->get_row($wpdb->prepare("SELECT e.*, u.display_name, u.user_email, b.name as branch_name FROM {$emp_table} e JOIN {$wpdb->users} u ON e.user_id = u.ID LEFT JOIN {$branch_table} b ON e.branch_id = b.id WHERE e.id = %d", $emp_id));
        } else {
            $employee = $wpdb->get_row($wpdb->prepare("SELECT e.*, u.display_name, u.user_email, b.name as branch_name FROM {$emp_table} e JOIN {$wpdb->users} u ON e.user_id = u.ID LEFT JOIN {$branch_table} b ON e.branch_id = b.id WHERE e.user_id = %d", $current_user_id));
        }

        if (!$employee) {
            wp_send_json_error(array('message' => __('Employee profile not found.', 'verification-system')));
        }

        // Permissions check
        if (!VS_Permissions::can_manage_user($employee->user_id, $current_user_id) && $employee->user_id !== $current_user_id) {
            wp_send_json_error(array('message' => __('Unauthorized access to employee profile.', 'verification-system')), 403);
        }

        // Include salary config if authorized
        $salary = null;
        if (VS_Permissions::is_admin() || VS_Permissions::is_general_manager() || $employee->user_id === $current_user_id) {
            $salary = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sal_table} WHERE employee_id = %d", $employee->id));
        }

        // Calculate compliance stats
        $stats = VS_Reports::get_employee_compliance_stats($employee->id);

        wp_send_json_success(array(
            'profile' => $employee,
            'salary' => $salary,
            'stats' => $stats
        ));
    }

    public static function ajax_update_profile() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $emp_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
        $current_user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';

        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$emp_table} WHERE id = %d", $emp_id));

        if (!$employee) {
            wp_send_json_error(array('message' => __('Employee profile not found.', 'verification-system')));
        }

        if (!VS_Permissions::can_manage_user($employee->user_id, $current_user_id) && $employee->user_id !== $current_user_id) {
            wp_send_json_error(array('message' => __('Unauthorized to update this profile.', 'verification-system')), 403);
        }

        $job_title = isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : $employee->job_title;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : $employee->status;
        $photo_url = isset($_POST['photo_url']) ? esc_url_raw($_POST['photo_url']) : $employee->photo_url;

        $wpdb->update($emp_table, array(
            'job_title' => $job_title,
            'status' => $status,
            'photo_url' => $photo_url,
            'updated_at' => current_time('mysql')
        ), array('id' => $employee->id));

        VS_Audit_Logs::log('employee_profile_updated', 'employee', $employee->id);

        wp_send_json_success(array('message' => __('Profile updated successfully.', 'verification-system')));
    }
}

VS_Employees::init();
