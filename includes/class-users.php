<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Users {

    public static function init() {
        add_action('wp_ajax_vs_create_user', array(__CLASS__, 'ajax_create_user'));
        add_action('wp_ajax_vs_get_users', array(__CLASS__, 'ajax_get_users'));
    }

    public static function ajax_create_user() {
        check_ajax_referer('vs_nonce', 'nonce');

        $current_user_id = get_current_user_id();
        $target_role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : VS_Roles::ROLE_EMPLOYEE;

        // Role Creation Authorization Rules according to section 12 & 4:
        // System Administrator may create all subordinate roles.
        // General Manager may create Branch Managers and Employees within scope.
        // Branch Manager may create Employees if authorized.
        // No role may create System Administrator through plugin interface.
        if ($target_role === VS_Roles::ROLE_ADMIN) {
            wp_send_json_error(array('message' => __('Cannot create System Administrator accounts through this interface.', 'verification-system')), 403);
        }

        if (VS_Permissions::is_employee()) {
            wp_send_json_error(array('message' => __('Employees cannot create user accounts.', 'verification-system')), 403);
        }

        if (VS_Permissions::is_general_manager() && $target_role === VS_Roles::ROLE_GENERAL_MANAGER) {
            wp_send_json_error(array('message' => __('General Managers cannot create other General Managers.', 'verification-system')), 403);
        }

        if (VS_Permissions::is_branch_manager() && $target_role !== VS_Roles::ROLE_EMPLOYEE) {
            wp_send_json_error(array('message' => __('Branch Managers can only create Employee accounts.', 'verification-system')), 403);
        }

        $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';
        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
        $job_title = isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : '';
        $employee_code = isset($_POST['employee_code']) ? sanitize_text_field($_POST['employee_code']) : 'EMP' . wp_rand(1000, 9999);

        $phone = VS_Validation::normalize_phone($phone_raw);

        if (empty($full_name) || empty($phone) || empty($password)) {
            wp_send_json_error(array('message' => __('Full name, phone number, and password are required.', 'verification-system')));
        }

        // Check if phone or email exists
        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $existing_phone = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$emp_table} WHERE phone = %s", $phone));
        if ($existing_phone) {
            wp_send_json_error(array('message' => __('An account with this phone number already exists.', 'verification-system')));
        }

        if (!empty($email) && email_exists($email)) {
            wp_send_json_error(array('message' => __('An account with this email address already exists.', 'verification-system')));
        }

        $username = 'vs_' . preg_replace('/[^\d]/', '', $phone);
        if (empty($email)) {
            $email = $username . '@vsapp.local';
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Update display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $full_name,
            'first_name' => $full_name,
            'role' => $target_role
        ));

        // Create employee profile record
        $wpdb->insert($emp_table, array(
            'user_id' => $user_id,
            'employee_code' => $employee_code,
            'phone' => $phone,
            'branch_id' => $branch_id ? $branch_id : null,
            'job_title' => $job_title,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ));

        $emp_id = $wpdb->insert_id;

        // Initialize default salary config
        $sal_table = $wpdb->prefix . 'vs_salary_configs';
        $basic = isset($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 5000.00;
        $allowances = isset($_POST['fixed_allowances']) ? (float)$_POST['fixed_allowances'] : 1000.00;

        $wpdb->insert($sal_table, array(
            'employee_id' => $emp_id,
            'basic_salary' => $basic,
            'fixed_allowances' => $allowances,
            'currency' => 'AED',
            'payment_frequency' => 'monthly',
            'contractual_working_days' => 22,
            'contractual_daily_hours' => 8.00,
            'overtime_rate_multiplier' => 1.25,
            'effective_date' => current_time('Y-m-d'),
            'created_at' => current_time('mysql')
        ));

        VS_Audit_Logs::log('user_created', 'user', $user_id, array('role' => $target_role, 'phone' => $phone));

        wp_send_json_success(array('message' => __('User created successfully.', 'verification-system'), 'user_id' => $user_id));
    }

    public static function ajax_get_users() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in() || VS_Permissions::is_employee()) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'verification-system')), 403);
        }

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $branch_table = $wpdb->prefix . 'vs_branches';

        $current_user_id = get_current_user_id();

        if (VS_Permissions::is_admin() || VS_Permissions::is_general_manager()) {
            $users = $wpdb->get_results(
                "SELECT e.*, u.display_name, u.user_email, b.name as branch_name
                 FROM {$emp_table} e
                 JOIN {$wpdb->users} u ON e.user_id = u.ID
                 LEFT JOIN {$branch_table} b ON e.branch_id = b.id
                 ORDER BY e.id DESC"
            );
        } else {
            // Branch manager: only employees in same branch
            $bm_branch = $wpdb->get_var($wpdb->prepare("SELECT branch_id FROM {$emp_table} WHERE user_id = %d", $current_user_id));
            if (!$bm_branch) {
                wp_send_json_success(array('users' => array()));
                return;
            }

            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT e.*, u.display_name, u.user_email, b.name as branch_name
                 FROM {$emp_table} e
                 JOIN {$wpdb->users} u ON e.user_id = u.ID
                 LEFT JOIN {$branch_table} b ON e.branch_id = b.id
                 WHERE e.branch_id = %d
                 ORDER BY e.id DESC",
                $bm_branch
            ));
        }

        wp_send_json_success(array('users' => $users));
    }
}

VS_Users::init();
