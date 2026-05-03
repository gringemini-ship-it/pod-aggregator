<?php
/**
 * POD Aggregator — WP-CLI Integration.
 *
 * Registers the `wp pod` command group and all subcommands.
 * Requires WP-CLI to be available (checked via WP_CLI constant).
 *
 * @package POD_Aggregator\CLI
 */

namespace POD_Aggregator\CLI;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Register all POD Aggregator WP-CLI commands.
 *
 * @return void
 */
function register_commands(): void
{
    \WP_CLI::add_command('pod syncProducts', '\\POD_Aggregator\\CLI\\Commands\\Sync_Products');
    \WP_CLI::add_command('pod syncOrders', '\\POD_Aggregator\\CLI\\Commands\\Sync_Orders');
    \WP_CLI::add_command('pod testConnection', '\\POD_Aggregator\\CLI\\Commands\\Test_Connection');
}

// Register when WP_CLI is ready.
add_action('plugins_loaded', __NAMESPACE__ . '\\register_commands', 5);
