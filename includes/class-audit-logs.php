<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Audit_Logs {

    public static function log($action, $object_type = null, $object_id = null, $details = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'vs_audit_logs';
        $user_id = get_current_user_id();

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $details_str = is_array($details) || is_object($details) ? wp_json_encode($details) : (string)$details;

        $wpdb->insert($table, array(
            'user_id' => $user_id ? $user_id : null,
            'action' => sanitize_text_field($action),
            'object_type' => $object_type ? sanitize_text_field($object_type) : null,
            'object_id' => $object_id ? (int)$object_id : null,
            'details' => $details_str,
            'ip_address' => $ip,
            'created_at' => current_time('mysql')
        ));
    }
}
