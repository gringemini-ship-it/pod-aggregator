<?php
/**
 * POD Aggregator — Frontend Shortcodes.
 *
 * @package POD_Aggregator\Public
 */

namespace POD_Aggregator\Public;

/**
 * Registers and renders frontend shortcodes.
 *
 * @since 1.0.0
 */
class Shortcodes
{
    /**
     * Render the POD product customizer using the Fabric.js canvas editor.
     *
     * Shortcode: [pod_customizer product_id="123" area="front" design_uuid=""]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_customizer(array $atts): string
    {
        $atts = shortcode_atts([
            'product_id'  => '',
            'area'        => 'front',
            'design_uuid' => '',
        ], $atts, 'pod_customizer');

        if (empty($atts['product_id'])) {
            return '<p class="pod-error">' . esc_html__('POD customizer: product_id is required.', 'pod-aggregator') . '</p>';
        }

        $product_id = absint($atts['product_id']);
        $area       = sanitize_key($atts['area']);
        $design_uuid = sanitize_key($atts['design_uuid']);

        // Enqueue the editor assets.
        $editor = new \POD_Aggregator\Public\POD_Customizer_Editor();
        $editor->enqueue_assets();

        // Render the editor UI.
        return $editor->render_editor($product_id, $area, $design_uuid);
    }

    /**
     * Render a browsable POD product catalog.
     *
     * Shortcode: [pod_catalog provider="printful" category="all" per_page="12"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_catalog(array $atts): string
    {
        $atts = shortcode_atts([
            'provider'  => 'printful',
            'category' => 'all',
            'per_page' => '12',
        ], $atts, 'pod_catalog');

        $provider = \POD_Aggregator\pod_aggregator_get_provider($atts['provider']);
        if (!$provider || !$provider->is_configured()) {
            return '<p class="pod-error">' . esc_html__('POD catalog: provider not configured.', 'pod-aggregator') . '</p>';
        }

        $products = $provider->get_products();
        if (is_wp_error($products) || empty($products)) {
            return '<p class="pod-info">' . esc_html__('No products found.', 'pod-aggregator') . '</p>';
        }

        $per_page  = max(1, (int) $atts['per_page']);
        $total     = count($products);
        $page      = max(1, intval($_GET['pod_catalog_page'] ?? 1));
        $offset    = ($page - 1) * $per_page;
        $displayed = array_slice($products, $offset, $per_page);

        ob_start();
        ?>
        <div class="pod-catalog" id="pod-catalog-<?php echo esc_attr($atts['provider']); ?>">
            <div class="pod-catalog-grid">
                <?php foreach ($displayed as $product): ?>
                    <div class="pod-catalog-item">
                        <?php if (!empty($product['thumbnail_url'])): ?>
                            <img
                                src="<?php echo esc_url($product['thumbnail_url']); ?>"
                                alt="<?php echo esc_attr($product['name'] ?? ''); ?>"
                                class="pod-catalog-thumb"
                                loading="lazy"
                            />
                        <?php else: ?>
                            <div class="pod-catalog-placeholder">
                                <?php esc_html_e('No image', 'pod-aggregator'); ?>
                            </div>
                        <?php endif; ?>

                        <h3 class="pod-catalog-title"><?php echo esc_html($product['name'] ?? ''); ?></h3>

                        <?php if (!empty($product['variants'])): ?>
                            <p class="pod-catalog-price">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s = starting price */
                                        __('From %s', 'pod-aggregator'),
                                        wc_price($product['variants'][0]['price'] ?? 0)
                                    )
                                );
                                ?>
                            </p>
                        <?php endif; ?>

                        <button
                            type="button"
                            class="button"
                            onclick="podOpenCustomizer('<?php echo esc_attr($product['provider_product_id']); ?>', '<?php echo esc_attr($atts['provider']); ?>')"
                        >
                            <?php esc_html_e('Customize', 'pod-aggregator'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total > $per_page): ?>
                <div class="pod-catalog-pagination">
                    <?php if ($page > 1): ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg('pod_catalog_page', $page - 1)); ?>">
                            &larr; <?php esc_html_e('Previous', 'pod-aggregator'); ?>
                        </a>
                    <?php endif; ?>

                    <span>
                        <?php
                        printf(
                            /* translators: %1$d = current page, %2$d = total pages */
                            esc_html__('Page %1$d of %2$d', 'pod-aggregator'),
                            $page,
                            ceil($total / $per_page)
                        );
                        ?>
                    </span>

                    <?php if (($offset + $per_page) < $total): ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg('pod_catalog_page', $page + 1)); ?>">
                            <?php esc_html_e('Next', 'pod-aggregator'); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Enqueue frontend CSS and JS assets.
     *
     * @return void
     */
    private function enqueue_assets()
    {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }

        wp_enqueue_style(
            'pod-aggregator-frontend',
            POD_AGGREGATOR_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            POD_AGGREGATOR_VERSION
        );

        wp_enqueue_script(
            'pod-aggregator-frontend',
            POD_AGGREGATOR_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            POD_AGGREGATOR_VERSION,
            true
        );

        wp_localize_script('pod-aggregator-frontend', 'PODConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pod_aggregator_nonce'),
            'i18n'    => [
                'addToCart'   => __('Add to Cart', 'pod-aggregator'),
                'processing'  => __('Processing...', 'pod-aggregator'),
                'success'     => __('Added to cart!', 'pod-aggregator'),
                'error'       => __('Error. Please try again.', 'pod-aggregator'),
            ],
        ]);

        $enqueued = true;
    }

    /**
     * Output per-product inline initialization script.
     *
     * @param string $product_id Product ID for scoping.
     * @return void
     */
    private function inline_product_script(string $product_id)
    {
        ?>
        <script>
        (function () {
            var btn = document.getElementById('pod-add-to-cart-<?php echo esc_attr($product_id); ?>');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var designDataInput = document.getElementById('pod-design-data-<?php echo esc_attr($product_id); ?>');
                var variantSelect   = document.getElementById('pod-variant-<?php echo esc_attr($product_id); ?>');
                var variantId       = variantSelect ? variantSelect.value : btn.dataset.variantId;
                var productId      = btn.dataset.productId;
                var provider       = btn.dataset.provider;

                var designData = {
                    product_id: productId,
                    provider:   provider,
                    variant_id: variantId,
                    text_front: '',
                    text_back:  '',
                    image_url:  '',
                    position:   'front',
                };

                // Collect text inputs.
                var textInputs = document.querySelectorAll('#pod-customizer-<?php echo esc_attr($product_id); ?> .pod-text-input');
                textInputs.forEach(function (input) {
                    if (input.dataset.position === 'front') {
                        designData.text_front = input.value;
                    } else {
                        designData.text_back = input.value;
                    }
                });

                if (designDataInput) {
                    designDataInput.value = JSON.stringify(designData);
                }

                // Add to cart via AJAX.
                var formData = new FormData();
                formData.append('action', 'pod_add_to_cart');
                formData.append('nonce', '<?php echo esc_attr(wp_create_nonce('pod_add_to_cart')); ?>');
                formData.append('product_id', productId);
                formData.append('variant_id', variantId);
                formData.append('design_data', JSON.stringify(designData));

                btn.disabled = true;
                btn.textContent = '<?php esc_attr_e('Processing...', 'pod-aggregator'); ?>';

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        btn.textContent = '<?php esc_attr_e('Added to cart!', 'pod-aggregator'); ?>';
                        // Redirect to cart or update cart count.
                        if (r.data && r.data.cart_url) {
                            window.location.href = r.data.cart_url;
                        } else {
                            setTimeout(function () {
                                btn.disabled = false;
                                btn.textContent = '<?php esc_attr_e('Add to Cart', 'pod-aggregator'); ?>';
                            }, 2000);
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = '<?php esc_attr_e('Error. Try again.', 'pod-aggregator'); ?>';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = '<?php esc_attr_e('Error. Try again.', 'pod-aggregator'); ?>';
                });
            });
        })();
        </script>
        <?php
    }
}
