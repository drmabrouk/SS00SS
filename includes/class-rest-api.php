<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_REST_API {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        register_rest_route('verification-system/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_status'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('verification-system/v1', '/clock-in', array(
            'methods' => 'POST',
            'callback' => array('VS_Attendance', 'ajax_clock_in'),
            'permission_callback' => array(__CLASS__, 'check_auth_permission')
        ));
    }

    public static function check_auth_permission() {
        return is_user_logged_in();
    }

    public static function get_status() {
        return rest_ensure_response(array(
            'plugin' => 'Verification System',
            'version' => VS_VERSION,
            'status' => 'active'
        ));
    }
}

VS_REST_API::init();
