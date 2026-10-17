<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Roles {

    public const ROLE_ADMIN = 'administrator';
    public const ROLE_GENERAL_MANAGER = 'vs_general_manager';
    public const ROLE_BRANCH_MANAGER = 'vs_branch_manager';
    public const ROLE_EMPLOYEE = 'vs_employee';

    public static function register_roles() {
        // System Administrator uses existing 'administrator' role.

        add_role(self::ROLE_GENERAL_MANAGER, __('General Manager', 'verification-system'), array(
            'read' => true,
            'vs_access_app' => true,
            'vs_manage_branches' => true,
            'vs_manage_branch_managers' => true,
            'vs_manage_employees' => true,
            'vs_manage_schedules' => true,
            'vs_view_payroll' => true,
            'vs_manage_payroll' => true,
            'vs_view_reports' => true,
            'vs_manage_leave' => true,
        ));

        add_role(self::ROLE_BRANCH_MANAGER, __('Branch Manager', 'verification-system'), array(
            'read' => true,
            'vs_access_app' => true,
            'vs_view_branch_employees' => true,
            'vs_manage_branch_attendance' => true,
            'vs_view_branch_payroll' => true,
            'vs_manage_branch_leave' => true,
        ));

        add_role(self::ROLE_EMPLOYEE, __('Employee', 'verification-system'), array(
            'read' => true,
            'vs_access_app' => true,
            'vs_clock_in_out' => true,
            'vs_view_own_profile' => true,
            'vs_view_own_attendance' => true,
            'vs_view_own_payroll' => true,
            'vs_submit_leave' => true,
        ));

        // Add caps to Administrator
        $admin = get_role('administrator');
        if ($admin) {
            $caps = array(
                'vs_access_app',
                'vs_manage_settings',
                'vs_manage_branches',
                'vs_manage_general_managers',
                'vs_manage_branch_managers',
                'vs_manage_employees',
                'vs_manage_schedules',
                'vs_view_payroll',
                'vs_manage_payroll',
                'vs_view_reports',
                'vs_manage_leave',
                'vs_override_attendance',
                'vs_view_all_branches',
                'vs_audit_logs'
            );
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
}
