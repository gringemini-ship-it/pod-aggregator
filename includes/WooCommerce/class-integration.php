<?php
/**
 * WooCommerce Integration — bridges POD Aggregator with WooCommerce.
 *
 * @package POD_Aggregator\WooCommerce
 */

namespace POD_Aggregator\WooCommerce;

/**
 * Handles WooCommerce product data, cart item customization,
 * order line item meta, and forwarding orders to POD providers.
 *
 * @since 1.0.0
 */
class Integration
{
    /** Meta key for POD product association. */
    public const META_POD_ENABLED      = '_pod_enabled';
    public const META_PROVIDER          = '_pod_provider';
    public const META_VARIANT_ID        = '_pod_variant_id';
    public const META_DESIGN_DATA       = '_pod_design_data';
    public const META_DESIGN_UUID       = '_pod_design_uuid';
    public const META_DESIGN_THUMB      = '_pod_design_thumb';
    public const META_DESIGN_NAME       = '_pod_design_name';
    public const META_PRINT_FILE_URL    = '_pod_print_file_url';
    public const META_PRINT_AREA        = '_pod_print_area';
    // Per-product print dimension overrides (P3-D).
    public const META_PRINT_WIDTH_MM    = '_pod_print_width_mm';
    public const META_PRINT_HEIGHT_MM   = '_pod_print_height_mm';
    public const META_PRINT_DPI         = '_pod_print_dpi';

    /**
     * Add a "POD Product" tab to the WooCommerce product data box.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public function product_data_tab(array $tabs): array
    {
        $tabs['pod_product'] = [
            'label'    => __('POD Product', 'pod-aggregator'),
            'target'   => 'pod_product_data',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 70,
        ];
        return $tabs;
    }

    /**
     * Render the POD product data panel.
     *
     * @return void
     */
    public function product_data_panel()
    {
        global $post;

        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        $is_pod      = (bool) $product->get_meta(self::META_POD_ENABLED);
        $provider    = $product->get_meta(self::META_PROVIDER) ?: 'printful';
        $variant_id  = $product->get_meta(self::META_VARIANT_ID);
        $design_data = $product->get_meta(self::META_DESIGN_DATA);
        ?>
        <div id="pod_product_data" class="panel woocommerce_options_panel" style="display:none">

            <div class="options_group">
                <p class="form-field">
                    <label for="_pod_enabled">
                        <?php esc_html_e('Enable POD Product', 'pod-aggregator'); ?>
                    </label>
                    <input
                        type="checkbox"
                        id="_pod_enabled"
                        name="_pod_enabled"
                        value="1"
                        <?php checked($is_pod, true); ?>
                    />
                    <span class="description">
                        <?php esc_html_e('Mark this product as a Print-on-Demand item linked to a POD provider.', 'pod-aggregator'); ?>
                    </span>
                </p>
            </div>

            <div class="options_group" id="pod_provider_fields" style="<?php echo $is_pod ? '' : 'display:none'; ?>">
                <p class="form-field">
                    <label for="_pod_provider"><?php esc_html_e('Provider', 'pod-aggregator'); ?></label>
                    <select id="_pod_provider" name="_pod_provider">
                        <option value="printful" <?php selected($provider, 'printful'); ?>>Printful</option>
                        <option value="printify" <?php selected($provider, 'printify'); ?>>Printify</option>
                        <option value="gelato" <?php selected($provider, 'gelato'); ?>>Gelato</option>
                    </select>
                </p>

                <p class="form-field">
                    <label for="_pod_variant_id"><?php esc_html_e('Provider Variant ID', 'pod-aggregator'); ?></label>
                    <input
                        type="text"
                        id="_pod_variant_id"
                        name="_pod_variant_id"
                        class="short"
                        value="<?php echo esc_attr($variant_id); ?>"
                        placeholder="e.g. 12829"
                    />
                </p>

                <p class="form-field">
                    <label for="_pod_design_data"><?php esc_html_e('Default Design Data (JSON)', 'pod-aggregator'); ?></label>
                    <textarea
                        id="_pod_design_data"
                        name="_pod_design_data"
                        rows="4"
                        class="short"
                        placeholder='{"url":"https://...","type":"front","position":"front"}'
                    ><?php echo esc_textarea(is_string($design_data) ? $design_data : wp_json_encode($design_data)); ?></textarea>
                    <span class="description">
                        <?php esc_html_e('JSON describing print file, position, and options.', 'pod-aggregator'); ?>
                    </span>
                </p>

                <p class="form-field">
                    <button type="button" class="button" id="pod_fetch_variants">
                        <?php esc_html_e('Fetch Variants from Provider', 'pod-aggregator'); ?>
                    </button>
                    <span id="pod_variant_status"></span>
                </p>
            </div>

            <?php // Print dimension overrides (P3-D) — shown only for POD-enabled products ?>
            <div class="options_group" id="pod_print_dims" style="<?php echo $is_pod ? '' : 'display:none'; ?>">
                <p class="form-field">
                    <label for="_pod_print_width_mm"><?php esc_html_e('Print Width (mm)', 'pod-aggregator'); ?></label>
                    <input
                        type="number"
                        id="_pod_print_width_mm"
                        name="_pod_print_width_mm"
                        class="short"
                        value="<?php echo esc_attr($product->get_meta(self::META_PRINT_WIDTH_MM) ?: ''); ?>"
                        placeholder="e.g. 300"
                        min="1"
                        step="0.1"
                    />
                    <span class="description"><?php esc_html_e('Override the default print width in millimetres. Leave blank to use provider default.', 'pod-aggregator'); ?></span>
                </p>

                <p class="form-field">
                    <label for="_pod_print_height_mm"><?php esc_html_e('Print Height (mm)', 'pod-aggregator'); ?></label>
                    <input
                        type="number"
                        id="_pod_print_height_mm"
                        name="_pod_print_height_mm"
                        class="short"
                        value="<?php echo esc_attr($product->get_meta(self::META_PRINT_HEIGHT_MM) ?: ''); ?>"
                        placeholder="e.g. 400"
                        min="1"
                        step="0.1"
                    />
                    <span class="description"><?php esc_html_e('Override the default print height in millimetres. Leave blank to use provider default.', 'pod-aggregator'); ?></span>
                </p>

                <p class="form-field">
                    <label for="_pod_print_dpi"><?php esc_html_e('Print DPI', 'pod-aggregator'); ?></label>
                    <input
                        type="number"
                        id="_pod_print_dpi"
                        name="_pod_print_dpi"
                        class="short"
                        value="<?php echo esc_attr($product->get_meta(self::META_PRINT_DPI) ?: '300'); ?>"
                        placeholder="300"
                        min="72"
                        max="600"
                    />
                    <span class="description"><?php esc_html_e('DPI for print file generation. Default: 300.', 'pod-aggregator'); ?></span>
                </p>
            </div>

        </div>

        <script>
        (function () {
            var checkbox   = document.getElementById('_pod_enabled');
            var providerFields = document.getElementById('pod_provider_fields');
            var printDims  = document.getElementById('pod_print_dims');
            if (checkbox) {
                checkbox.addEventListener('change', function () {
                    var show = this.checked;
                    if (providerFields) providerFields.style.display = show ? '' : 'none';
                    if (printDims)     printDims.style.display     = show ? '' : 'none';
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Save POD product meta when a product is saved.
     *
     * @param int $post_id Product post ID.
     * @return void
     */
    public function save_product_meta(int $post_id)
    {
        // Security: verify nonce and user capability.
        if (!isset($_POST['woocommerce_meta_nonce'])
            || !wp_verify_nonce(sanitize_key($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data')
        ) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $is_enabled = isset($_POST['_pod_enabled']) ? '1' : '0';
        $product->update_meta_data(self::META_POD_ENABLED, $is_enabled);
        $product->update_meta_data(
            self::META_PROVIDER,
            sanitize_text_field(wp_unslash($_POST['_pod_provider'] ?? 'printful'))
        );
        $product->update_meta_data(
            self::META_VARIANT_ID,
            sanitize_text_field(wp_unslash($_POST['_pod_variant_id'] ?? ''))
        );
        $product->update_meta_data(
            self::META_DESIGN_DATA,
            sanitize_text_field(wp_unslash($_POST['_pod_design_data'] ?? ''))
        );

        // Print dimension overrides (P3-D).
        if (isset($_POST['_pod_print_width_mm'])) {
            $product->update_meta_data(self::META_PRINT_WIDTH_MM, (float) $_POST['_pod_print_width_mm']);
        }
        if (isset($_POST['_pod_print_height_mm'])) {
            $product->update_meta_data(self::META_PRINT_HEIGHT_MM, (float) $_POST['_pod_print_height_mm']);
        }
        if (isset($_POST['_pod_print_dpi'])) {
            $product->update_meta_data(self::META_PRINT_DPI, absint($_POST['_pod_print_dpi']));
        }

        $product->save();
    }

    /**
     * Add cart item data when a POD product is added to the cart.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id    Variation ID (0 if simple).
     * @return array
     */
    public function add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_meta(self::META_POD_ENABLED) !== '1') {
            return $cart_item_data;
        }

        $cart_item_data['pod'] = [
            'enabled'     => '1',
            'provider'    => $product->get_meta(self::META_PROVIDER),
            'variant_id'  => $product->get_meta(self::META_VARIANT_ID),
            'design_data' => sanitize_text_field(wp_unslash($_POST['pod_design_data'] ?? '{}')),
        ];

        return $cart_item_data;
    }

    /**
     * Display POD customization summary in cart.
     *
     * @param array $data Row data.
     * @param array $item Cart item.
     * @return array
     */
    public function display_cart_item_data(array $data, array $item): array
    {
        if (empty($item['pod'])) {
            return $data;
        }

        $pod = $item['pod'];

        // Show design name if available.
        if (!empty($pod['design_name'])) {
            $data[] = [
                'key'   => __('Customization', 'pod-aggregator'),
                'value' => esc_html($pod['design_name']),
            ];
        } elseif (!empty($pod['design_data'])) {
            $design = json_decode($pod['design_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data[] = [
                    'key'   => __('Customization', 'pod-aggregator'),
                    'value' => esc_html($design['description'] ?? __('Custom design applied', 'pod-aggregator')),
                ];
            }
        }

        // Show thumbnail preview if available (P3-B).
        if (!empty($pod['design_thumb'])) {
            $data[] = [
                'key'   => __('Design Preview', 'pod-aggregator'),
                'value' => '<img src="' . esc_url($pod['design_thumb']) . '" alt="Design preview" style="max-width:80px;max-height:80px;">',
                'display' => 'virtual', // Tells WC not to escape HTML.
            ];
        }

        return $data;
    }

    /**
     * Add POD design data as order line item meta and generate the
     * 300 DPI print file for the production POD provider (P3-C).
     *
     * @param \WC_Order_Item_Product $item         Order line item product.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @return void
     */
    public function create_order_line_item(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values
    ) {
        if (empty($values['pod'])) {
            return;
        }

        $pod = $values['pod'];

        $item->add_meta_data('_pod_provider', $pod['provider'] ?? '', true);
        $item->add_meta_data('_pod_variant_id', $pod['variant_id'] ?? '', true);
        $item->add_meta_data('_pod_design_data', $pod['design_data'] ?? '', true);
        $item->add_meta_data('_pod_design_uuid', $pod['design_uuid'] ?? '', true);
        $item->add_meta_data('_pod_design_thumb', $pod['design_thumb'] ?? '', true);
        $item->add_meta_data('_pod_design_name', $pod['design_name'] ?? '', true);
        $item->add_meta_data('_pod_print_area', $pod['print_area'] ?? '', true);

        // Generate and store the 300 DPI print file (P3-C).
        $print_file_url = $this->generate_print_file_for_order_item($pod);
        $item->add_meta_data('_pod_print_file_url', $print_file_url, true);
    }

    /**
     * Generate a 300 DPI print file from a saved design and return the URL.
     * Hooked into order item creation so the file is ready when the order
     * is forwarded to the POD provider.
     *
     * P3-C: bridges Design_Storage → Print_Generator → WC order meta.
     *
     * @param array $pod Cart item POD data.
     * @return string URL of the generated print file (empty on failure).
     */
    private function generate_print_file_for_order_item(array $pod): string
    {
        $design_uuid = $pod['design_uuid'] ?? '';
        if (empty($design_uuid)) {
            // Fall back to inline design_data if no UUID.
            $design_data = json_decode($pod['design_data'] ?? '{}', true);
            if (empty($design_data)) {
                return '';
            }
            try {
                $design = new \POD_Aggregator\ProductCustomizer\Design($design_data);
            } catch (\Throwable $e) {
                return '';
            }
        } else {
            $storage = new \POD_Aggregator\ProductCustomizer\Design_Storage();
            $design  = $storage->get($design_uuid);
            if (!$design) {
                return '';
            }
        }

        $print_gen = new \POD_Aggregator\ProductCustomizer\Print_Generator();
        $result    = $print_gen->generate($design);

        if (is_wp_error($result)) {
            // Log but don't fail the order — print file can be regenerated manually.
            error_log('[POD Aggregator] Print file generation failed: ' . $result->get_error_message());
            return '';
        }

        return $result['file_url'] ?? '';
    }

    /**
     * Forward a WooCommerce order to the POD provider.
     *
     * Fired on 'woocommerce_checkout_order_processed'.
     *
     * Supports multi-provider orders: items are grouped by provider and
     * submitted as separate orders. Each provider's result is logged to
     * the sync log table.
     *
     * @param int      $order_id        WooCommerce order ID.
     * @param mixed    $posted_data     Posted checkout data.
     * @param \WC_Order $order          Order object.
     * @return void
     */
    public function forward_order_to_provider(int $order_id, $posted_data, \WC_Order $order)
    {
        // Collect POD items grouped by provider.
        $items_by_provider = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $meta_provider = $item->get_meta('_pod_provider', true);
            if (empty($meta_provider)) {
                continue;
            }

            // Group item under its provider.
            if (!isset($items_by_provider[$meta_provider])) {
                $items_by_provider[$meta_provider] = [];
            }

            $items_by_provider[$meta_provider][] = [
                'provider_product_id' => $item->get_meta('_pod_variant_id', true),
                'variant_id'          => $item->get_meta('_pod_variant_id', true),
                'provider'            => $meta_provider,
                'qty'                 => $item->get_quantity(),
                'design_data'         => json_decode($item->get_meta('_pod_design_data', true), true),
                'design_uuid'         => $item->get_meta('_pod_design_uuid', true),
                'print_file_url'      => $item->get_meta('_pod_print_file_url', true),
                'print_area'          => $item->get_meta('_pod_print_area', true),
                'item_id'             => $item_id,
            ];
        }

        if (empty($items_by_provider)) {
            return;
        }

        // Build shipping address once (shared across all provider orders).
        $address = $order->get_address('shipping');
        $base_address = [
            'name'     => $address['first_name'] . ' ' . $address['last_name'],
            'address1' => $address['address_1'],
            'address2' => $address['address_2'],
            'city'    => $address['city'],
            'state'   => $address['state'],
            'country' => $address['country'],
            'zip'     => $address['postcode'],
            'phone'   => $order->get_billing_phone(),
            'email'   => $order->get_billing_email(),
        ];

        // Submit a separate order to each provider.
        $first_external_id = null;
        foreach ($items_by_provider as $provider_slug => $pod_items) {
            $provider = pod_aggregator_get_provider($provider_slug);

            if (!$provider || !$provider->is_configured()) {
                $order->add_order_note(sprintf(
                    __('POD Aggregator: Provider "%s" is not configured. Items not submitted.', 'pod-aggregator'),
                    $provider_slug
                ));
                continue;
            }

            $order_data = [
                'woo_order_id'     => $order_id,
                'items'            => $pod_items,
                'shipping_address' => $base_address,
            ];

            $result = $provider->submit_order($order_data);

            if (is_wp_error($result)) {
                $order->add_order_note(sprintf(
                    __('POD Aggregator: %s submission failed — %s', 'pod-aggregator'),
                    $provider->get_name(),
                    $result->get_error_message()
                ));
                continue;
            }

            $external_id = $result['id'] ?? 'unknown';

            // Store the first external ID in the WC order meta for reference.
            if (null === $first_external_id) {
                $first_external_id = $external_id;
                $order->update_meta_data('_pod_external_order_id', $external_id);
            }

            // Track each provider's external ID separately.
            $order->update_meta_data("_pod_external_order_id_{$provider_slug}", $external_id);
            $order->add_order_note(sprintf(
                __('POD Aggregator: Order forwarded to %s (External ID: %s)', 'pod-aggregator'),
                $provider->get_name(),
                $external_id
            ));
        }

        // Store primary provider in meta.
        $primary_provider = array_key_first($items_by_provider);
        $order->update_meta_data('_pod_provider', $primary_provider);
        $order->save();
    }

    /**
     * Add a "Resend to POD" action on the WooCommerce order page.
     *
     * @param array $actions Existing order actions.
     * @return array
     */
    public function add_order_actions(array $actions): array
    {
        $actions['pod_resend'] = __('Resend to POD Provider', 'pod-aggregator');
        return $actions;
    }

    /**
     * Handle the "Resend to POD" order action.
     *
     * @param \WC_Order $order WooCommerce order.
     * @return void
     */
    public function resend_order_to_provider(\WC_Order $order)
    {
        $provider_slug = $order->get_meta('_pod_provider', true) ?: 'printful';
        $provider     = pod_aggregator_get_provider($provider_slug);

        if (!$provider || !$provider->is_configured()) {
            $order->add_order_note(
                __('POD Aggregator: Provider not configured.', 'pod-aggregator')
            );
            return;
        }

        $pod_items = [];
        foreach ($order->get_items() as $item_id => $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            if ($item->get_meta('_pod_provider', true)) {
                $pod_items[] = [
                    'provider_product_id' => $item->get_meta('_pod_variant_id', true),
                    'variant_id'           => $item->get_meta('_pod_variant_id', true),
                    'provider'             => $item->get_meta('_pod_provider', true),
                    'qty'                  => $item->get_quantity(),
                    'design_data'          => json_decode($item->get_meta('_pod_design_data', true), true),
                ];
            }
        }

        $address = $order->get_address('shipping');

        $result = $provider->submit_order([
            'woo_order_id'     => $order->get_id(),
            'items'            => $pod_items,
            'shipping_address' => [
                'name'     => $address['first_name'] . ' ' . $address['last_name'],
                'address1' => $address['address_1'],
                'address2' => $address['address_2'],
                'city'    => $address['city'],
                'state'   => $address['state'],
                'country' => $address['country'],
                'zip'     => $address['postcode'],
                'phone'   => $order->get_billing_phone(),
                'email'   => $order->get_billing_email(),
            ],
        ]);

        if (is_wp_error($result)) {
            $order->add_order_note(
                sprintf(
                    __('POD Aggregator: Resend failed — %s', 'pod-aggregator'),
                    $result->get_error_message()
                )
            );
            return;
        }

        $order->add_order_note(
            sprintf(
                __('POD Aggregator: Order resent to %s (External ID: %s)', 'pod-aggregator'),
                $provider->get_name(),
                $result['id'] ?? 'unknown'
            )
        );
    }
}
