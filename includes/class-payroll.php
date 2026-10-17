<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Payroll {

    public static function init() {
        add_action('wp_ajax_vs_calculate_payroll_period', array(__CLASS__, 'ajax_calculate_payroll_period'));
        add_action('wp_ajax_vs_approve_payroll_period', array(__CLASS__, 'ajax_approve_payroll_period'));
        add_action('wp_ajax_vs_get_payroll_statement', array(__CLASS__, 'ajax_get_payroll_statement'));
        add_action('wp_ajax_vs_export_payroll_csv', array(__CLASS__, 'ajax_export_payroll_csv'));
    }

    /**
     * Calculate decimal-precise payroll for a given employee and date range.
     */
    public static function calculate_employee_payroll($employee_id, $start_date, $end_date) {
        global $wpdb;

        $sal_table = $wpdb->prefix . 'vs_salary_configs';
        $att_table = $wpdb->prefix . 'vs_attendance';

        $sal_cfg = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sal_table} WHERE employee_id = %d", $employee_id));

        if (!$sal_cfg) {
            // Default salary configuration if none explicit
            $sal_cfg = (object) array(
                'basic_salary' => 5000.00,
                'fixed_allowances' => 1000.00,
                'currency' => 'AED',
                'contractual_working_days' => 22,
                'contractual_daily_hours' => 8.00,
                'overtime_rate_multiplier' => 1.25
            );
        }

        $basic = (float)$sal_cfg->basic_salary;
        $allowances = (float)$sal_cfg->fixed_allowances;
        $working_days_in_month = (int)$sal_cfg->contractual_working_days > 0 ? (int)$sal_cfg->contractual_working_days : 22;
        $daily_hours = (float)$sal_cfg->contractual_daily_hours > 0 ? (float)$sal_cfg->contractual_daily_hours : 8.00;

        $daily_rate = $basic / $working_days_in_month;
        $hourly_rate = $daily_rate / $daily_hours;

        // Fetch attendance records for period
        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$att_table} WHERE employee_id = %d AND attendance_date BETWEEN %s AND %s",
            $employee_id,
            $start_date,
            $end_date
        ));

        $lateness_count = 0;
        $absence_count = 0;
        $unpaid_leave_count = 0;
        $early_departure_count = 0;
        $total_worked_hours = 0.0;
        $overtime_hours = 0.0;

        foreach ($attendance as $att) {
            if ($att->status === 'late' || $att->status === 'late_and_early_departure') {
                $lateness_count++;
            }
            if ($att->status === 'early_departure' || $att->status === 'late_and_early_departure') {
                $early_departure_count++;
            }
            if ($att->status === 'absent') {
                $absence_count++;
            }
            if ($att->status === 'unpaid_leave') {
                $unpaid_leave_count++;
            }

            if (!empty($att->clock_in) && !empty($att->clock_out)) {
                $duration = (strtotime($att->clock_out) - strtotime($att->clock_in)) / 3600;
                $total_worked_hours += $duration;
                if ($duration > $daily_hours) {
                    $overtime_hours += ($duration - $daily_hours);
                }
            }
        }

        // Deductions calculation rules
        // Lateness deduction: 0.25 of hourly rate per late instance (configurable rule)
        $lateness_deduction = round($lateness_count * ($hourly_rate * 0.5), 2);
        $early_departure_deduction = round($early_departure_count * ($hourly_rate * 0.5), 2);
        $absence_deduction = round($absence_count * $daily_rate, 2);
        $unpaid_leave_deduction = round($unpaid_leave_count * $daily_rate, 2);

        $overtime_mult = (float)$sal_cfg->overtime_rate_multiplier > 0 ? (float)$sal_cfg->overtime_rate_multiplier : 1.25;
        $overtime_pay = round($overtime_hours * $hourly_rate * $overtime_mult, 2);

        $gross_earnings = round($basic + $allowances + $overtime_pay, 2);
        $total_deductions = round($lateness_deduction + $early_departure_deduction + $absence_deduction + $unpaid_leave_deduction, 2);
        $net_salary = round($gross_earnings - $total_deductions, 2);

        return array(
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'overtime_pay' => $overtime_pay,
            'bonuses' => 0.00,
            'lateness_deduction' => $lateness_deduction + $early_departure_deduction,
            'absence_deduction' => $absence_deduction,
            'unpaid_leave_deduction' => $unpaid_leave_deduction,
            'other_deductions' => 0.00,
            'gross_earnings' => $gross_earnings,
            'total_deductions' => $total_deductions,
            'net_salary' => $net_salary,
            'amount_paid' => $net_salary,
            'status' => 'calculated'
        );
    }

    public static function ajax_calculate_payroll_period() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to run payroll.', 'verification-system')), 403);
        }

        $period_name = isset($_POST['period_name']) ? sanitize_text_field($_POST['period_name']) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (empty($period_name) || empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => __('Period name, start date, and end date are required.', 'verification-system')));
        }

        global $wpdb;
        $period_table = $wpdb->prefix . 'vs_payroll_periods';
        $record_table = $wpdb->prefix . 'vs_payroll_records';
        $emp_table = $wpdb->prefix . 'vs_employees';

        // Create or get period
        $wpdb->insert($period_table, array(
            'period_name' => $period_name,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'draft',
            'created_at' => current_time('mysql')
        ));
        $period_id = $wpdb->insert_id;

        $employees = $wpdb->get_results("SELECT id FROM {$emp_table} WHERE status = 'active'");

        foreach ($employees as $emp) {
            $calc = self::calculate_employee_payroll($emp->id, $start_date, $end_date);

            $wpdb->insert($record_table, array(
                'period_id' => $period_id,
                'employee_id' => $emp->id,
                'basic_salary' => $calc['basic_salary'],
                'allowances' => $calc['allowances'],
                'overtime_pay' => $calc['overtime_pay'],
                'bonuses' => $calc['bonuses'],
                'lateness_deduction' => $calc['lateness_deduction'],
                'absence_deduction' => $calc['absence_deduction'],
                'unpaid_leave_deduction' => $calc['unpaid_leave_deduction'],
                'other_deductions' => $calc['other_deductions'],
                'gross_earnings' => $calc['gross_earnings'],
                'total_deductions' => $calc['total_deductions'],
                'net_salary' => $calc['net_salary'],
                'amount_paid' => $calc['amount_paid'],
                'status' => 'draft',
                'created_at' => current_time('mysql')
            ));
        }

        VS_Audit_Logs::log('payroll_period_calculated', 'payroll_period', $period_id, array('name' => $period_name));

        wp_send_json_success(array(
            'message' => __('Payroll calculated successfully for ' . count($employees) . ' employees.', 'verification-system'),
            'period_id' => $period_id
        ));
    }

    public static function ajax_approve_payroll_period() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to approve payroll.', 'verification-system')), 403);
        }

        $period_id = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
        if (!$period_id) {
            wp_send_json_error(array('message' => __('Invalid period ID.', 'verification-system')));
        }

        global $wpdb;
        $period_table = $wpdb->prefix . 'vs_payroll_periods';
        $record_table = $wpdb->prefix . 'vs_payroll_records';

        $period = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$period_table} WHERE id = %d", $period_id));

        if (!$period) {
            wp_send_json_error(array('message' => __('Payroll period not found.', 'verification-system')));
        }

        if ($period->status === 'closed') {
            wp_send_json_error(array('message' => __('Closed payroll periods cannot be modified.', 'verification-system')));
        }

        $now = current_time('mysql');
        $user_id = get_current_user_id();

        $wpdb->update($period_table, array(
            'status' => 'approved',
            'approved_by' => $user_id,
            'approved_at' => $now
        ), array('id' => $period_id));

        $wpdb->update($record_table, array('status' => 'approved'), array('period_id' => $period_id));

        VS_Audit_Logs::log('payroll_period_approved', 'payroll_period', $period_id);

        wp_send_json_success(array('message' => __('Payroll period approved successfully.', 'verification-system')));
    }

    public static function ajax_get_payroll_statement() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $record_table = $wpdb->prefix . 'vs_payroll_records';
        $period_table = $wpdb->prefix . 'vs_payroll_periods';
        $emp_table = $wpdb->prefix . 'vs_employees';

        if (VS_Permissions::is_employee()) {
            $emp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$emp_table} WHERE user_id = %d", $user_id));
            if (!$emp) {
                wp_send_json_success(array('records' => array()));
                return;
            }
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, p.period_name, p.start_date, p.end_date FROM {$record_table} r JOIN {$period_table} p ON r.period_id = p.id WHERE r.employee_id = %d ORDER BY r.id DESC",
                $emp->id
            ));
        } else {
            $records = $wpdb->get_results(
                "SELECT r.*, p.period_name, p.start_date, p.end_date, e.employee_code, u.display_name FROM {$record_table} r JOIN {$period_table} p ON r.period_id = p.id JOIN {$emp_table} e ON r.employee_id = e.id JOIN {$wpdb->users} u ON e.user_id = u.ID ORDER BY r.id DESC"
            );
        }

        wp_send_json_success(array('records' => $records));
    }

    public static function ajax_export_payroll_csv() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager() && !VS_Permissions::is_branch_manager()) {
            wp_die(__('Unauthorized to export payroll data.', 'verification-system'), 403);
        }

        $period_id = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

        global $wpdb;
        $record_table = $wpdb->prefix . 'vs_payroll_records';
        $emp_table = $wpdb->prefix . 'vs_employees';

        if ($period_id) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, e.employee_code, u.display_name, u.user_email FROM {$record_table} r JOIN {$emp_table} e ON r.employee_id = e.id JOIN {$wpdb->users} u ON e.user_id = u.ID WHERE r.period_id = %d",
                $period_id
            ));
        } else {
            $results = $wpdb->get_results(
                "SELECT r.*, e.employee_code, u.display_name, u.user_email FROM {$record_table} r JOIN {$emp_table} e ON r.employee_id = e.id JOIN {$wpdb->users} u ON e.user_id = u.ID"
            );
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payroll_export_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Employee Code', 'Name', 'Basic Salary', 'Allowances', 'Overtime Pay', 'Gross Earnings', 'Lateness Deductions', 'Absence Deductions', 'Total Deductions', 'Net Salary', 'Status'));

        foreach ($results as $row) {
            fputcsv($output, array(
                $row->employee_code,
                $row->display_name,
                $row->basic_salary,
                $row->allowances,
                $row->overtime_pay,
                $row->gross_earnings,
                $row->lateness_deduction,
                $row->absence_deduction,
                $row->total_deductions,
                $row->net_salary,
                $row->status
            ));
        }

        fclose($output);
        exit;
    }
}

VS_Payroll::init();
