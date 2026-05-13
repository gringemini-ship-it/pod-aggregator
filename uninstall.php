<?php
/**
 * POD Aggregator — Uninstall
 *
 * Runs when the plugin is deleted (not just deactivated).
 * Removes ALL plugin-created data: options, tables, CPTs, transients.
 *
 * @package POD_Aggregator
 */

// Exit if uninstall not called from WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up site options.
delete_site_option('pod_aggregator_settings');
delete_site_option('pod_aggregator_schema_version');
delete_site_option('pod_aggregator_tables_created');
delete_site_option('pod_aggregator_last_product_sync');
delete_site_option('pod_aggregator_last_order_sync');

// Clear transients with known prefix.
global $wpdb;
if (is_multisite()) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND site_id = %d",
            $wpdb->esc_like('_site_transient_pod_agg_printful_products_') . '%',
            $wpdb->siteid
        )
    );
} else {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_pod_agg_printful_products_') . '%'
        )
    );
}

// Clear scheduled cron events.
wp_clear_scheduled_hook('pod_aggregator_sync_products');
wp_clear_scheduled_hook('pod_aggregator_sync_orders');

// Remove custom tables.
global $wpdb;

$table_sync_log = $wpdb->base_prefix . 'pod_aggregator_sync_log';
$table_designs  = $wpdb->base_prefix . 'pod_aggregator_designs';

$wpdb->query("DROP TABLE IF EXISTS {$table_sync_log}");
$wpdb->query("DROP TABLE IF EXISTS {$table_designs}");

// Delete all POD product CPT posts and their meta.
$pods = get_posts([
    'post_type'      => 'pod_product',
    'post_status'   => 'any',
    'posts_per_page' => -1,
    'fields'        => 'ids',
]);

foreach ($pods as $post_id) {
    wp_delete_post($post_id, true);
}
