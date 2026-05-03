<?php
/**
 * POD Aggregator — Sync Orders Command.
 *
 * @package POD_Aggregator\CLI
 */

namespace POD_Aggregator\CLI\Commands;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Sync order status from POD providers back into WooCommerce orders.
 *
 * ## EXAMPLES
 *
 *     # Sync all pending orders across all providers.
 *     wp pod syncOrders
 *
 *     # Sync only Printify orders.
 *     wp pod syncOrders --provider=printful
 *
 * ## OPTIONS
 *
 * [--provider=<provider>]
 * : Filter by specific provider (printful, printify, gelato).
 */
class Sync_Orders
{
    /**
     * Run the syncOrders command.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     * @return void
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        global $wpdb;

        $provider_filter = $assoc_args['provider'] ?? null;
        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            \WP_CLI::error('Sync log table does not exist. Run plugin activation first.');
            return;
        }

        $sql = "SELECT * FROM {$table} WHERE event_type = 'submit_order' AND status = 'success' ORDER BY created_at DESC LIMIT 50";
        if ($provider_filter) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE event_type = 'submit_order' AND status = 'success' AND provider = %s ORDER BY created_at DESC LIMIT 50",
                $provider_filter
            );
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (empty($rows)) {
            \WP_CLI::line('No pending orders to sync.');
            return;
        }

        $updated = 0;
        $errors = 0;

        foreach ($rows as $row) {
            if (empty($row['external_id']) || empty($row['order_id'])) {
                continue;
            }

            $provider = pod_aggregator_get_provider($row['provider']);
            if (!$provider) {
                continue;
            }

            $status_data = $provider->get_order_status($row['external_id']);

            if (is_wp_error($status_data)) {
                $errors++;
                \WP_CLI::warning("Order #{$row['order_id']}: " . $status_data->get_error_message());
                continue;
            }

            $order = wc_get_order((int) $row['order_id']);
            if (!$order) {
                $errors++;
                \WP_CLI::warning("Order #{$row['order_id']}: WooCommerce order not found.");
                continue;
            }

            $note_parts = ["{$provider->get_name()} status: {$status_data['status']}"];
            if (!empty($status_data['tracking_number'])) {
                $order->update_meta_data('_pod_tracking_number', $status_data['tracking_number']);
                $order->update_meta_data('_pod_tracking_url', $status_data['tracking_url'] ?? '');
                $note_parts[] = "Tracking: {$status_data['tracking_number']}";
            }

            $order->save();
            $updated++;

            \WP_CLI::success("Order #{$row['order_id']}: " . implode(' | ', $note_parts));
        }

        update_site_option('pod_aggregator_last_order_sync', current_time('mysql'));

        \WP_CLI::line('');
        \WP_CLI::success("Done. {$updated} orders updated, {$errors} errors.");
    }
}
