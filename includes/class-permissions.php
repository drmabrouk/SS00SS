<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Permissions {

    public static function get_user_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->roles)) {
            return false;
        }

        if (in_array('administrator', $user->roles, true)) {
            return VS_Roles::ROLE_ADMIN;
        }
        if (in_array(VS_Roles::ROLE_GENERAL_MANAGER, $user->roles, true)) {
            return VS_Roles::ROLE_GENERAL_MANAGER;
        }
        if (in_array(VS_Roles::ROLE_BRANCH_MANAGER, $user->roles, true)) {
            return VS_Roles::ROLE_BRANCH_MANAGER;
        }
        if (in_array(VS_Roles::ROLE_EMPLOYEE, $user->roles, true)) {
            return VS_Roles::ROLE_EMPLOYEE;
        }

        return false;
    }

    public static function can_access_app($user_id = null) {
        $role = self::get_user_role($user_id);
        return $role !== false;
    }

    public static function is_admin($user_id = null) {
        return self::get_user_role($user_id) === VS_Roles::ROLE_ADMIN;
    }

    public static function is_general_manager($user_id = null) {
        return self::get_user_role($user_id) === VS_Roles::ROLE_GENERAL_MANAGER;
    }

    public static function is_branch_manager($user_id = null) {
        return self::get_user_role($user_id) === VS_Roles::ROLE_BRANCH_MANAGER;
    }

    public static function is_employee($user_id = null) {
        return self::get_user_role($user_id) === VS_Roles::ROLE_EMPLOYEE;
    }

    public static function can_manage_user($target_user_id, $current_user_id = null) {
        if (!$current_user_id) {
            $current_user_id = get_current_user_id();
        }

        if (self::is_admin($current_user_id)) {
            return true;
        }

        $target_role = self::get_user_role($target_user_id);

        if (self::is_general_manager($current_user_id)) {
            // GM cannot manage Admins or GMs
            if ($target_role === VS_Roles::ROLE_ADMIN || $target_role === VS_Roles::ROLE_GENERAL_MANAGER) {
                return false;
            }
            return true;
        }

        if (self::is_branch_manager($current_user_id)) {
            // BM can only view/manage Employees in their branch
            if ($target_role !== VS_Roles::ROLE_EMPLOYEE) {
                return false;
            }
            return self::is_same_branch($current_user_id, $target_user_id);
        }

        return false;
    }

    public static function is_same_branch($user1_id, $user2_id) {
        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';

        $b1 = $wpdb->get_var($wpdb->prepare("SELECT branch_id FROM {$emp_table} WHERE user_id = %d", $user1_id));
        $b2 = $wpdb->get_var($wpdb->prepare("SELECT branch_id FROM {$emp_table} WHERE user_id = %d", $user2_id));

        if (!$b1 || !$b2) {
            return false;
        }

        return (int)$b1 === (int)$b2;
    }

    public static function enforce_capability($capability) {
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'verification-system')), 403);
            exit;
        }
    }
}
