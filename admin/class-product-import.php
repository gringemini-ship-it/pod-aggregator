<?php
/**
 * POD Aggregator — Product Import Admin Page.
 *
 * Catalog browser for synced POD products with one-click WooCommerce import.
 *
 * @package POD_Aggregator\Admin
 */

namespace POD_Aggregator\Admin;

/**
 * Renders the product import page and handles AJAX import requests.
 *
 * @since 1.0.8
 */
class Product_Import
{
    /** Per-page default. */
    private const PER_PAGE = 20;

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
     * Register the submenu page.
     *
     * @return void
     */
    public function register_menu()
    {
        $cap = $this->cap();

        add_submenu_page(
            'pod-aggregator',
            __('Import Products', 'pod-aggregator'),
            __('Import Products', 'pod-aggregator'),
            $cap,
            'pod-aggregator-import',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin assets for the import page.
     *
     * @param string $hook_suffix Current admin page.
     * @return void
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        if (strpos($hook_suffix, 'pod-aggregator-import') === false) {
            return;
        }

        wp_enqueue_style(
            'pod-aggregator-import',
            POD_AGGREGATOR_PLUGIN_URL . 'assets/css/admin-import.css',
            [],
            POD_AGGREGATOR_VERSION
        );
    }

    /**
     * Render the product import catalog page.
     *
     * @return void
     */
    public function render_page()
    {
        $page     = max(1, intval($_GET['paged'] ?? 1));
        $provider = sanitize_text_field($_GET['provider'] ?? '');
        $status   = sanitize_text_field($_GET['import_status'] ?? '');

        $results = $this->query_pod_products($page, $provider, $status);
        $products = $results['products'];
        $total    = $results['total'];
        $pages    = ceil($total / self::PER_PAGE);

        $nonce = wp_create_nonce('pod_import_product');
        ?>
        <div class="wrap pod-import-wrap">
            <h1><?php esc_html_e('Import POD Products to Store', 'pod-aggregator'); ?></h1>
            <p class="description">
                <?php esc_html_e('Synced products from your POD providers appear below. Click "Import" to create a WooCommerce product with all variants and pricing.', 'pod-aggregator'); ?>
            </p>

            <form method="get" class="pod-import-filters">
                <input type="hidden" name="page" value="pod-aggregator-import" />

                <label>
                    <?php esc_html_e('Provider:', 'pod-aggregator'); ?>
                    <select name="provider">
                        <option value=""><?php esc_html_e('All providers', 'pod-aggregator'); ?></option>
                        <option value="printful" <?php selected($provider, 'printful'); ?>><?php esc_html_e('Printful', 'pod-aggregator'); ?></option>
                        <option value="printify" <?php selected($provider, 'printify'); ?>><?php esc_html_e('Printify', 'pod-aggregator'); ?></option>
                        <option value="gelato" <?php selected($provider, 'gelato'); ?>><?php esc_html_e('Gelato', 'pod-aggregator'); ?></option>
                    </select>
                </label>

                <label>
                    <?php esc_html_e('Status:', 'pod-aggregator'); ?>
                    <select name="import_status">
                        <option value=""><?php esc_html_e('All', 'pod-aggregator'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Not imported', 'pod-aggregator'); ?></option>
                        <option value="imported" <?php selected($status, 'imported'); ?>><?php esc_html_e('Imported', 'pod-aggregator'); ?></option>
                    </select>
                </label>

                <button type="submit" class="button"><?php esc_html_e('Filter', 'pod-aggregator'); ?></button>

                <?php if ($provider): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pod-aggregator-sync-log')); ?>" class="button" style="margin-left:8px;">
                        <?php esc_html_e('View Sync Log', 'pod-aggregator'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <?php if (empty($products)): ?>
                <div class="notice notice-warning" style="margin-top:15px;">
                    <p>
                        <?php esc_html_e('No POD products found.', 'pod-aggregator'); ?>
                        <?php if (!$provider || $provider === 'printful'): ?>
                            <?php
                            printf(
                                /* translators: %s = settings URL */
                                esc_html__('Make sure your API key is configured in %s, then sync products from the Dashboard.', 'pod-aggregator'),
                                '<a href="' . esc_url(admin_url('admin.php?page=pod-aggregator-settings')) . '">' . esc_html__('Settings', 'pod-aggregator') . '</a>'
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="widefat striped pod-import-table" style="margin-top:15px;">
                    <thead>
                        <tr>
                            <th style="width:60px;"><?php esc_html_e('Image', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Product', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Provider', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Variants', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Status', 'pod-aggregator'); ?></th>
                            <th><?php esc_html_e('Actions', 'pod-aggregator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php $this->render_product_row($product, $nonce); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php echo esc_html(sprintf(
                                    /* translators: %d = total count */
                                    _n('%d item', '%d items', $total, 'pod-aggregator'),
                                    $total
                                )); ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $base = add_query_arg(['paged' => '%#%']);
                                echo wp_kses_post(paginate_links([
                                    'base'      => $base,
                                    'format'    => '',
                                    'current'   => $page,
                                    'total'     => $pages,
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                ]));
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            document.querySelectorAll('.pod-import-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.disabled) return;
                    btn.disabled = true;
                    var original = btn.textContent;
                    btn.textContent = '<?php echo esc_js(__('Importing…', 'pod-aggregator')); ?>';

                    var row = btn.closest('tr');
                    var statusEl = row.querySelector('.pod-import-status');

                    var data = new FormData();
                    data.append('action', 'pod_import_product');
                    data.append('nonce', btn.dataset.nonce);
                    data.append('pod_product_id', btn.dataset.productId);

                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: data
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        if (resp.success) {
                            statusEl.innerHTML = '<span style="color:green;">&#10003; ' + resp.data.status + '</span>';
                            statusEl.innerHTML += ' <a href="' + resp.data.edit_url + '" class="button button-small"><?php echo esc_js(__('View', 'pod-aggregator')); ?></a>';
                            btn.remove();
                        } else {
                            statusEl.innerHTML = '<span style="color:red;">&#10007; ' + resp.data.message + '</span>';
                            btn.disabled = false;
                            btn.textContent = original;
                        }
                    })
                    .catch(function () {
                        statusEl.innerHTML = '<span style="color:red;"><?php echo esc_js(__('Network error.', 'pod-aggregator')); ?></span>';
                        btn.disabled = false;
                        btn.textContent = original;
                    });
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Render a single product row.
     *
     * @param array  $product Row from query_pod_products.
     * @param string $nonce   AJAX nonce.
     * @return void
     */
    private function render_product_row(array $product, string $nonce): void
    {
        $thumb     = $product['thumbnail_url'] ?: '';
        $name      = esc_html($product['name']);
        $provider  = esc_html(ucfirst($product['provider']));
        $variants  = (int) $product['variant_count'];
        $imported  = (int) $product['imported_to'];
        $pod_id    = (int) $product['id'];
        $wc_id     = $imported ? (int) $product['imported_to'] : 0;

        ?>
        <tr>
            <td>
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;" />
                <?php else: ?>
                    <div style="width:50px;height:50px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:20px;">?</div>
                <?php endif; ?>
            </td>
            <td>
                <strong><?php echo $name; ?></strong>
                <br /><small><?php echo esc_html(sprintf(__('ID: %s', 'pod-aggregator'), $product['provider_product_id'])); ?></small>
            </td>
            <td><?php echo $provider; ?></td>
            <td><?php echo esc_html(sprintf(_n('%d variant', '%d variants', $variants, 'pod-aggregator'), $variants)); ?></td>
            <td class="pod-import-status">
                <?php if ($imported && $wc_id && get_post($wc_id)): ?>
                    <span style="color:green;">&#10003; <?php esc_html_e('Imported', 'pod-aggregator'); ?></span>
                    <br />
                    <a href="<?php echo esc_url(get_edit_post_link($wc_id)); ?>" class="button button-small">
                        <?php esc_html_e('Edit product', 'pod-aggregator'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_permalink($wc_id)); ?>" class="button button-small" target="_blank">
                        <?php esc_html_e('View', 'pod-aggregator'); ?>
                    </a>
                <?php else: ?>
                    <span style="color:#999;"><?php esc_html_e('Not imported', 'pod-aggregator'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$imported): ?>
                    <button
                        type="button"
                        class="button button-primary pod-import-btn"
                        data-product-id="<?php echo esc_attr($pod_id); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                    >
                        <?php esc_html_e('Import to Store', 'pod-aggregator'); ?>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Query pod_product CPT entries with optional filters.
     *
     * @param int    $page     Page number.
     * @param string $provider Provider slug filter.
     * @param string $status   Import status filter (pending|imported).
     * @return array ['products' => [[...]], 'total' => int]
     */
    private function query_pod_products(int $page, string $provider, string $status): array
    {
        global $wpdb;

        $offset = ($page - 1) * self::PER_PAGE;
        $join   = '';
        $where  = ["p.post_type = 'pod_product'", "p.post_status = 'publish'"];

        // Provider filter via post meta.
        if ($provider) {
            $join .= $wpdb->prepare(
                " JOIN {$wpdb->postmeta} pm_prov ON p.ID = pm_prov.post_id AND pm_prov.meta_key = '_pod_provider' AND pm_prov.meta_value = %s",
                $provider
            );
        }

        // Import status filter.
        if ($status === 'imported') {
            $join .= " JOIN {$wpdb->postmeta} pm_imp ON p.ID = pm_imp.post_id AND pm_imp.meta_key = '_pod_imported_to_product'";
        } elseif ($status === 'pending') {
            $join .= " LEFT JOIN {$wpdb->postmeta} pm_imp ON p.ID = pm_imp.post_id AND pm_imp.meta_key = '_pod_imported_to_product'";
            $where[] = 'pm_imp.meta_value IS NULL';
        }

        $where_sql = implode(' AND ', $where);

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p{$join} WHERE {$where_sql}"
        );

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title FROM {$wpdb->posts} p{$join} WHERE {$where_sql} ORDER BY p.post_date DESC LIMIT %d OFFSET %d",
            self::PER_PAGE,
            $offset
        ));

        $products = [];
        foreach ($rows as $row) {
            $provider_meta  = get_post_meta($row->ID, '_pod_provider', true);
            $ppid           = get_post_meta($row->ID, '_pod_provider_product_id', true);
            $thumbnail      = get_post_meta($row->ID, '_pod_thumbnail_url', true);
            $normalized_raw = get_post_meta($row->ID, '_pod_normalized_data', true);
            $imported_to    = get_post_meta($row->ID, '_pod_imported_to_product', true);
            $normalized     = $normalized_raw ? json_decode($normalized_raw, true) : [];
            $variant_count  = isset($normalized['variants']) ? count($normalized['variants']) : 0;

            $products[] = [
                'id'                  => $row->ID,
                'name'                => $row->post_title,
                'provider'            => $provider_meta ?: '',
                'provider_product_id' => $ppid ?: '',
                'thumbnail_url'       => $thumbnail ?: '',
                'variant_count'       => $variant_count,
                'imported_to'         => $imported_to ? (int) $imported_to : 0,
            ];
        }

        return ['products' => $products, 'total' => $total];
    }

    /**
     * AJAX handler — import a single pod_product into WooCommerce.
     *
     * @return void
     */
    public function ajax_import_product(): void
    {
        check_ajax_referer('pod_import_product', 'nonce');

        if (!current_user_can($this->cap())) {
            wp_send_json_error(['message' => __('Permission denied.', 'pod-aggregator')], 403);
            return;
        }

        $pod_product_id = intval($_POST['pod_product_id'] ?? 0);
        if (!$pod_product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'pod-aggregator')]);
            return;
        }

        // Read the markup setting.
        $settings = get_site_option('pod_aggregator_settings', []);
        $markup = max(0, (int) ($settings['printful_default_markup'] ?? 30));

        $importer = new \POD_Aggregator\Product_Importer($markup);
        $result = $importer->import($pod_product_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        $edit_url = get_edit_post_link($result, 'raw');

        wp_send_json_success([
            'message'  => __('Product imported successfully.', 'pod-aggregator'),
            'status'   => __('Imported', 'pod-aggregator'),
            'wc_product_id' => $result,
            'edit_url' => $edit_url ?: admin_url('post.php?post=' . $result . '&action=edit'),
        ]);
    }
}
