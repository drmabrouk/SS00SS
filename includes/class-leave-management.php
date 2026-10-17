<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Leave_Management {

    public static function init() {
        add_action('wp_ajax_vs_submit_leave_request', array(__CLASS__, 'ajax_submit_leave_request'));
        add_action('wp_ajax_vs_review_leave_request', array(__CLASS__, 'ajax_review_leave_request'));
        add_action('wp_ajax_vs_get_leave_requests', array(__CLASS__, 'ajax_get_leave_requests'));
    }

    public static function ajax_submit_leave_request() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $leave_table = $wpdb->prefix . 'vs_leave_requests';

        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$emp_table} WHERE user_id = %d AND status = 'active'", $user_id));

        if (!$employee) {
            wp_send_json_error(array('message' => __('Active employee profile not found.', 'verification-system')));
        }

        $leave_type = isset($_POST['leave_type']) ? sanitize_text_field($_POST['leave_type']) : 'annual';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => __('Start date and end date are required.', 'verification-system')));
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            wp_send_json_error(array('message' => __('Start date cannot be after end date.', 'verification-system')));
        }

        $wpdb->insert($leave_table, array(
            'employee_id' => $employee->id,
            'leave_type' => $leave_type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));

        $req_id = $wpdb->insert_id;

        VS_Audit_Logs::log('leave_request_submitted', 'leave_request', $req_id, array(
            'employee_id' => $employee->id,
            'type' => $leave_type,
            'start' => $start_date,
            'end' => $end_date
        ));

        wp_send_json_success(array('message' => __('Leave request submitted successfully.', 'verification-system')));
    }

    public static function ajax_review_leave_request() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager() && !VS_Permissions::is_branch_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to review leave requests.', 'verification-system')), 403);
        }

        $req_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'approved'; // approved or rejected

        if (!$req_id || !in_array($status, array('approved', 'rejected'), true)) {
            wp_send_json_error(array('message' => __('Invalid request parameters.', 'verification-system')));
        }

        global $wpdb;
        $leave_table = $wpdb->prefix . 'vs_leave_requests';
        $att_table = $wpdb->prefix . 'vs_attendance';

        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$leave_table} WHERE id = %d", $req_id));

        if (!$req) {
            wp_send_json_error(array('message' => __('Leave request not found.', 'verification-system')));
        }

        $wpdb->update($leave_table, array(
            'status' => $status,
            'action_by' => get_current_user_id()
        ), array('id' => $req_id));

        // If approved, update or insert attendance records for the leave period
        if ($status === 'approved') {
            $curr = strtotime($req->start_date);
            $last = strtotime($req->end_date);

            $emp_table = $wpdb->prefix . 'vs_employees';
            $branch_id = $wpdb->get_var($wpdb->prepare("SELECT branch_id FROM {$emp_table} WHERE id = %d", $req->employee_id));

            while ($curr <= $last) {
                $d = date('Y-m-d', $curr);

                $att_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$att_table} WHERE employee_id = %d AND attendance_date = %s", $req->employee_id, $d));
                if ($att_id) {
                    $wpdb->update($att_table, array('status' => 'leave'), array('id' => $att_id));
                } else {
                    $wpdb->insert($att_table, array(
                        'employee_id' => $req->employee_id,
                        'branch_id' => $branch_id ? $branch_id : 0,
                        'attendance_date' => $d,
                        'status' => 'leave',
                        'created_at' => current_time('mysql')
                    ));
                }

                $curr = strtotime('+1 day', $curr);
            }
        }

        VS_Audit_Logs::log('leave_request_reviewed', 'leave_request', $req_id, array(
            'status' => $status,
            'reviewer' => get_current_user_id()
        ));

        wp_send_json_success(array('message' => sprintf(__('Leave request %s.', 'verification-system'), $status)));
    }

    public static function ajax_get_leave_requests() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        global $wpdb;
        $leave_table = $wpdb->prefix . 'vs_leave_requests';
        $emp_table = $wpdb->prefix . 'vs_employees';
        $user_id = get_current_user_id();

        if (VS_Permissions::is_employee()) {
            $emp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$emp_table} WHERE user_id = %d", $user_id));
            if (!$emp) {
                wp_send_json_success(array('requests' => array()));
                return;
            }
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$leave_table} WHERE employee_id = %d ORDER BY id DESC", $emp->id));
        } else {
            $results = $wpdb->get_results("SELECT l.*, e.employee_code, e.job_title FROM {$leave_table} l JOIN {$emp_table} e ON l.employee_id = e.id ORDER BY l.id DESC");
        }

        wp_send_json_success(array('requests' => $results));
    }
}

VS_Leave_Management::init();
