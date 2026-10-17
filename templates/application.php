<?php
/**
 * Standalone Application Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="verification-system-app">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_the_title(get_option('vs_page_id')) ?: 'Verification System'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="vs-app-body verification-system-app">
    <div id="vs-app-root" class="vs-app-root">
        <div class="vs-loading-spinner" id="vs-initial-loader" style="padding: 40px; text-align: center;">
            <p><?php esc_html_e('Loading Verification System...', 'verification-system'); ?></p>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
