<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Scheduling {

    public static function get_schedule($employee_id, $branch_id, $date_str) {
        global $wpdb;
        $sch_table = $wpdb->prefix . 'vs_schedules';

        $day_of_week = date('w', strtotime($date_str)); // 0 (Sun) to 6 (Sat)

        // 1. Check employee-specific schedule
        $emp_sch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE target_type = 'employee' AND target_id = %d AND day_of_week = %d AND effective_date <= %s ORDER BY effective_date DESC, id DESC LIMIT 1",
            $employee_id,
            $day_of_week,
            $date_str
        ));

        if ($emp_sch) {
            return $emp_sch;
        }

        // 2. Check branch-specific schedule
        $branch_sch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE target_type = 'branch' AND target_id = %d AND day_of_week = %d AND effective_date <= %s ORDER BY effective_date DESC, id DESC LIMIT 1",
            $branch_id,
            $day_of_week,
            $date_str
        ));

        if ($branch_sch) {
            return $branch_sch;
        }

        // 3. Default fallback schedule (09:00 to 17:00, Sun-Thu active, Fri-Sat rest)
        $default_rest = ($day_of_week == 5 || $day_of_week == 6) ? 1 : 0; // Fri / Sat default rest in UAE
        return (object) array(
            'target_type' => 'default',
            'target_id' => 0,
            'day_of_week' => $day_of_week,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_rest_day' => $default_rest,
            'grace_period_minutes' => 15,
            'effective_date' => '2020-01-01'
        );
    }

    public static function evaluate_clock_in_status($employee_id, $branch_id, $date_str, $clock_in_datetime) {
        $sch = self::get_schedule($employee_id, $branch_id, $date_str);

        if ($sch->is_rest_day) {
            return 'rest_day';
        }

        if (empty($sch->start_time)) {
            return 'present';
        }

        $clock_in_time = date('H:i:s', strtotime($clock_in_datetime));
        $scheduled_start = $sch->start_time;

        $grace_minutes = isset($sch->grace_period_minutes) ? (int)$sch->grace_period_minutes : 15;
        $allowed_time = date('H:i:s', strtotime("{$scheduled_start} +{$grace_minutes} minutes"));

        if ($clock_in_time > $allowed_time) {
            return 'late';
        }

        return 'present';
    }

    public static function evaluate_clock_out_status($employee_id, $branch_id, $date_str, $clock_in_datetime, $clock_out_datetime, $current_status) {
        $sch = self::get_schedule($employee_id, $branch_id, $date_str);

        if (empty($sch->end_time)) {
            return $current_status;
        }

        $clock_out_time = date('H:i:s', strtotime($clock_out_datetime));
        $scheduled_end = $sch->end_time;

        if ($clock_out_time < $scheduled_end) {
            return ($current_status === 'late') ? 'late_and_early_departure' : 'early_departure';
        }

        return $current_status;
    }
}
