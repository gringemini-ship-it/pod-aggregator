<?php
/**
 * Plugin Name: POD Aggregator — Activation Logger
 * Plugin URI:  https://github.com/gringemini-ship-it/pod-aggregator
 * Description: MU-plugin: captures fatal errors during POD Aggregator activation.
 *              Install to wp-content/mu-plugins/pod-debug.php, then try activating.
 * Version:     1.0.0
 * Author:      POD Aggregator Team
 * License:     GPL-2.0-or-later
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Only run in admin.
if (!is_admin()) {
    return;
}

// Capture activation attempts for pod-aggregator.
add_action('activated_plugin', function ($plugin, $network_wide) {
    if (strpos($plugin, 'pod-aggregator') === false) {
        return;
    }

    $log = WP_CONTENT_DIR . '/pod-aggregator-activation.log';
    $entry = sprintf(
        "[%s] Plugin activated successfully. Network: %s, Plugin: %s\n",
        date('Y-m-d H:i:s'),
        $network_wide ? 'yes' : 'no',
        $plugin
    );
    file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
}, 10, 2);

// Capture the shutdown to catch fatal errors.
register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    // Only log fatal errors.
    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        return;
    }

    $log = WP_CONTENT_DIR . '/pod-aggregator-activation.log';
    $entry = sprintf(
        "[%s] FATAL ERROR: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $error['message'],
        $error['file'],
        $error['line']
    );
    file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
});

// Add a notice on the plugins page to help the user.
add_action('admin_notices', function () {
    $log_file = WP_CONTENT_DIR . '/pod-aggregator-activation.log';
    if (!file_exists($log_file)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }
    ?>
    <div class="notice notice-info">
        <p>
            <strong>POD Aggregator Debug:</strong>
            Activation log available at <code><?php echo esc_html($log_file); ?></code>
            (<?php echo esc_html(filesize($log_file)); ?> bytes, last modified
            <?php echo esc_html(date('Y-m-d H:i:s', filemtime($log_file))); ?>)
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=pod_view_activation_log')); ?>"
               class="button" target="_blank">View Activation Log</a>
        </p>
    </div>
    <?php
});

// AJAX endpoint to view the log.
add_action('wp_ajax_pod_view_activation_log', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }

    $log_file = WP_CONTENT_DIR . '/pod-aggregator-activation.log';
    if (!file_exists($log_file)) {
        wp_die('No log file found. Try activating the plugin first.');
    }

    header('Content-Type: text/plain');
    readfile($log_file);
    exit;
});
