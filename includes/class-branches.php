<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Branches {

    public static function init() {
        add_action('wp_ajax_vs_create_branch', array(__CLASS__, 'ajax_create_branch'));
        add_action('wp_ajax_vs_update_branch', array(__CLASS__, 'ajax_update_branch'));
        add_action('wp_ajax_vs_get_branches', array(__CLASS__, 'ajax_get_branches'));
    }

    public static function ajax_create_branch() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to create branches.', 'verification-system')), 403);
        }

        $branch_code = isset($_POST['branch_code']) ? sanitize_text_field($_POST['branch_code']) : 'BR' . wp_rand(100, 999);
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '';
        $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0.0;
        $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0.0;
        $radius = isset($_POST['geofence_radius']) ? (int)$_POST['geofence_radius'] : 100;

        if (empty($name) || !VS_Validation::validate_coordinates($latitude, $longitude)) {
            wp_send_json_error(array('message' => __('Please provide valid branch name and GPS coordinates.', 'verification-system')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vs_branches';

        $wpdb->insert($table, array(
            'branch_code' => $branch_code,
            'name' => $name,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geofence_radius' => $radius,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ));

        $branch_id = $wpdb->insert_id;

        VS_Audit_Logs::log('branch_created', 'branch', $branch_id, array('name' => $name));

        wp_send_json_success(array('message' => __('Branch created successfully.', 'verification-system'), 'branch_id' => $branch_id));
    }

    public static function ajax_update_branch() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!VS_Permissions::is_admin() && !VS_Permissions::is_general_manager()) {
            wp_send_json_error(array('message' => __('Unauthorized to update branches.', 'verification-system')), 403);
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '';
        $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0.0;
        $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0.0;
        $radius = isset($_POST['geofence_radius']) ? (int)$_POST['geofence_radius'] : 100;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        if (!$id || empty($name) || !VS_Validation::validate_coordinates($latitude, $longitude)) {
            wp_send_json_error(array('message' => __('Valid branch ID, name, and GPS coordinates required.', 'verification-system')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vs_branches';

        $wpdb->update($table, array(
            'name' => $name,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geofence_radius' => $radius,
            'status' => $status,
            'updated_at' => current_time('mysql')
        ), array('id' => $id));

        VS_Audit_Logs::log('branch_updated', 'branch', $id);

        wp_send_json_success(array('message' => __('Branch updated successfully.', 'verification-system')));
    }

    public static function ajax_get_branches() {
        check_ajax_referer('vs_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('User not logged in.', 'verification-system')), 401);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vs_branches';

        $branches = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");

        wp_send_json_success(array('branches' => $branches));
    }
}

VS_Branches::init();
