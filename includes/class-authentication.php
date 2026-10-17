<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Authentication {

    public static function init() {
        add_action('wp_ajax_nopriv_vs_login', array(__CLASS__, 'ajax_login'));
        add_action('wp_ajax_vs_login', array(__CLASS__, 'ajax_login'));

        add_action('wp_ajax_nopriv_vs_forgot_password', array(__CLASS__, 'ajax_forgot_password'));
        add_action('wp_ajax_nopriv_vs_verify_recovery_code', array(__CLASS__, 'ajax_verify_recovery_code'));
        add_action('wp_ajax_nopriv_vs_reset_password', array(__CLASS__, 'ajax_reset_password'));

        add_action('wp_ajax_vs_logout', array(__CLASS__, 'ajax_logout'));
        add_action('wp_ajax_nopriv_vs_logout', array(__CLASS__, 'ajax_logout'));
    }

    public static function check_rate_limit($action, $identifier, $max_attempts = 5, $decay_seconds = 300) {
        $transient_key = 'vs_rl_' . md5($action . '_' . $identifier);
        $attempts = (int)get_transient($transient_key);

        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, $decay_seconds);
        return true;
    }

    public static function ajax_login() {
        check_ajax_referer('vs_nonce', 'nonce');

        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

        $phone = VS_Validation::normalize_phone($phone_raw);

        if (empty($phone) || empty($password)) {
            wp_send_json_error(array('message' => __('Please enter both phone number and password.', 'verification-system')));
        }

        if (!self::check_rate_limit('login', $phone, 5, 300)) {
            wp_send_json_error(array('message' => __('Too many failed login attempts. Please try again in 5 minutes.', 'verification-system')));
        }

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';

        // Find user by phone in vs_employees or user_login/user_email
        $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$emp_table} WHERE phone = %s AND status = 'active'", $phone));

        if (!$user_id) {
            // Check if phone matches user_login or meta
            $user = get_user_by('login', $phone);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid credentials or account is inactive.', 'verification-system')));
        }

        $user = get_user_by('id', $user_id);
        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_send_json_error(array('message' => __('Invalid credentials or account is inactive.', 'verification-system')));
        }

        // Authenticate user
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        VS_Audit_Logs::log('user_login', 'user', $user->ID, array('phone' => $phone));

        $role = VS_Permissions::get_user_role($user->ID);

        wp_send_json_success(array(
            'message' => __('Login successful.', 'verification-system'),
            'role' => $role,
            'user_id' => $user->ID
        ));
    }

    public static function ajax_forgot_password() {
        check_ajax_referer('vs_nonce', 'nonce');

        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone = VS_Validation::normalize_phone($phone_raw);

        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'verification-system')));
        }

        if (!self::check_rate_limit('recovery_request', $phone, 3, 600)) {
            wp_send_json_error(array('message' => __('Too many recovery requests. Please try again later.', 'verification-system')));
        }

        global $wpdb;
        $emp_table = $wpdb->prefix . 'vs_employees';
        $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$emp_table} WHERE phone = %s", $phone));

        if (!$user_id) {
            $user = get_user_by('login', $phone);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        // Always return success message for privacy preservation if account not found
        if (!$user_id) {
            wp_send_json_success(array('message' => __('If account exists, a 6-digit verification code has been sent to the registered email.', 'verification-system')));
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            wp_send_json_success(array('message' => __('If account exists, a 6-digit verification code has been sent to the registered email.', 'verification-system')));
        }

        // Generate 6-digit code
        $code = sprintf('%06d', wp_rand(100000, 999999));
        $code_hash = wp_hash_password($code);
        $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $recovery_table = $wpdb->prefix . 'vs_password_recoveries';

        // Invalidate previous active codes for this phone
        $wpdb->update($recovery_table, array('is_used' => 1), array('phone' => $phone, 'is_used' => 0));

        $wpdb->insert($recovery_table, array(
            'phone' => $phone,
            'user_id' => $user->ID,
            'code_hash' => $code_hash,
            'attempts' => 0,
            'expires_at' => $expires_at,
            'is_used' => 0,
            'created_at' => current_time('mysql')
        ));

        // Send Email
        $subject = __('Verification Code - Password Reset', 'verification-system');
        $message = sprintf(__('Your password reset verification code is: %s. It will expire in 10 minutes.', 'verification-system'), $code);
        wp_mail($user->user_email, $subject, $message);

        VS_Audit_Logs::log('password_recovery_request', 'user', $user->ID, array('phone' => $phone));

        wp_send_json_success(array(
            'message' => __('If account exists, a 6-digit verification code has been sent to the registered email.', 'verification-system'),
            'phone' => $phone
        ));
    }

    public static function ajax_verify_recovery_code() {
        check_ajax_referer('vs_nonce', 'nonce');

        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $phone = VS_Validation::normalize_phone($phone_raw);

        if (empty($phone) || strlen($code) !== 6) {
            wp_send_json_error(array('message' => __('Please provide a valid 6-digit code.', 'verification-system')));
        }

        global $wpdb;
        $recovery_table = $wpdb->prefix . 'vs_password_recoveries';

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$recovery_table} WHERE phone = %s AND is_used = 0 ORDER BY id DESC LIMIT 1",
            $phone
        ));

        if (!$record) {
            wp_send_json_error(array('message' => __('Invalid or expired verification code.', 'verification-system')));
        }

        if (strtotime($record->expires_at) < time()) {
            wp_send_json_error(array('message' => __('Verification code has expired. Please request a new one.', 'verification-system')));
        }

        if ($record->attempts >= 5) {
            $wpdb->update($recovery_table, array('is_used' => 1), array('id' => $record->id));
            wp_send_json_error(array('message' => __('Maximum verification attempts exceeded. Please request a new code.', 'verification-system')));
        }

        // Increment attempts
        $wpdb->update($recovery_table, array('attempts' => $record->attempts + 1), array('id' => $record->id));

        if (!wp_check_password($code, $record->code_hash)) {
            wp_send_json_error(array('message' => __('Incorrect verification code.', 'verification-system')));
        }

        // Generate temporary reset token
        $reset_token = wp_generate_password(32, false);
        set_transient('vs_reset_token_' . md5($reset_token), array(
            'user_id' => $record->user_id,
            'recovery_id' => $record->id
        ), 900); // 15 mins

        wp_send_json_success(array(
            'message' => __('Code verified successfully.', 'verification-system'),
            'reset_token' => $reset_token
        ));
    }

    public static function ajax_reset_password() {
        check_ajax_referer('vs_nonce', 'nonce');

        $reset_token = isset($_POST['reset_token']) ? sanitize_text_field($_POST['reset_token']) : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($new_password) || $new_password !== $confirm_password) {
            wp_send_json_error(array('message' => __('Passwords do not match or are empty.', 'verification-system')));
        }

        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => __('Password must be at least 8 characters long.', 'verification-system')));
        }

        $token_data = get_transient('vs_reset_token_' . md5($reset_token));
        if (!$token_data) {
            wp_send_json_error(array('message' => __('Reset token is invalid or expired. Please restart recovery process.', 'verification-system')));
        }

        $user_id = $token_data['user_id'];
        $recovery_id = $token_data['recovery_id'];

        wp_set_password($new_password, $user_id);

        global $wpdb;
        $recovery_table = $wpdb->prefix . 'vs_password_recoveries';
        $wpdb->update($recovery_table, array('is_used' => 1), array('id' => $recovery_id));

        delete_transient('vs_reset_token_' . md5($reset_token));

        // Revoke user sessions
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        VS_Audit_Logs::log('password_reset_success', 'user', $user_id);

        wp_send_json_success(array('message' => __('Password reset successfully. You can now log in with your new password.', 'verification-system')));
    }

    public static function ajax_logout() {
        check_ajax_referer('vs_nonce', 'nonce');
        $user_id = get_current_user_id();
        wp_logout();
        if ($user_id) {
            VS_Audit_Logs::log('user_logout', 'user', $user_id);
        }
        wp_send_json_success(array('message' => __('Logged out successfully.', 'verification-system')));
    }
}

VS_Authentication::init();
