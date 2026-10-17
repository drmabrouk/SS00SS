<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Attendance {

    public static function init() {
        add_action('wp_ajax_vs_clock_in', array(__CLASS__, 'ajax_clock_in'));
        add_action('wp_ajax_vs_clock_out', array(__CLASS__, 'ajax_clock_out'));
        add_action('wp_ajax_vs_get_today_attendance', array(__CLASS__, 'ajax_get_today_attendance'));
        add_action('wp_ajax_vs_override_attendance', array(__CLASS__, 'ajax_override_attendance'));
    }

    public static function ajax_clock_in() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $branch_table = $wpdb->prefix . 'vs_branches';
        $att_table = $wpdb->prefix . 'vs_attendance';

        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$emp_table} WHERE user_id = %d AND status = 'active'", $user_id));

        if (!$employee) {
            wp_send_json_error(array('message' => __('Active employee profile not found.', 'verification-system')));
        }

        if (!$employee->branch_id) {
            wp_send_json_error(array('message' => __('You are not assigned to any branch.', 'verification-system')));
        }

        $branch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$branch_table} WHERE id = %d AND status = 'active'", $employee->branch_id));

        if (!$branch) {
            wp_send_json_error(array('message' => __('Assigned branch is inactive or not found.', 'verification-system')));
        }

        $lat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $accuracy = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : 0.0;

        // Verify Location
        $loc_check = VS_Geolocation::verify_location($lat, $lng, $accuracy, $branch->latitude, $branch->longitude, $branch->geofence_radius);

        if (!$loc_check['valid']) {
            VS_Audit_Logs::log('clock_in_failed_location', 'employee', $employee->id, array(
                'reason' => $loc_check['message'],
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $accuracy
            ));
            wp_send_json_error(array('message' => $loc_check['message']));
        }

        $today = date('Y-m-d');
        $now = current_time('mysql');

        // Check if employee already has active attendance session or clocked in today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$att_table} WHERE employee_id = %d AND attendance_date = %s",
            $employee->id,
            $today
        ));

        if ($existing && !empty($existing->clock_in) && empty($existing->clock_out)) {
            wp_send_json_error(array('message' => __('You are already clocked in for today.', 'verification-system')));
        }

        if ($existing && !empty($existing->clock_in) && !empty($existing->clock_out)) {
            wp_send_json_error(array('message' => __('You have already completed your clock-in and clock-out for today.', 'verification-system')));
        }

        // Determine schedule status (on-time or late)
        $status = VS_Scheduling::evaluate_clock_in_status($employee->id, $branch->id, $today, $now);

        if ($existing) {
            $wpdb->update($att_table, array(
                'clock_in' => $now,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_accuracy' => $accuracy,
                'clock_in_distance' => $loc_check['distance'],
                'status' => $status,
                'updated_at' => $now
            ), array('id' => $existing->id));
            $att_id = $existing->id;
        } else {
            $wpdb->insert($att_table, array(
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'attendance_date' => $today,
                'clock_in' => $now,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_accuracy' => $accuracy,
                'clock_in_distance' => $loc_check['distance'],
                'status' => $status,
                'created_at' => $now
            ));
            $att_id = $wpdb->insert_id;
        }

        VS_Audit_Logs::log('clock_in_success', 'attendance', $att_id, array(
            'employee_id' => $employee->id,
            'time' => $now,
            'status' => $status
        ));

        wp_send_json_success(array(
            'message' => __('Clocked in successfully!', 'verification-system'),
            'clock_in_time' => date('H:i:s', strtotime($now)),
            'status' => $status
        ));
    }

    public static function ajax_clock_out() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $branch_table = $wpdb->prefix . 'vs_branches';
        $att_table = $wpdb->prefix . 'vs_attendance';

        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$emp_table} WHERE user_id = %d AND status = 'active'", $user_id));

        if (!$employee) {
            wp_send_json_error(array('message' => __('Active employee profile not found.', 'verification-system')));
        }

        $today = date('Y-m-d');
        $now = current_time('mysql');

        $attendance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$att_table} WHERE employee_id = %d AND attendance_date = %s AND clock_in IS NOT NULL",
            $employee->id,
            $today
        ));

        if (!$attendance) {
            wp_send_json_error(array('message' => __('No active clock-in session found for today.', 'verification-system')));
        }

        if (!empty($attendance->clock_out)) {
            wp_send_json_error(array('message' => __('You have already clocked out today.', 'verification-system')));
        }

        $branch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$branch_table} WHERE id = %d", $attendance->branch_id));

        $lat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $accuracy = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : 0.0;

        $distance = null;
        if ($branch && $lat && $lng) {
            $loc_check = VS_Geolocation::verify_location($lat, $lng, $accuracy, $branch->latitude, $branch->longitude, $branch->geofence_radius);
            $distance = $loc_check['distance'];
        }

        // Determine updated status (checking early departure)
        $status = VS_Scheduling::evaluate_clock_out_status($employee->id, $attendance->branch_id, $today, $attendance->clock_in, $now, $attendance->status);

        $wpdb->update($att_table, array(
            'clock_out' => $now,
            'clock_out_lat' => $lat,
            'clock_out_lng' => $lng,
            'clock_out_accuracy' => $accuracy,
            'clock_out_distance' => $distance,
            'status' => $status,
            'updated_at' => $now
        ), array('id' => $attendance->id));

        // Duration calculation
        $worked_seconds = strtotime($now) - strtotime($attendance->clock_in);
        $hours = floor($worked_seconds / 3600);
        $minutes = floor(($worked_seconds % 3600) / 60);

        VS_Audit_Logs::log('clock_out_success', 'attendance', $attendance->id, array(
            'employee_id' => $employee->id,
            'time' => $now,
            'worked_hours' => round($worked_seconds / 3600, 2)
        ));

        wp_send_json_success(array(
            'message' => __('Clocked out successfully!', 'verification-system'),
            'clock_out_time' => date('H:i:s', strtotime($now)),
            'worked_duration' => sprintf('%dh %02dm', $hours, $minutes),
            'status' => $status
        ));
    }

    public static function ajax_get_today_attendance() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $att_table = $wpdb->prefix . 'vs_attendance';

        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$emp_table} WHERE user_id = %d", $user_id));

        if (!$employee) {
            wp_send_json_error(array('message' => __('Employee profile not found.', 'verification-system')));
        }

        $today = date('Y-m-d');
        $attendance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$att_table} WHERE employee_id = %d AND attendance_date = %s",
            $employee->id,
            $today
        ));

        wp_send_json_success(array(
            'attendance' => $attendance,
            'today' => $today
        ));
    }

    public static function ajax_override_attendance() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager() && !VS_Permissions::is_branch_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to override attendance.', 'verification-system')), 403);
        }

        $att_id = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
        $clock_in = isset($_POST['clock_in']) ? sanitize_text_field($_POST['clock_in']) : null;
        $clock_out = isset($_POST['clock_out']) ? sanitize_text_field($_POST['clock_out']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'present';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        if (!$att_id || empty($reason)) {
            wp_send_json_error(array('message' => __('Attendance ID and reason are required.', 'verification-system')));
        }

        global $wpdb;
        $att_table = $wpdb->prefix . 'vs_attendance';
        $emp_table = $wpdb->prefix . 'vs_employees';

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$att_table} WHERE id = %d", $att_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Attendance record not found.', 'verification-system')));
        }

        $target_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$emp_table} WHERE id = %d", $existing->employee_id));

        if (!$target_user_id || !VS_Permissions::can_manage_user($target_user_id)) {
            wp_send_json_error(array('message' => __('Unauthorized to modify this employee\'s attendance.', 'verification-system')), 403);
        }

        $wpdb->update($att_table, array(
            'clock_in' => $clock_in ? $clock_in : $existing->clock_in,
            'clock_out' => $clock_out ? $clock_out : $existing->clock_out,
            'status' => $status,
            'is_manual_override' => 1,
            'override_reason' => $reason,
            'override_by' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        ), array('id' => $att_id));

        VS_Audit_Logs::log('attendance_manual_override', 'attendance', $att_id, array(
            'reason' => $reason,
            'new_status' => $status,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out
        ));

        wp_send_json_success(array('message' => __('Attendance record updated successfully.', 'verification-system')));
    }
}

VS_Attendance::init();
