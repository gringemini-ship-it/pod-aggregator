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

/**
 * AJAX: Add a saved design (by UUID) to WooCommerce cart.
 * P3-B — bridges the customizer "Save & Add to Cart" flow.
 *
 * @since 1.0.0
 */
function handle_ajax_add_design_to_cart(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'pod_customizer_editor')) {
        wp_send_json_error(['message' => __('Security check failed.', 'pod-aggregator')], 403);
        return;
    }

    if (!function_exists('WC')) {
        wp_send_json_error(['message' => __('WooCommerce is not active.', 'pod-aggregator')], 500);
        return;
    }

    $product_id  = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $design_uuid = isset($_POST['design_uuid']) ? sanitize_key($_POST['design_uuid']) : '';
    $thumb_url   = isset($_POST['thumb_url']) ? esc_url_raw($_POST['thumb_url']) : '';

    if (!$product_id || !$design_uuid) {
        wp_send_json_error(['message' => __('Product and design are required.', 'pod-aggregator')], 400);
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => __('Product not found.', 'pod-aggregator')], 404);
        return;
    }

    // Load design from storage.
    $storage = new \POD_Aggregator\ProductCustomizer\Design_Storage();
    $design  = $storage->get($design_uuid);
    if (!$design) {
        wp_send_json_error(['message' => __('Design not found.', 'pod-aggregator')], 404);
        return;
    }

    $design_json = wp_json_encode($design->jsonSerialize());
    $provider    = $product->get_meta(\POD_Aggregator\WooCommerce\Integration::META_PROVIDER) ?: 'printful';

    $cart_item_data = [
        'pod' => [
            'enabled'        => '1',
            'provider'       => $provider,
            'design_uuid'    => $design_uuid,
            'design_data'    => $design_json,
            'design_thumb'   => $thumb_url,
            'design_name'    => $design->get_name(),
            'print_area'     => $design->get_area(),
        ],
    ];

    $qty = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $qty, 0, $cart_item_data);
    if (!$passed_validation) {
        wp_send_json_error(['message' => __('Could not add customized product to cart.', 'pod-aggregator')], 400);
        return;
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, 0, [], $cart_item_data);
    if (!$cart_item_key) {
        wp_send_json_error(['message' => __('Could not add to cart.', 'pod-aggregator')], 500);
        return;
    }

    WC()->cart->calculate_totals();

    wp_send_json_success([
        'message'       => __('Added to cart!', 'pod-aggregator'),
        'url'           => function_exists('wc_get_cart_url') ? wc_get_cart_url() : WC()->cart->get_cart_url(),
        'cart_item_key' => $cart_item_key,
    ]);
}
add_action('wp_ajax_pod_add_design_to_cart', __NAMESPACE__ . '\\handle_ajax_add_design_to_cart');

/**
 * AJAX: Save a design preset template.
 * P3-A — used by the admin Preset_Templates UI.
 *
 * @since 1.0.0
 */
function handle_ajax_save_preset(): void
{
    check_ajax_referer('pod_preset_templates', 'nonce');

    if (!current_user_can('manage_network')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'pod-aggregator')], 403);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);

    $name     = sanitize_text_field($body['name'] ?? '');
    $category  = sanitize_key($body['category'] ?? '');
    $thumbnail = esc_url_raw($body['thumbnail'] ?? '');
    $design_json = $body['design_json'] ?? '{}';

    if (empty($name)) {
        wp_send_json_error(['message' => __('Template name is required.', 'pod-aggregator')], 400);
        return;
    }

    $design_data = json_decode($design_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($design_data['elements'])) {
        wp_send_json_error(['message' => __('Invalid design JSON.', 'pod-aggregator')], 400);
        return;
    }

    $design  = new \POD_Aggregator\ProductCustomizer\Design($design_data);
    $storage = new \POD_Aggregator\ProductCustomizer\Design_Storage();

    $existing_uuid = sanitize_key($body['uuid'] ?? '');
    if ($existing_uuid) {
        $existing = $storage->get($existing_uuid);
        if (!$existing) {
            wp_send_json_error(['message' => __('Template not found.', 'pod-aggregator')], 404);
            return;
        }
        $merged = $design->jsonSerialize();
        $merged['id']         = $existing_uuid;
        $merged['created_at'] = $existing->get_created_at();
        $merged['updated_at'] = time();
        $design = new \POD_Aggregator\ProductCustomizer\Design($merged);
    } else {
        $design_data['name']    = $name;
        $design_data['provider'] = 'preset';
        $design = new \POD_Aggregator\ProductCustomizer\Design($design_data);
    }

    $post_id = $storage->save($design);
    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => $post_id->get_error_message()], 500);
        return;
    }

    update_post_meta($post_id, \POD_Aggregator\Admin\Preset_Templates::META_IS_PRESET, '1');
    update_post_meta($post_id, \POD_Aggregator\Admin\Preset_Templates::META_CATEGORY, $category);
    update_post_meta($post_id, \POD_Aggregator\Admin\Preset_Templates::META_THUMBNAIL, $thumbnail);

    wp_send_json_success([
        'uuid'    => $design->get_id(),
        'post_id' => $post_id,
    ]);
}
add_action('wp_ajax_pod_save_preset', __NAMESPACE__ . '\\handle_ajax_save_preset');

/**
 * AJAX: Delete a design preset template.
 * P3-A
 *
 * @since 1.0.0
 */
function handle_ajax_delete_preset(): void
{
    check_ajax_referer('pod_preset_templates', 'nonce');

    if (!current_user_can('manage_network')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'pod-aggregator')], 403);
        return;
    }

    $uuid = sanitize_key($_POST['uuid'] ?? '');
    if (empty($uuid)) {
        wp_send_json_error(['message' => __('UUID is required.', 'pod-aggregator')], 400);
        return;
    }

    $storage = new \POD_Aggregator\ProductCustomizer\Design_Storage();
    $deleted = $storage->delete($uuid);
    if (!$deleted) {
        wp_send_json_error(['message' => __('Template not found.', 'pod-aggregator')], 404);
        return;
    }

    wp_send_json_success(['message' => __('Template deleted.', 'pod-aggregator')]);
}
add_action('wp_ajax_pod_delete_preset', __NAMESPACE__ . '\\handle_ajax_delete_preset');

/**
 * AJAX: AI design generation stub.
 * P3-E — placeholder for future AI provider integration.
 *
 * @since 1.0.0
 */
function handle_ajax_ai_generate(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'pod_customizer_editor')) {
        wp_send_json_error(['message' => __('Security check failed.', 'pod-aggregator')], 403);
        return;
    }

    $prompt     = sanitize_text_field($_POST['prompt'] ?? '');
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $print_area = sanitize_key($_POST['print_area'] ?? 'front');

    if (empty($prompt)) {
        wp_send_json_error(['message' => __('Prompt is required.', 'pod-aggregator')], 400);
        return;
    }

    /**
     * Filter: 'pod_aggregator_ai_generate_elements'
     * Hook this to a real AI provider (e.g. DALL-E, Stable Diffusion) to
     * return an array of DesignElement-serializable arrays.
     *
     * @param array  $elements   Empty array — populate with element data
     * @param string $prompt     User's prompt
     * @param int    $product_id WC product ID
     * @param string $print_area Print area key
     * @return array|WP_Error
     */
    $elements = apply_filters('pod_aggregator_ai_generate_elements', [], $prompt, $product_id, $print_area);

    if (is_wp_error($elements)) {
        wp_send_json_error(['message' => $elements->get_error_message()], 500);
        return;
    }

    if (empty($elements)) {
        // No AI provider hooked — return a friendly stub response.
        wp_send_json_error(
            ['message' => __('AI generation is not yet configured. Hook the "pod_aggregator_ai_generate_elements" filter to enable.', 'pod-aggregator')],
            501
        );
        return;
    }

    wp_send_json_success(['elements' => $elements]);
}
add_action('wp_ajax_pod_ai_generate', __NAMESPACE__ . '\\handle_ajax_ai_generate');
