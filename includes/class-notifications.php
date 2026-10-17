<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Notifications {

    public static function init() {
        add_action('wp_ajax_vs_get_notifications', array(__CLASS__, 'ajax_get_notifications'));
        add_action('wp_ajax_vs_mark_notification_read', array(__CLASS__, 'ajax_mark_read'));
    }

    public static function create($user_id, $title, $message, $type = 'info') {
        global $wpdb;
        $table = $wpdb->prefix . 'vs_notifications';

        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'title' => sanitize_text_field($title),
            'message' => sanitize_textarea_field($message),
            'type' => sanitize_text_field($type),
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ));
    }

    public static function ajax_get_notifications() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'vs_notifications';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 20",
            $user_id
        ));

        wp_send_json_success(array('notifications' => $notifications));
    }

    public static function ajax_mark_read() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'vs_notifications';

        if ($id) {
            $wpdb->update($table, array('is_read' => 1), array('id' => $id, 'user_id' => $user_id));
        } else {
            $wpdb->update($table, array('is_read' => 1), array('user_id' => $user_id));
        }

        wp_send_json_success(array('message' => __('Notifications updated.', 'verification-system')));
    }
}

VS_Notifications::init();
