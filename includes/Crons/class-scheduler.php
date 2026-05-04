<?php
/**
 * POD Aggregator — Cron Scheduler.
 *
 * @package POD_Aggregator\Crons
 */

namespace POD_Aggregator\Crons;

/**
 * Handles scheduled product and order sync jobs.
 * Uses WordPress cron with the "schedule" option for interval control.
 *
 * @since 1.0.0
 */
class Scheduler
{
    /** Cron hook names. */
    public const HOOK_PRODUCT_SYNC = 'pod_aggregator_sync_products';
    public const HOOK_ORDER_SYNC   = 'pod_aggregator_sync_orders';

    /**
     * Schedule product catalog sync.
     *
     * @param bool   $manual        If true, run immediately and skip auto-sync check.
     * @param string $provider_slug  If provided, sync only this provider.
     * @return void
     */
    public function sync_products(bool $manual = false, string $provider_slug = '')
    {
        $settings = get_site_option('pod_aggregator_settings', []);

        if (!$manual && empty($settings['auto_sync_enabled'])) {
            return;
        }

        $all_providers = \POD_Aggregator\pod_aggregator_get_provider();
        if (empty($all_providers)) {
            return;
        }

        foreach ($all_providers as $slug => $provider) {
            // If a specific provider was requested, skip all others.
            if ($provider_slug !== '' && $slug !== $provider_slug) {
                continue;
            }

            if (!$provider || !$provider->is_configured()) {
                continue;
            }

            $products = $provider->get_products();

            if (is_wp_error($products)) {
                $this->log('error', $slug, 'sync_products', null, null, $products->get_error_message());
                continue;
            }

            $count = 0;
            foreach ($products as $product_data) {
                $this->upsert_pod_product($slug, $product_data);
                $count++;
            }

            $this->log('success', $slug, 'sync_products', null, null, "Synced {$count} products");
        }

        // Update last sync time.
        update_site_option('pod_aggregator_last_product_sync', current_time('mysql'));
    }

    /**
     * Sync order status from POD providers back to WooCommerce.
     *
     * @return void
     */
    public function sync_order_status()
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        // Get pending/processing orders that have an external ID.
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE event_type = 'submit_order' AND status = 'success' ORDER BY created_at DESC LIMIT 50",
            ARRAY_A
        );

        foreach ($rows as $row) {
            if (empty($row['external_id']) || empty($row['order_id'])) {
                continue;
            }

            $provider = \POD_Aggregator\pod_aggregator_get_provider($row['provider']);
            if (!$provider) {
                continue;
            }

            $status_data = $provider->get_order_status($row['external_id']);

            if (is_wp_error($status_data)) {
                continue;
            }

            // If tracking is available, update the WooCommerce order.
            if (!empty($status_data['tracking_number'])) {
                $order = wc_get_order((int) $row['order_id']);
                if ($order) {
                    $order->update_meta_data('_pod_tracking_number', $status_data['tracking_number']);
                    $order->update_meta_data('_pod_tracking_url', $status_data['tracking_url'] ?? '');
                    $order->save();
                }
            }
        }

        update_site_option('pod_aggregator_last_order_sync', current_time('mysql'));
    }

    /**
     * Schedule all cron events. Called on activation and when settings change.
     * Uses the 'pod_aggregator_sync_interval' schedule registered via cron_schedules.
     *
     * @return void
     */
    public static function schedule_crons()
    {
        $interval = 'pod_aggregator_sync_interval';

        if (!wp_next_scheduled(self::HOOK_PRODUCT_SYNC)) {
            wp_schedule_event(time(), $interval, self::HOOK_PRODUCT_SYNC);
        }

        if (!wp_next_scheduled(self::HOOK_ORDER_SYNC)) {
            wp_schedule_event(time(), $interval, self::HOOK_ORDER_SYNC);
        }
    }

    /**
     * Clear all scheduled cron events. Called on deactivation.
     *
     * @return void
     */
    public static function clear_crons()
    {
        wp_clear_scheduled_hook(self::HOOK_PRODUCT_SYNC);
        wp_clear_scheduled_hook(self::HOOK_ORDER_SYNC);
    }

    // -------------------------------------------------------------------------
    // POD Product upsert
    // -------------------------------------------------------------------------

    /**
     * Insert or update a POD product CPT entry.
     *
     * @param string $provider_slug Provider slug.
     * @param array  $product_data Normalized product data.
     * @return int Post ID.
     */
    public function upsert_pod_product(string $provider_slug, array $product_data): int
    {
        // Check if we already have this product.
        $existing = $this->find_pod_product($provider_slug, $product_data['provider_product_id']);

        $post_data = [
            'post_type'    => 'pod_product',
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field($product_data['name']),
        ];

        if ($existing) {
            $post_data['ID'] = $existing;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return 0;
        }

        // Store all normalized data as post meta.
        update_post_meta($post_id, '_pod_provider', $provider_slug);
        update_post_meta($post_id, '_pod_provider_product_id', $product_data['provider_product_id']);
        update_post_meta($post_id, '_pod_normalized_data', wp_json_encode($product_data));
        update_post_meta($post_id, '_pod_thumbnail_url', esc_url_raw($product_data['thumbnail_url'] ?? ''));
        update_post_meta($post_id, '_pod_last_synced', current_time('mysql'));

        return (int) $post_id;
    }

    /**
     * Find an existing POD product CPT by provider + provider product ID.
     *
     * @param string $provider_slug      Provider slug.
     * @param string $provider_product_id Provider's product ID.
     * @return int|null Post ID or null.
     */
    private function find_pod_product(string $provider_slug, string $provider_product_id): ?int
    {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_pod_provider_product_id'
               AND meta_value = %s
             LIMIT 1",
            $provider_product_id
        ));

        return $post_id ? (int) $post_id : null;
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Log a sync event to the custom table.
     *
     * @param string       $status
     * @param string       $provider
     * @param string       $event_type
     * @param string|null  $external_id
     * @param int|null     $order_id
     * @param string|null  $message
     * @return void
     */
    private function log(
        string $status,
        string $provider,
        string $event_type,
        ?string $external_id,
        ?int $order_id,
        ?string $message = null
    ) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'provider'      => $provider,
                'event_type'   => $event_type,
                'external_id'  => $external_id,
                'order_id'     => $order_id,
                'status'       => $status,
                'error_message'=> $message,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
}
