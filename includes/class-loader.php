<?php
/**
 * POD Aggregator — Hook loader and registration.
 *
 * @package POD_Aggregator
 */

namespace POD_Aggregator;

/**
 * Loader — registers all WordPress hooks (actions + filters).
 *
 * @since 1.0.0
 */
class Loader
{
    /**
     * Registered actions.
     *
     * @var array
     */
    protected $actions = [];

    /**
     * Registered filters.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Register a WordPress action hook.
     *
     * Supports two calling conventions:
     *   add_action('hook', $object, 'method')           — object + method name
     *   add_action('hook', $callable, $priority, $args) — direct callable (Closure, etc.)
     *
     * @param string        $hook          Hook name.
     * @param object|callable $component   Component object, or callable if used directly.
     * @param string|int    $callback      Method name on component, or priority if callable.
     * @param int           $priority      Priority (default: 10).
     * @param int           $accepted_args Number of accepted args (default: 1).
     * @return void
     */
    public function add_action($hook, $component, $callback = null, $priority = 10, $accepted_args = 1)
    {
        if ($callback === null || is_int($callback)) {
            // Direct callable: add_action('hook', $callable, $priority, $accepted_args)
            $this->actions = $this->add($this->actions, $hook, null, $component, $callback ?? $priority, $priority);
            return;
        }
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Register a WordPress filter hook.
     *
     * Supports two calling conventions:
     *   add_filter('hook', $object, 'method')           — object + method name
     *   add_filter('hook', $callable, $priority, $args) — direct callable (Closure, etc.)
     *
     * @param string        $hook          Hook name.
     * @param object|callable $component   Component object, or callable if used directly.
     * @param string|int    $callback      Method name on component, or priority if callable.
     * @param int           $priority      Priority (default: 10).
     * @param int           $accepted_args Number of accepted args (default: 1).
     * @return void
     */
    public function add_filter($hook, $component, $callback = null, $priority = 10, $accepted_args = 1)
    {
        if ($callback === null || is_int($callback)) {
            // Direct callable: add_filter('hook', $callable, $priority, $accepted_args)
            $this->filters = $this->add($this->filters, $hook, null, $component, $callback ?? $priority, $priority);
            return;
        }
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Internal helper to register a hook.
     *
     * @param array         $hooks         Existing hooks array.
     * @param string        $hook          Hook name.
     * @param object|null   $component     Component object, or null for direct callables.
     * @param string|callable $callback    Method name, or the callable itself.
     * @param int           $priority      Priority.
     * @param int           $accepted_args Accepted args.
     * @return array
     */
    private function add(array $hooks, $hook, $component, $callback, $priority, $accepted_args): array
    {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
        return $hooks;
    }

    /**
     * Register all hooks with WordPress.
     *
     * @return void
     */
    public function run()
    {
        // ---- Core / Init ----
        $this->add_filter('init', new \POD_Aggregator\CPT_Registrar(), 'register_post_types');

        // Deferred flush of rewrite rules after first 'init' (set by activation hook).
        $this->add_action('init', function () {
            if (get_site_transient('pod_aggregator_flush_rewrite')) {
                delete_site_transient('pod_aggregator_flush_rewrite');
                flush_rewrite_rules();
            }
        });

        // ---- Custom cron interval ----
        $this->add_filter('cron_schedules', function (array $schedules): array {
            $settings = get_site_option('pod_aggregator_settings', []);
            $interval_hours = max(1, (int) ($settings['sync_interval_hours'] ?? 6));
            $interval_seconds = $interval_hours * HOUR_IN_SECONDS;

            $schedules['pod_aggregator_sync_interval'] = [
                'interval' => $interval_seconds,
                'display'  => sprintf(
                    /* translators: %d = number of hours */
                    _n('Every hour', 'Every %d hours', $interval_hours, 'pod-aggregator'),
                    $interval_hours
                ),
            ];

            // Order status sync runs more frequently.
            $schedules['pod_aggregator_order_sync_interval'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 minutes', 'pod-aggregator'),
            ];

            return $schedules;
        });

        // ---- Admin ----
        if (is_admin()) {
            $admin = new \POD_Aggregator\Admin\Admin();
            $this->add_action('admin_menu', $admin, 'add_menu_pages');
            $this->add_action('admin_init', $admin, 'register_settings');
            $this->add_action('wp_ajax_pod_manual_sync', $admin, 'ajax_manual_sync_products');

            $settings = new \POD_Aggregator\Admin\Settings();
            $this->add_action('admin_init', $settings, 'register_settings');

            // Preset design templates admin UI (P3-A).
            $presets = new \POD_Aggregator\Admin\Preset_Templates();
            $this->add_action('admin_menu', $presets, 'register_menu');
            $this->add_action('admin_enqueue_scripts', $presets, 'enqueue_assets');
            $this->add_action('wp_ajax_pod_save_preset', $presets, 'ajax_save_preset');
            $this->add_action('wp_ajax_pod_delete_preset', $presets, 'ajax_delete_preset');
            $this->add_filter('rest_api_init', $presets, 'register_rest_routes');
        }

        // ---- REST API (Webhooks) ----
        $this->add_filter('rest_api_init', new \POD_Aggregator\REST\Controller(), 'register_routes');

        // ---- POD Design REST API (Customizer) ----
        $this->add_filter('rest_api_init', new \POD_Aggregator\ProductCustomizer\REST_Controller(), 'register_routes');

        // ---- POD Design CPT (registered on 'init') ----
        $this->add_filter('init', new \POD_Aggregator\ProductCustomizer\Design_Storage(), 'register_post_type');

        // ---- WooCommerce integration hooks ----
        $wc = new \POD_Aggregator\WooCommerce\Integration();
        // Add product data tab on WooCommerce product edit screen.
        $this->add_action('woocommerce_product_data_tabs', $wc, 'product_data_tab');
        $this->add_action('woocommerce_product_data_panels', $wc, 'product_data_panel');
        // Save POD product meta on product save.
        $this->add_action('woocommerce_process_product_meta', $wc, 'save_product_meta');
        // Add POD product to cart.
        $this->add_action('woocommerce_add_cart_item_data', $wc, 'add_cart_item_data', 10, 3);
        // Display customization in cart.
        $this->add_filter('woocommerce_get_item_data', $wc, 'display_cart_item_data', 10, 2);
        // Add order item meta on checkout.
        $this->add_action('woocommerce_checkout_create_order_line_item', $wc, 'create_order_line_item', 10, 4);
        // Handle WooCommerce order status change → send to POD provider.
        $this->add_action('woocommerce_checkout_order_processed', $wc, 'forward_order_to_provider', 10, 3);
        // Register order action button.
        $this->add_filter('woocommerce_order_actions', $wc, 'add_order_actions');
        $this->add_action('woocommerce_order_action_pod_resend', $wc, 'resend_order_to_provider');

        // ---- Cron ----
        $cron = new \POD_Aggregator\Crons\Scheduler();
        // Product sync — runs on the pod_aggregator_sync_interval schedule.
        $this->add_action('pod_aggregator_sync_products', $cron, 'sync_products');
        // Order status sync — runs every 15 minutes.
        $this->add_action('pod_aggregator_sync_orders', $cron, 'sync_order_status');
        // Re-schedule crons when sync interval setting changes.
        $this->add_action('update_site_option_pod_aggregator_settings', function ($option, $value) {
            // Clear old scheduled events so the next run picks up the new interval.
            \POD_Aggregator\Crons\Scheduler::clear_crons();
            \POD_Aggregator\Crons\Scheduler::schedule_crons();
        }, 10, 2);

        // ---- Frontend shortcodes ----
        if (!is_admin()) {
            $shortcodes = new \POD_Aggregator\Public\Shortcodes();
            $this->add_shortcode('pod_customizer', $shortcodes, 'render_customizer');
            $this->add_shortcode('pod_catalog', $shortcodes, 'render_catalog');
        }

        // ---- Register all collected hooks ----
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }

    /**
     * Register a shortcode.
     *
     * @param string $tag      Shortcode tag.
     * @param object $component Component with the callback method.
     * @param string $callback  Method name.
     * @return void
     */
    private function add_shortcode($tag, $component, $callback)
    {
        add_shortcode($tag, [$component, $callback]);
    }
}
