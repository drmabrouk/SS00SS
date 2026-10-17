<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once VS_PLUGIN_DIR . 'includes/class-database.php';
        require_once VS_PLUGIN_DIR . 'includes/class-installer.php';
        require_once VS_PLUGIN_DIR . 'includes/class-roles.php';
        require_once VS_PLUGIN_DIR . 'includes/class-permissions.php';
        require_once VS_PLUGIN_DIR . 'includes/class-validation.php';
        require_once VS_PLUGIN_DIR . 'includes/class-users.php';
        require_once VS_PLUGIN_DIR . 'includes/class-employees.php';
        require_once VS_PLUGIN_DIR . 'includes/class-branches.php';
        require_once VS_PLUGIN_DIR . 'includes/class-authentication.php';
        require_once VS_PLUGIN_DIR . 'includes/class-passkeys.php';
        require_once VS_PLUGIN_DIR . 'includes/class-geolocation.php';
        require_once VS_PLUGIN_DIR . 'includes/class-attendance.php';
        require_once VS_PLUGIN_DIR . 'includes/class-scheduling.php';
        require_once VS_PLUGIN_DIR . 'includes/class-leave-management.php';
        require_once VS_PLUGIN_DIR . 'includes/class-payroll.php';
        require_once VS_PLUGIN_DIR . 'includes/class-reports.php';
        require_once VS_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once VS_PLUGIN_DIR . 'includes/class-audit-logs.php';
        require_once VS_PLUGIN_DIR . 'includes/class-rest-api.php';
    }

    private function init_hooks() {
        add_shortcode('verification_system', array($this, 'render_shortcode'));
        add_action('template_redirect', array($this, 'handle_template_redirect'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'restrict_wp_admin_access'));
        add_filter('show_admin_bar', array($this, 'control_admin_bar'));
    }

    public function run() {
        // Plugin running
    }

    public function render_shortcode() {
        ob_start();
        include VS_PLUGIN_DIR . 'templates/application.php';
        return ob_get_clean();
    }

    public function handle_template_redirect() {
        $vs_page_id = get_option('vs_page_id');
        if (is_page($vs_page_id)) {
            // Render standalone template
            include VS_PLUGIN_DIR . 'templates/application.php';
            exit;
        }
    }

    public function enqueue_assets() {
        $vs_page_id = get_option('vs_page_id');
        if (is_page($vs_page_id)) {
            wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), null);
            wp_enqueue_style('vs-application-css', VS_PLUGIN_URL . 'assets/css/application.css', array(), VS_VERSION);

            wp_enqueue_script('vs-application-js', VS_PLUGIN_URL . 'assets/js/application.js', array('jquery'), VS_VERSION, true);

            wp_localize_script('vs-application-js', 'vsConfig', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('vs_nonce'),
                'is_logged_in' => is_user_logged_in(),
                'user_role' => VS_Permissions::get_user_role(),
                'user_id' => get_current_user_id()
            ));
        }
    }

    public function restrict_wp_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_admin() && !current_user_can('administrator') && !VS_Permissions::is_admin()) {
            $vs_page_id = get_option('vs_page_id');
            if ($vs_page_id) {
                wp_redirect(get_permalink($vs_page_id));
                exit;
            } else {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    public function control_admin_bar($show) {
        if (!current_user_can('administrator') && !VS_Permissions::is_admin()) {
            return false;
        }
        return $show;
    }
}
