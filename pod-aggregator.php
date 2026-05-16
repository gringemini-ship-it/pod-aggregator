<?php
/**
 * Plugin Name:       POD Aggregator
 * Plugin URI:        https://github.com/gringemini-ship-it/pod-aggregator
 * Description:       Connect your WordPress store to multiple Print-on-Demand providers (Printful, Printify, Gelato and more). Sync products, personalize with the built-in customizer, and automate order fulfillment.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            POD Aggregator Team
 * Author URI:        https://github.com/gringemini-ship-it/pod-aggregator
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pod-aggregator
 * Domain Path:       /languages
 * Network:           true
 *
 * @package           POD_Aggregator
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('POD_AGGREGATOR_VERSION', '1.0.0');
define('POD_AGGREGATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POD_AGGREGATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POD_AGGREGATOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader if present (must be top-level so classes
// are available during activation/deactivation hooks).
if (file_exists(POD_AGGREGATOR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once POD_AGGREGATOR_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load all class files at top-level so they are available during
// activation, deactivation, and normal plugin loading. Order matters
// for dependencies: Provider_Interface before provider adapters.
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/class-pod-provider.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/class-cpt-registrar.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/providers/class-printful.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/providers/class-printify.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/providers/class-gelato.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/CLI/class-cli.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/WooCommerce/class-integration.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/product-customizer/class-design-element.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/product-customizer/class-design.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/product-customizer/class-design-storage.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/product-customizer/class-print-generator.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/product-customizer/class-rest-controller.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/REST/class-controller.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/Crons/class-scheduler.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/class-ajax.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'admin/class-admin.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'admin/class-settings.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'admin/class-preset-templates.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'public/class-customizer-editor.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'public/class-shortcodes.php';
require_once POD_AGGREGATOR_PLUGIN_DIR . 'includes/class-loader.php';

/**
 * Code that runs during the plugin loading.
 */
function pod_aggregator_load()
{
    // Load translated strings.
    load_plugin_textdomain(
        'pod-aggregator',
        false,
        dirname(POD_AGGREGATOR_PLUGIN_BASENAME) . '/languages'
    );

    // Initialize the loader.
    $loader = new POD_Aggregator\Loader();
    $loader->run();
}
add_action('plugins_loaded', 'pod_aggregator_load');

/**
 * Register activation hook at top-level (not inside any other hook).
 *
 * @return void
 */
register_activation_hook(
    __FILE__,
    function () {
        // Check WooCommerce is active (network-wide).
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(POD_AGGREGATOR_PLUGIN_BASENAME);
            wp_die(
                esc_html__('POD Aggregator requires WooCommerce to be active.', 'pod-aggregator'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Store schema version.
        update_site_option('pod_aggregator_schema_version', POD_AGGREGATOR_VERSION);

        // Create sync log table.
        pod_aggregator_create_tables();

        // Set default options.
        if (!get_site_option('pod_aggregator_settings')) {
            add_site_option('pod_aggregator_settings', []);
        }

        // Schedule cron events.
        // Note: flush_rewrite_rules() is deferred to 'init' via a separate
        // hook — see below for the deferred approach.
        \POD_Aggregator\Crons\Scheduler::schedule_crons();

        // Set a transient flag so 'init' can flush rewrite rules once.
        set_site_transient('pod_aggregator_flush_rewrite', true, 60);
    }
);

/**
 * Register deactivation hook at top-level.
 *
 * @return void
 */
register_deactivation_hook(
    __FILE__,
    function () {
        // Clear scheduled cron events.
        \POD_Aggregator\Crons\Scheduler::clear_crons();

        // Flush rewrite rules directly — 'init' has already run by deactivation.
        flush_rewrite_rules();
    }
);

/**
 * Create plugin database tables.
 *
 * @return void
 */
function pod_aggregator_create_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Order sync log table.
    $table_sync_log = $wpdb->base_prefix . 'pod_aggregator_sync_log';
    $sql_sync_log   = "CREATE TABLE {$table_sync_log} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        provider        VARCHAR(50) NOT NULL DEFAULT 'printful',
        event_type      VARCHAR(50) NOT NULL,
        external_id     VARCHAR(255) DEFAULT NULL,
        order_id        BIGINT UNSIGNED DEFAULT NULL,
        status          VARCHAR(50) DEFAULT 'pending',
        payload         LONGTEXT DEFAULT NULL,
        error_message   TEXT DEFAULT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_provider (provider),
        KEY idx_event_type (event_type),
        KEY idx_external_id (external_id),
        KEY idx_order_id (order_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // Design presets table.
    $table_designs = $wpdb->base_prefix . 'pod_aggregator_designs';
    $sql_designs   = "CREATE TABLE {$table_designs} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        design_name     VARCHAR(255) NOT NULL,
        provider        VARCHAR(50) NOT NULL DEFAULT 'printful',
        provider_product_id VARCHAR(255) NOT NULL,
        design_data     LONGTEXT NOT NULL COMMENT 'JSON: layers, colors, fonts, positions',
        thumbnail_url   VARCHAR(1000) DEFAULT NULL,
        is_preset       TINYINT(1) NOT NULL DEFAULT 0,
        created_by      BIGINT UNSIGNED DEFAULT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_provider_product (provider, provider_product_id),
        KEY idx_is_preset (is_preset)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_sync_log);
    dbDelta($sql_designs);

    // Store that tables were created.
    update_site_option('pod_aggregator_tables_created', true);
}

/**
