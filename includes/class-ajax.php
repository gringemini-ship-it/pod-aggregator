<?php
/**
 * POD Aggregator — AJAX Handlers.
 *
 * Handles AJAX endpoints used by the frontend JavaScript:
 *   - pod_add_to_cart
 *   - pod_load_customizer
 *
 * @package POD_Aggregator
 */

namespace POD_Aggregator;

/**
 * AJAX handler for adding a POD product to the WooCommerce cart.
 *
 * @since 1.0.0
 */
function handle_ajax_add_to_cart(): void
{
    // Verify nonce.
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'pod_add_to_cart')) {
        wp_send_json_error(['message' => __('Security check failed.', 'pod-aggregator')], 403);
        return;
    }

    // Validate required inputs.
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $variant_id = isset($_POST['variant_id']) ? sanitize_text_field(wp_unslash($_POST['variant_id'])) : '';
    $design_data = isset($_POST['design_data']) ? wp_unslash($_POST['design_data']) : '{}';

    if (!$product_id) {
        wp_send_json_error(['message' => __('Invalid product.', 'pod-aggregator')], 400);
        return;
    }

    // Ensure WooCommerce is active and the product exists.
    if (!function_exists('WC')) {
        wp_send_json_error(['message' => __('WooCommerce is not active.', 'pod-aggregator')], 500);
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => __('Product not found.', 'pod-aggregator')], 404);
        return;
    }

    // Retrieve the POD-enabled flag from the product.
    $is_pod = $product->get_meta(\POD_Aggregator\WooCommerce\Integration::META_POD_ENABLED);
    if ($is_pod !== '1') {
        // Fall back to normal WooCommerce add-to-cart if not a POD product.
        $qty = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $qty);
        if (!$passed_validation) {
            wp_send_json_error(['message' => __('Could not add to cart.', 'pod-aggregator')], 400);
            return;
        }
        $cart_item_key = WC()->cart->add_to_cart($product_id, $qty);
    } else {
        // POD product: pass design data as cart item meta.
        $design_data_arr = json_decode($design_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $design_data_arr = [];
        }

        $cart_item_data = [
            'pod' => [
                'enabled'     => '1',
                'provider'    => $product->get_meta(\POD_Aggregator\WooCommerce\Integration::META_PROVIDER) ?: 'printful',
                'variant_id'  => $variant_id,
                'design_data' => $design_data,
            ],
        ];

        $qty = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $qty, 0, $cart_item_data);
        if (!$passed_validation) {
            wp_send_json_error(['message' => __('Could not add customized product to cart.', 'pod-aggregator')], 400);
            return;
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, 0, [], $cart_item_data);
    }

    if (!$cart_item_key) {
        wp_send_json_error(['message' => __('Could not add to cart.', 'pod-aggregator')], 500);
        return;
    }

    WC()->cart->calculate_totals();

    $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : WC()->cart->get_cart_url();

    wp_send_json_success([
        'message'     => __('Added to cart!', 'pod-aggregator'),
        'cart_url'    => $cart_url,
        'cart_item_key' => $cart_item_key,
    ]);
}
add_action('wp_ajax_pod_add_to_cart', __NAMESPACE__ . '\\handle_ajax_add_to_cart');
add_action('wp_ajax_nopriv_pod_add_to_cart', __NAMESPACE__ . '\\handle_ajax_add_to_cart');

/**
 * AJAX handler for loading the POD customizer inline (used by the catalog modal).
 *
 * @since 1.0.0
 */
function handle_ajax_load_customizer(): void
{
    // Verify nonce.
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'pod_aggregator_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'pod-aggregator')], 403);
        return;
    }

    $product_id = isset($_POST['product_id']) ? sanitize_text_field(wp_unslash($_POST['product_id'])) : '';
    $provider   = isset($_POST['provider']) ? sanitize_key($_POST['provider']) : 'printful';

    if (empty($product_id)) {
        wp_send_json_error(['message' => __('product_id is required.', 'pod-aggregator')], 400);
        return;
    }

    $provider_obj = pod_aggregator_get_provider($provider);
    if (!$provider_obj || !$provider_obj->is_configured()) {
        wp_send_json_error(['message' => __('POD provider not configured.', 'pod-aggregator')], 400);
        return;
    }

    $product = $provider_obj->get_product($product_id);
    if (is_wp_error($product)) {
        wp_send_json_error(['message' => $product->get_error_message()], 500);
        return;
    }

    $variants    = $product['variants'] ?? [];
    $mockups     = $provider_obj->get_mockups($product_id);
    $design_data = $product['files'] ?? [];

    // Render the customizer HTML via the Shortcodes class (reuses its renderer).
    $shortcodes = new \POD_Aggregator\Public\Shortcodes();
    ob_start();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered HTML is intentionally unescaped here as it uses WC functions.
    echo $shortcodes->render_customizer([
        'product_id' => $product_id,
        'provider'   => $provider,
        'variant_id' => $variants[0]['variant_id'] ?? '',
    ]);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_pod_load_customizer', __NAMESPACE__ . '\\handle_ajax_load_customizer');
add_action('wp_ajax_nopriv_pod_load_customizer', __NAMESPACE__ . '\\handle_ajax_load_customizer');
