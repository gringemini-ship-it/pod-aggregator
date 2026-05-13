<?php
/**
 * POD Aggregator — Test Connection Command.
 *
 * @package POD_Aggregator\CLI
 */

namespace POD_Aggregator\CLI\Commands;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Test API connection to a POD provider.
 *
 * ## EXAMPLES
 *
 *     # Test Printify connection.
 *     wp pod testConnection --provider=printify
 *
 *     # Test all providers.
 *     wp pod testConnection
 *
 * ## OPTIONS
 *
 * [--provider=<provider>]
 * : Provider to test (printful, printify, gelato). Tests all if omitted.
 */
class Test_Connection
{
    /**
     * Run the testConnection command.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     * @return void
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $provider_slug = $assoc_args['provider'] ?? null;

        if ($provider_slug) {
            $providers = [pod_aggregator_get_provider($provider_slug)];
        } else {
            $providers = pod_aggregator_get_provider();
        }

        if (empty($providers)) {
            \WP_CLI::error('No providers configured. Add API keys in Settings.');
            return;
        }

        $all_ok = true;

        foreach ($providers as $provider) {
            if (!$provider) {
                \WP_CLI::error('Unknown provider.');
                $all_ok = false;
                continue;
            }

            $slug = $provider->get_slug();

            if (!$provider->is_configured()) {
                \WP_CLI::warning("Provider '{$slug}' is not configured (no API key). Skipping.");
                continue;
            }

            \WP_CLI::line("Testing {$provider->get_name()} ({$slug})...");

            // Make a lightweight API call to verify credentials.
            $products = $provider->get_products();

            if (is_wp_error($products)) {
                \WP_CLI::error("  ✗ Connection failed: " . $products->get_error_message());
                $all_ok = false;
                continue;
            }

            $count = is_array($products) ? count($products) : 0;
            \WP_CLI::success("  ✓ Connected. {$count} products available.");
        }

        if (!$all_ok) {
            \WP_CLI::beep();
        }
    }
}
