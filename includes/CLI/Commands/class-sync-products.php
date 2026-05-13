<?php
/**
 * POD Aggregator — Sync Products Command.
 *
 * @package POD_Aggregator\CLI
 */

namespace POD_Aggregator\CLI\Commands;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Sync products from POD providers into the local POD catalog CPT.
 *
 * ## EXAMPLES
 *
 *     # Sync all configured providers.
 *     wp pod syncProducts
 *
 *     # Sync only Printify.
 *     wp pod syncProducts --provider=printify
 *
 *     # Sync Printify, limit 10 products.
 *     wp pod syncProducts --provider=printify --limit=10
 *
 *     # Sync all providers, verbose output.
 *     wp pod syncProducts --verbose
 *
 * ## OPTIONS
 *
 * [--provider=<provider>]
 * : Provider slug to sync (printful, printify, gelato). Syncs all configured providers if omitted.
 *
 * [--limit=<limit>]
 * : Maximum number of products to sync per provider (default: unlimited).
 *
 * [--verbose]
 * : Output detailed progress for each product.
 */
class Sync_Products
{
    /** @var \POD_Aggregator\Crons\Scheduler */
    private $scheduler;

    public function __construct()
    {
        $this->scheduler = new \POD_Aggregator\Crons\Scheduler();
    }

    /**
     * Run the syncProducts command.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     * @return void
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $provider_slug = $assoc_args['provider'] ?? null;
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : null;
        $verbose = isset($assoc_args['verbose']);

        // Load all configured providers if no specific one requested.
        if ($provider_slug) {
            $providers = [pod_aggregator_get_provider($provider_slug)];
            if (!$providers[0]) {
                \WP_CLI::error("Unknown provider: {$provider_slug}");
                return;
            }
        } else {
            $providers = pod_aggregator_get_provider();
            if (empty($providers)) {
                \WP_CLI::error('No providers configured. Add API keys in Settings.');
                return;
            }
        }

        $total_synced = 0;
        $total_failed = 0;

        foreach ($providers as $provider) {
            if (!$provider || !$provider->is_configured()) {
                $slug = $provider ? $provider->get_slug() : 'unknown';
                \WP_CLI::warning("Provider '{$slug}' is not configured. Skipping.");
                continue;
            }

            $slug = $provider->get_slug();
            \WP_CLI::line("=== Syncing {$provider->get_name()} ({$slug}) ===");

            $products = $provider->get_products();

            if (is_wp_error($products)) {
                \WP_CLI::error("Failed to fetch products: " . $products->get_error_message());
                continue;
            }

            if (empty($products)) {
                \WP_CLI::warning('No products returned from provider.');
                continue;
            }

            $count = 0;
            $errors = 0;
            $products_to_sync = $products;
            if ($limit !== null) {
                $products_to_sync = array_slice($products, 0, $limit);
            }

            foreach ($products_to_sync as $product_data) {
                $result = $this->scheduler->upsert_pod_product($slug, $product_data);
                if ($result) {
                    $count++;
                    if ($verbose) {
                        \WP_CLI::success("  [{$slug}] {$product_data['name']} ({$product_data['provider_product_id']})");
                    }
                } else {
                    $errors++;
                    \WP_CLI::warning("  [{$slug}] Failed: {$product_data['name']}");
                }
            }

            $total_synced += $count;
            $total_failed += $errors;

            \WP_CLI::success("{$provider->get_name()}: {$count} products synced" . ($errors ? ", {$errors} failed" : ''));
        }

        // Update last sync time.
        update_site_option('pod_aggregator_last_product_sync', current_time('mysql'));

        \WP_CLI::line('');
        \WP_CLI::success("Done. Total: {$total_synced} synced, {$total_failed} failed.");
    }
}
