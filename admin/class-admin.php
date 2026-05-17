<?php
/**
 * POD Aggregator — Network Admin Menu.
 *
 * @package POD_Aggregator\Admin
 */

namespace POD_Aggregator\Admin;

/**
 * Adds the top-level network admin menu and submenu pages.
 *
 * @since 1.0.0
 */
class Admin
{
    /**
     * Return the appropriate capability for the current installation.
     *
     * @return string
     */
    private function cap(): string
    {
        return is_multisite() ? 'manage_network' : 'manage_options';
    }

    /**
     * Add Settings and Dashboard links to the plugin row.
     *
     * @param array $links Existing action links.
     * @return array
     */
    public function add_action_links(array $links): array
    {
        $cap = $this->cap();
        $url = is_network_admin()
            ? network_admin_url('admin.php?page=pod-aggregator-settings')
            : admin_url('admin.php?page=pod-aggregator-settings');

        $links['settings'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Settings', 'pod-aggregator')
        );

        return $links;
    }

    /**
     * Add admin menu pages.
     *
     * @return void
     */
    public function add_menu_pages()
    {
        $cap = $this->cap();

        // Top-level menu.
        add_menu_page(
            __('POD Aggregator', 'pod-aggregator'),
            __('POD Aggregator', 'pod-aggregator'),
            $cap,
            'pod-aggregator',
            [$this, 'render_dashboard_page'],
            'dashicons-images-alt',
            56
        );

        // Dashboard submenu.
        add_submenu_page(
            'pod-aggregator',
            __('Dashboard', 'pod-aggregator'),
            __('Dashboard', 'pod-aggregator'),
            $cap,
            'pod-aggregator',
            [$this, 'render_dashboard_page']
        );

        // Products submenu.
        add_submenu_page(
            'pod-aggregator',
            __('POD Products', 'pod-aggregator'),
            __('POD Products', 'pod-aggregator'),
            $cap,
            'edit.php?post_type=pod_product',
            null
        );

        // Settings submenu.
        add_submenu_page(
            'pod-aggregator',
            __('Settings', 'pod-aggregator'),
            __('Settings', 'pod-aggregator'),
            $cap,
            'pod-aggregator-settings',
            [$this, 'render_settings_page']
        );

        // Sync Log submenu.
        add_submenu_page(
            'pod-aggregator',
            __('Sync Log', 'pod-aggregator'),
            __('Sync Log', 'pod-aggregator'),
            $cap,
            'pod-aggregator-sync-log',
            [$this, 'render_sync_log_page']
        );
    }

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page()
    {
        $providers = [];
        foreach (['printful', 'printify', 'gelato'] as $slug) {
            $providers[$slug] = ['connected' => false, 'name' => ucfirst($slug)];
            try {
                $provider = \POD_Aggregator\pod_aggregator_get_provider($slug);
                $providers[$slug]['connected'] = $provider && $provider->is_configured();
            } catch (\Throwable $e) {
                $providers[$slug]['connected'] = false;
            }
        }

        $stats = $this->get_sync_stats();
        $schedules = $this->get_cron_schedules();

        // Nonce for AJAX sync.
        $sync_nonce = wp_create_nonce('pod_manual_sync');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('POD Aggregator Dashboard', 'pod-aggregator'); ?></h1>

            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Welcome to POD Aggregator!', 'pod-aggregator'); ?></strong>
                    <?php esc_html_e('Connect your store to Print-on-Demand providers to sync products and automate fulfillment.', 'pod-aggregator'); ?>
                </p>
            </div>

            <div class="card" style="max-width:600px; margin-top:20px;">
                <h2><?php esc_html_e('Provider Status', 'pod-aggregator'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Provider', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Status', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Actions', 'pod-aggregator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $slug => $data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                            <td>
                                <?php if ($data['connected']): ?>
                                    <span style="color:green;">&#10003; <?php esc_html_e('Connected', 'pod-aggregator'); ?></span>
                                <?php else: ?>
                                    <span style="color:red;">&#10007; <?php esc_html_e('Not configured', 'pod-aggregator'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(network_admin_url('admin.php?page=pod-aggregator-settings')); ?>" class="button">
                                    <?php esc_html_e('Configure', 'pod-aggregator'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width:600px; margin-top:20px;">
                <h2><?php esc_html_e('Sync Statistics', 'pod-aggregator'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Total sync events', 'pod-aggregator'); ?></td>
                            <td><?php echo esc_html($stats['total'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Successful', 'pod-aggregator'); ?></td>
                            <td><?php echo esc_html($stats['success'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Failed', 'pod-aggregator'); ?></td>
                            <td><?php echo esc_html($stats['error'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Product sync schedule', 'pod-aggregator'); ?></td>
                            <td><?php echo esc_html($schedules['products'] ?? 'Every 6 hours'); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Order sync schedule', 'pod-aggregator'); ?></td>
                            <td><?php echo esc_html($schedules['orders'] ?? 'Every 15 minutes'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="button" id="pod-manual-sync-btn" class="button" data-nonce="<?php echo esc_attr($sync_nonce); ?>">
                        <?php esc_html_e('Sync Products Now', 'pod-aggregator'); ?>
                    </button>
                    <span id="pod-sync-status" style="margin-left:10px;"></span>
                </p>
            </div>
        </div>

        <script>
        (function () {
            var btn = document.getElementById('pod-manual-sync-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                btn.disabled = true;
                var status = document.getElementById('pod-sync-status');
                status.textContent = '<?php esc_attr_e('Syncing…', 'pod-aggregator'); ?>';

                var data = new FormData();
                data.append('action', 'pod_manual_sync');
                data.append('nonce', btn.dataset.nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    btn.disabled = false;
                    if (resp.success) {
                        status.textContent = resp.data.message || '<?php esc_attr_e('Sync complete.', 'pod-aggregator'); ?>';
                        status.style.color = 'green';
                    } else {
                        status.textContent = resp.data.message || '<?php esc_attr_e('Sync failed.', 'pod-aggregator'); ?>';
                        status.style.color = 'red';
                    }
                    // Auto-clear after 5s.
                    setTimeout(function () { status.textContent = ''; }, 5000);
                })
                .catch(function () {
                    btn.disabled = false;
                    status.textContent = '<?php esc_attr_e('Network error.', 'pod-aggregator'); ?>';
                    status.style.color = 'red';
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page()
    {
        // Handled by Settings class via do_settings_sections.
        // Use network admin edit URL when on multisite network admin,
        // otherwise fall back to options.php for single-site.
        $form_action = is_network_admin()
            ? network_admin_url('edit.php?action=updatenetwork')
            : 'options.php';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('POD Aggregator Settings', 'pod-aggregator'); ?></h1>
            <?php settings_errors(\POD_Aggregator\Admin\Settings::SETTINGS_KEY); ?>
            <form action="<?php echo esc_url($form_action); ?>" method="post">
                <?php
                settings_fields('pod_aggregator_settings');
                do_settings_sections('pod_aggregator_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the sync log page.
     *
     * @return void
     */
    public function render_sync_log_page()
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        // Check if table exists.
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            echo '<div class="wrap"><p>' . esc_html__('Sync log table not found. Sync at least once.', 'pod-aggregator') . '</p></div>';
            return;
        }

        $per_page = 20;
        $page     = max(1, intval($_GET['paged'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $orderby  = sanitize_sql_orderby($_GET['orderby'] ?? 'created_at');
        $order    = $_GET['order'] ?? 'DESC';
        $status   = sanitize_text_field($_GET['status'] ?? '');

        $where = '';
        $args  = [];
        if ($status) {
            $where = 'WHERE status = %s';
            $args[] = $status;
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", ...$args)
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                ...array_merge($args, [$per_page, $offset])
            ),
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('POD Sync Log', 'pod-aggregator'); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="pod-aggregator-sync-log" />
                <label>
                    <?php esc_html_e('Filter by status:', 'pod-aggregator'); ?>
                    <select name="status">
                        <option value=""><?php esc_html_e('All', 'pod-aggregator'); ?></option>
                        <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Success', 'pod-aggregator'); ?></option>
                        <option value="error" <?php selected($status, 'error'); ?>><?php esc_html_e('Error', 'pod-aggregator'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'pod-aggregator'); ?></option>
                    </select>
                </label>
                <button type="submit" class="button"><?php esc_html_e('Filter', 'pod-aggregator'); ?></button>
            </form>

            <table class="widefat" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Provider', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Event', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('External ID', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Order ID', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Status', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Created', 'pod-aggregator'); ?></th>
                        <th><?php esc_html_e('Error', 'pod-aggregator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8"><?php esc_html_e('No sync events found.', 'pod-aggregator'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['id']); ?></td>
                                <td><?php echo esc_html($row['provider']); ?></td>
                                <td><?php echo esc_html($row['event_type']); ?></td>
                                <td><?php echo esc_html($row['external_id'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['order_id'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $badge_color = $row['status'] === 'success' ? 'green' : ($row['status'] === 'error' ? 'red' : 'gray');
                                    printf(
                                        '<span style="color:%s">%s</span>',
                                        esc_attr($badge_color),
                                        esc_html($row['status'])
                                    );
                                    ?>
                                </td>
                                <td><?php echo esc_html($row['created_at']); ?></td>
                                <td><?php echo esc_html($row['error_message'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total > $per_page): ?>
                <p>
                    <?php
                    printf(
                        /* translators: %1$d = start, %2$d = end, %3$d = total */
                        esc_html__('Showing %1$d–%2$d of %3$d results', 'pod-aggregator'),
                        $offset + 1,
                        min($offset + $per_page, $total),
                        $total
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Register settings (placeholder — actual fields are in Settings class).
     *
     * @return void
     */
    public function register_settings()
    {
        // Settings fields are registered by the Settings class.
    }

    /**
     * Get sync statistics from the log table.
     *
     * @return array
     */
    private function get_sync_stats(): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['total' => 0, 'success' => 0, 'error' => 0];
        }

        return [
            'total'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'success' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'success'"),
            'error'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'error'"),
        ];
    }

    /**
     * Get current cron schedule descriptions.
     *
     * @return array
     */
    private function get_cron_schedules(): array
    {
        return [
            'products' => __('Every 6 hours', 'pod-aggregator'),
            'orders'   => __('Every 15 minutes', 'pod-aggregator'),
        ];
    }

    /**
     * AJAX handler — manually trigger product sync.
     *
     * Requires manage_network capability and a valid nonce.
     * Responds with JSON: { success: bool, message: string, synced: int }
     *
     * @return void
     */
    public function ajax_manual_sync_products(): void
    {
        check_ajax_referer('pod_manual_sync', 'nonce');

        if (!current_user_can($this->cap())) {
            wp_send_json_error(['message' => __('Permission denied.', 'pod-aggregator')], 403);
            return;
        }

        $scheduler = new \POD_Aggregator\Crons\Scheduler();

        // Sync each configured provider.
        $providers = \POD_Aggregator\pod_aggregator_get_provider();
        $synced = 0;
        $errors = [];

        foreach ($providers as $provider) {
            if (!$provider->is_configured()) {
                continue;
            }

            $slug = $provider->get_slug();

            try {
                // Call sync_products() directly with manual=true for verbose output.
                $scheduler->sync_products(true, $slug);
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = sprintf('%s: %s', ucfirst($slug), $e->getMessage());
            }
        }

        if ($errors) {
            wp_send_json_success([
                'message' => implode('; ', $errors),
                'synced'  => $synced,
            ]);
            return;
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d = number of providers synced */
                _n('Product sync complete for %d provider.', 'Product sync complete for %d providers.', $synced, 'pod-aggregator'),
                $synced
            ),
            'synced' => $synced,
        ]);
    }
}
