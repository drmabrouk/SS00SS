<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Installer {

    public static function activate() {
        VS_Database::create_tables();
        VS_Roles::register_roles();
        self::create_application_page();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function create_application_page() {
        $page_id = get_option('vs_page_id');

        if ($page_id && get_post($page_id)) {
            // Page exists
            return;
        }

        // Check if page with title exists
        $existing = get_page_by_title('Verification System');
        if ($existing) {
            update_option('vs_page_id', $existing->ID);
            return;
        }

        $page_data = array(
            'post_title'    => 'Verification System',
            'post_content'  => '[verification_system]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        );

        $new_page_id = wp_insert_post($page_data);
        if ($new_page_id && !is_wp_error($new_page_id)) {
            update_option('vs_page_id', $new_page_id);
        }
    }
}
