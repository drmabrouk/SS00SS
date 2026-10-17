<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Passkeys {

    public static function init() {
        add_action('wp_ajax_vs_passkey_register_options', array(__CLASS__, 'ajax_register_options'));
        add_action('wp_ajax_vs_passkey_register_verify', array(__CLASS__, 'ajax_register_verify'));
        add_action('wp_ajax_nopriv_vs_passkey_auth_options', array(__CLASS__, 'ajax_auth_options'));
        add_action('wp_ajax_vs_passkey_auth_options', array(__CLASS__, 'ajax_auth_options'));
        add_action('wp_ajax_nopriv_vs_passkey_auth_verify', array(__CLASS__, 'ajax_auth_verify'));
        add_action('wp_ajax_vs_passkey_auth_verify', array(__CLASS__, 'ajax_auth_verify'));
        add_action('wp_ajax_vs_passkey_revoke', array(__CLASS__, 'ajax_revoke_passkey'));
    }

    public static function get_rp_id() {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return $host ? $host : 'localhost';
    }

    public static function ajax_register_options() {
        check_ajax_referer('vs_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user = wp_get_current_user();
        $challenge = wp_generate_password(32, false);

        set_transient('vs_passkey_reg_challenge_' . $user->ID, $challenge, 300);

        $options = array(
            'rp' => array(
                'name' => get_bloginfo('name') . ' Verification System',
                'id' => self::get_rp_id()
            ),
            'user' => array(
                'id' => base64_encode('vs_user_' . $user->ID),
                'name' => $user->user_email ? $user->user_email : $user->user_login,
                'displayName' => $user->display_name
            ),
            'challenge' => base64_encode($challenge),
            'pubKeyCredParams' => array(
                array('type' => 'public-key', 'alg' => -7),  // ES256
                array('type' => 'public-key', 'alg' => -257) // RS256
            ),
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => array(
                'userVerification' => 'preferred'
            )
        );

        wp_send_json_success($options);
    }

    public static function ajax_register_verify() {
        check_ajax_referer('vs_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user = wp_get_current_user();
        $stored_challenge = get_transient('vs_passkey_reg_challenge_' . $user->ID);

        if (!$stored_challenge) {
            wp_send_json_error(array('message' => __('Passkey registration challenge expired.', 'verification-system')));
        }

        $raw_response = isset($_POST['credential']) ? $_POST['credential'] : '';
        if (empty($raw_response)) {
            wp_send_json_error(array('message' => __('Invalid passkey payload.', 'verification-system')));
        }

        $credential = json_decode(stripslashes($raw_response), true);
        if (!$credential || empty($credential['id'])) {
            wp_send_json_error(array('message' => __('Invalid passkey data.', 'verification-system')));
        }

        // Validate clientDataJSON challenge if present
        if (!empty($credential['clientDataJSON'])) {
            $client_data_raw = base64_decode($credential['clientDataJSON']);
            $client_data = json_decode($client_data_raw, true);
            if ($client_data && isset($client_data['challenge'])) {
                $client_challenge = str_replace(array('-', '_'), array('+', '/'), $client_data['challenge']);
                if (base64_encode($stored_challenge) !== $client_challenge && $stored_challenge !== $client_data['challenge']) {
                    wp_send_json_error(array('message' => __('Registration challenge mismatch.', 'verification-system')));
                }
            }
        }

        delete_transient('vs_passkey_reg_challenge_' . $user->ID);

        global $wpdb;
        $table = $wpdb->prefix . 'vs_passkeys';

        $cred_id = sanitize_text_field($credential['id']);
        $public_key = wp_json_encode($credential);

        $wpdb->insert($table, array(
            'user_id' => $user->ID,
            'credential_id' => $cred_id,
            'public_key' => $public_key,
            'sign_count' => 0,
            'user_handle' => base64_encode('vs_user_' . $user->ID),
            'created_at' => current_time('mysql')
        ));

        VS_Audit_Logs::log('passkey_registered', 'user', $user->ID, array('credential_id' => $cred_id));

        wp_send_json_success(array('message' => __('Passkey registered successfully.', 'verification-system')));
    }

    public static function ajax_auth_options() {
        check_ajax_referer('vs_nonce', 'nonce');

        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone = VS_Validation::normalize_phone($phone_raw);

        $user_id = 0;
        if (!empty($phone)) {
            global $wpdb;
            $emp_table = $wpdb->prefix . 'vs_employees';
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$emp_table} WHERE phone = %s", $phone));
        }

        $challenge = wp_generate_password(32, false);
        $sess_key = 'vs_passkey_auth_challenge_' . md5($challenge);
        set_transient($sess_key, array('user_id' => $user_id, 'challenge' => $challenge), 300);

        global $wpdb;
        $table = $wpdb->prefix . 'vs_passkeys';

        $allow_credentials = array();
        if ($user_id) {
            $keys = $wpdb->get_results($wpdb->prepare("SELECT credential_id FROM {$table} WHERE user_id = %d", $user_id));
            foreach ($keys as $k) {
                $allow_credentials[] = array(
                    'type' => 'public-key',
                    'id' => $k->credential_id
                );
            }
        }

        $options = array(
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'rpId' => self::get_rp_id(),
            'allowCredentials' => $allow_credentials,
            'session_challenge_id' => md5($challenge)
        );

        wp_send_json_success($options);
    }

    public static function ajax_auth_verify() {
        check_ajax_referer('vs_nonce', 'nonce');

        $sess_id = isset($_POST['session_challenge_id']) ? sanitize_text_field($_POST['session_challenge_id']) : '';
        $transient = get_transient('vs_passkey_auth_challenge_' . $sess_id);

        if (!$transient) {
            wp_send_json_error(array('message' => __('Passkey authentication challenge expired.', 'verification-system')));
        }

        $stored_challenge = $transient['challenge'];
        $expected_user_id = (int)$transient['user_id'];

        delete_transient('vs_passkey_auth_challenge_' . $sess_id);

        $raw_response = isset($_POST['credential']) ? $_POST['credential'] : '';
        if (empty($raw_response)) {
            wp_send_json_error(array('message' => __('Invalid passkey authentication payload.', 'verification-system')));
        }

        $credential = json_decode(stripslashes($raw_response), true);
        if (!$credential || empty($credential['id'])) {
            wp_send_json_error(array('message' => __('Invalid credential data.', 'verification-system')));
        }

        // Challenge and ClientDataJSON verification
        if (!empty($credential['clientDataJSON'])) {
            $client_data_raw = base64_decode($credential['clientDataJSON']);
            $client_data = json_decode($client_data_raw, true);
            if ($client_data && isset($client_data['challenge'])) {
                $resp_challenge = str_replace(array('-', '_'), array('+', '/'), $client_data['challenge']);
                if (base64_encode($stored_challenge) !== $resp_challenge && $stored_challenge !== $client_data['challenge']) {
                    wp_send_json_error(array('message' => __('Authentication challenge verification failed.', 'verification-system')));
                }
            }
        }

        $cred_id = sanitize_text_field($credential['id']);

        global $wpdb;
        $table = $wpdb->prefix . 'vs_passkeys';

        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE credential_id = %s", $cred_id));

        if (!$record) {
            wp_send_json_error(array('message' => __('Unrecognized passkey.', 'verification-system')));
        }

        $user_id = (int)$record->user_id;

        if ($expected_user_id > 0 && $user_id !== $expected_user_id) {
            wp_send_json_error(array('message' => __('Passkey does not belong to the specified account.', 'verification-system')));
        }

        $user = get_user_by('id', $user_id);

        if (!$user) {
            wp_send_json_error(array('message' => __('Associated user account not found.', 'verification-system')));
        }

        // Authenticate user
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        // Update sign count
        $wpdb->update($table, array('sign_count' => $record->sign_count + 1), array('id' => $record->id));

        VS_Audit_Logs::log('passkey_login_success', 'user', $user->ID, array('credential_id' => $cred_id));

        $role = VS_Permissions::get_user_role($user->ID);

        wp_send_json_success(array(
            'message' => __('Passkey authentication successful.', 'verification-system'),
            'role' => $role,
            'user_id' => $user->ID
        ));
    }

    public static function ajax_revoke_passkey() {
        check_ajax_referer('vs_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $id = isset($_POST['passkey_id']) ? (int)$_POST['passkey_id'] : 0;
        $current_user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'vs_passkeys';

        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        if (!$record) {
            wp_send_json_error(array('message' => __('Passkey record not found.', 'verification-system')));
        }

        if ((int)$record->user_id !== $current_user_id && !VS_Permissions::is_admin()) {
            wp_send_json_error(array('message' => __('Unauthorized to delete this passkey.', 'verification-system')), 403);
        }

        $wpdb->delete($table, array('id' => $id));

        VS_Audit_Logs::log('passkey_revoked', 'user', $record->user_id, array('passkey_id' => $id));

        wp_send_json_success(array('message' => __('Passkey revoked successfully.', 'verification-system')));
    }
}

VS_Passkeys::init();
