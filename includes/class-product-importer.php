<?php
/**
 * POD Aggregator — Product Importer.
 *
 * Converts synced pod_product CPT entries into WooCommerce variable products.
 *
 * @package POD_Aggregator
 */

namespace POD_Aggregator;

/**
 * Handles importing POD product CPTs into actual WooCommerce products.
 *
 * Reads normalized product data from post meta, creates WC variable products
 * with variations (size/color), downloads thumbnails, and tracks the
 * relationship so duplicates are prevented.
 *
 * @since 1.0.8
 */
class Product_Importer
{
    /** Meta key on pod_product marking the resulting WC product ID. */
    public const META_IMPORTED_TO = '_pod_imported_to_product';

    /** Meta key on WC product marking the source pod_product ID. */
    public const META_SOURCE_POD_ID = '_pod_source_product_id';

    /** Meta key on WC product marking the provider slug. */
    public const META_PROVIDER = '_pod_provider';

    /** Meta key on WC product marking the provider product ID. */
    public const META_PROVIDER_PRODUCT_ID = '_pod_provider_product_id';

    /** Default markup percentage applied to variant cost. */
    private int $markup_percent;

    /**
     * Constructor.
     *
     * @param int $markup_percent Default markup on provider cost (0–500).
     */
    public function __construct(int $markup_percent = 30)
    {
        $this->markup_percent = max(0, min(500, $markup_percent));
    }

    /**
     * Import a pod_product CPT entry as a WooCommerce variable product.
     *
     * @param int $pod_product_id The pod_product post ID.
     * @return int|\WP_Error WC product ID on success, WP_Error on failure.
     */
    public function import(int $pod_product_id)
    {
        $pod_post = get_post($pod_product_id);
        if (!$pod_post || $pod_post->post_type !== 'pod_product') {
            return new \WP_Error('invalid_post', __('Not a valid POD product.', 'pod-aggregator'));
        }

        // Check if already imported.
        $existing_wc_id = (int) get_post_meta($pod_product_id, self::META_IMPORTED_TO, true);
        if ($existing_wc_id && get_post($existing_wc_id)) {
            return new \WP_Error(
                'already_imported',
                sprintf(
                    /* translators: %d = WooCommerce product ID */
                    __('Already imported as product #%d.', 'pod-aggregator'),
                    $existing_wc_id
                )
            );
        }

        $normalized = $this->get_normalized_data($pod_product_id);
        if (empty($normalized['name'])) {
            return new \WP_Error('invalid_data', __('Product data is missing a name.', 'pod-aggregator'));
        }

        // Products synced from the catalog list don't include variants.
        // Fetch the full product detail (with variants) from the provider.
        if (empty($normalized['variants']) && !empty($normalized['provider_product_id'])) {
            $provider = \POD_Aggregator\pod_aggregator_get_provider($normalized['provider'] ?? 'printful');
            if ($provider) {
                $detail = $provider->get_product($normalized['provider_product_id']);
                if (!is_wp_error($detail)) {
                    $normalized = $detail;
                    // Update the CPT with the full data so we don't re-fetch.
                    update_post_meta($pod_product_id, '_pod_normalized_data', wp_json_encode($normalized));
                }
            }
        }

        if (empty($normalized['variants'])) {
            return new \WP_Error('invalid_data', __('Product has no variants to import.', 'pod-aggregator'));
        }

        $variants = $normalized['variants'];

        // Create the parent variable product.
        $wc_product_id = $this->create_variable_product(
            $normalized['name'],
            $normalized['description'] ?? '',
            $normalized['provider'] ?? '',
            $normalized['provider_product_id'] ?? '',
            $normalized['model'] ?? '',
            $normalized['brand'] ?? '',
            $normalized['category'] ?? ''
        );

        if (is_wp_error($wc_product_id)) {
            return $wc_product_id;
        }

        // Download and attach thumbnail.
        if (!empty($normalized['thumbnail_url'])) {
            $thumb_id = $this->sideload_image($normalized['thumbnail_url'], $wc_product_id);
            if ($thumb_id && !is_wp_error($thumb_id)) {
                set_post_thumbnail($wc_product_id, $thumb_id);
            }
        }

        // Collect attributes from variants.
        $attributes = $this->build_attributes($variants);
        $this->set_product_attributes($wc_product_id, $attributes);

        // Create variation posts.
        foreach ($variants as $variant) {
            $this->create_variation($wc_product_id, $variant, $attributes, $normalized['thumbnail_url'] ?? '');
        }

        // Link back to the source.
        update_post_meta($pod_product_id, self::META_IMPORTED_TO, $wc_product_id);
        update_post_meta($wc_product_id, self::META_SOURCE_POD_ID, $pod_product_id);
        update_post_meta($wc_product_id, self::META_PROVIDER, $normalized['provider'] ?? '');
        update_post_meta($wc_product_id, self::META_PROVIDER_PRODUCT_ID, $normalized['provider_product_id'] ?? '');
        update_post_meta($wc_product_id, '_pod_enabled', 'yes');

        // Set the catalog visibility.
        $product = wc_get_product($wc_product_id);
        if ($product) {
            $product->set_catalog_visibility('visible');
            $product->save();
        }

        return $wc_product_id;
    }

    /**
     * Read normalized product data from a pod_product post.
     *
     * @param int $pod_product_id
     * @return array
     */
    private function get_normalized_data(int $pod_product_id): array
    {
        $raw = get_post_meta($pod_product_id, '_pod_normalized_data', true);
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Create the parent WooCommerce variable product post.
     *
     * @param string $name
     * @param string $description
     * @param string $provider
     * @param string $provider_product_id
     * @param string $model
     * @param string $brand
     * @param string $category
     * @return int|\WP_Error
     */
    private function create_variable_product(
        string $name,
        string $description,
        string $provider,
        string $provider_product_id,
        string $model,
        string $brand,
        string $category
    ) {
        $post_id = wp_insert_post([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'post_title'     => sanitize_text_field($name),
            'post_content'   => wp_kses_post($description),
            'post_excerpt'   => wp_kses_post(
                $brand
                    ? sprintf(
                        /* translators: %1$s = brand, %2$s = provider name */
                        __('Brand: %1$s | Provider: %2$s', 'pod-aggregator'),
                        $brand,
                        ucfirst($provider)
                    )
                    : sprintf(
                        /* translators: %s = provider name */
                        __('Provider: %s', 'pod-aggregator'),
                        ucfirst($provider)
                    )
            ),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set product type to variable.
        wp_set_object_terms($post_id, 'variable', 'product_type');

        // Set core product meta.
        update_post_meta($post_id, '_sku', $this->generate_sku($provider, $provider_product_id));
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, '_manage_stock', 'no');

        // Set category term if it maps.
        if ($category) {
            $term = term_exists($category, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($category, 'product_cat');
            }
            if ($term && !is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                wp_set_object_terms($post_id, (int) $term_id, 'product_cat', true);
            }
        }

        return $post_id;
    }

    /**
     * Build WooCommerce attribute definitions from variant data.
     *
     * @param array $variants
     * @return array ['size' => ['Small','Medium','Large'], 'color' => ['Black','White']]
     */
    private function build_attributes(array $variants): array
    {
        $attrs = [];

        foreach ($variants as $v) {
            $size  = trim($v['size'] ?? '');
            $color = trim($v['color'] ?? '');

            if ($size !== '') {
                $attrs['size'][$size] = true;
            }
            if ($color !== '') {
                $attrs['color'][$color] = true;
            }
        }

        // Convert sets to indexed arrays.
        $result = [];
        foreach ($attrs as $name => $values) {
            $result[$name] = array_keys($values);
        }

        return $result;
    }

    /**
     * Register global attributes on the parent variable product.
     *
     * @param int   $wc_product_id
     * @param array $attributes ['size' => ['S','M'], 'color' => ['Black']]
     * @return void
     */
    private function set_product_attributes(int $wc_product_id, array $attributes): void
    {
        if (empty($attributes)) {
            return;
        }

        $product_attributes = [];

        foreach ($attributes as $name => $values) {
            $attr_name = wc_sanitize_taxonomy_name($name);

            // Ensure the global attribute taxonomy exists.
            $taxonomy = wc_attribute_taxonomy_name($attr_name);
            if (!taxonomy_exists($taxonomy)) {
                $this->register_attribute_taxonomy($attr_name, $name);
            }

            // Assign terms.
            wp_set_object_terms($wc_product_id, $values, $taxonomy, true);

            $product_attributes[$taxonomy] = [
                'name'         => $taxonomy,
                'value'        => '',
                'position'     => count($product_attributes),
                'is_visible'   => 1,
                'is_variation' => 1,
                'is_taxonomy'  => 1,
            ];
        }

        update_post_meta($wc_product_id, '_product_attributes', $product_attributes);
    }

    /**
     * Register a product attribute taxonomy if it doesn't exist.
     *
     * @param string $attr_name Sanitized attribute name (slug).
     * @param string $label     Human-readable label.
     * @return void
     */
    private function register_attribute_taxonomy(string $attr_name, string $label): void
    {
        global $wpdb;

        $taxonomy = wc_attribute_taxonomy_name($attr_name);

        if (taxonomy_exists($taxonomy)) {
            return;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
             WHERE attribute_name = %s LIMIT 1",
            $attr_name
        ));

        if (!$exists) {
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                [
                    'attribute_name'    => $attr_name,
                    'attribute_label'   => $label,
                    'attribute_type'    => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public'  => 0,
                ],
                ['%s', '%s', '%s', '%s', '%d']
            );
            // Flush the WooCommerce attribute cache.
            delete_transient('wc_attribute_taxonomies');
        }

        // Register the taxonomy on the current request so term assignment works.
        register_taxonomy(
            $taxonomy,
            ['product'],
            [
                'labels'       => ['name' => $label],
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            ]
        );
    }

    /**
     * Create a single variation on the parent product.
     *
     * @param int    $wc_product_id
     * @param array  $variant       Normalized variant data.
     * @param array  $attributes    Built attributes ['size'=>['S','M'], ...].
     * @param string $fallback_thumb URL to use if variant has no image.
     * @return int|\WP_Error
     */
    private function create_variation(
        int $wc_product_id,
        array $variant,
        array $attributes,
        string $fallback_thumb
    ) {
        $variation_post = [
            'post_type'   => 'product_variation',
            'post_parent' => $wc_product_id,
            'post_status' => 'publish',
            'post_title'  => sprintf(
                'Variation #%d of %d',
                $variant['variant_id'] ?? 0,
                $wc_product_id
            ),
        ];

        $variation_id = wp_insert_post($variation_post, true);

        if (is_wp_error($variation_id)) {
            return $variation_id;
        }

        // Price.
        $cost  = (float) ($variant['cost'] ?? 0);
        $price = $this->apply_markup($cost);
        update_post_meta($variation_id, '_price', wc_format_decimal($price));
        update_post_meta($variation_id, '_regular_price', wc_format_decimal($price));
        update_post_meta($variation_id, '_pod_provider_cost', wc_format_decimal($cost));

        // SKU.
        $sku = $variant['sku'] ?: $this->generate_variant_sku($wc_product_id, $variant);
        update_post_meta($variation_id, '_sku', sanitize_text_field($sku));

        // Stock.
        update_post_meta($variation_id, '_stock_status', 'instock');
        update_post_meta($variation_id, '_manage_stock', 'no');

        // Attributes.
        $variation_attributes = [];
        foreach ($attributes as $attr_name => $values) {
            $taxonomy = wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name($attr_name));
            $value = '';

            if ($attr_name === 'size') {
                $value = $variant['size'] ?? '';
            } elseif ($attr_name === 'color') {
                $value = $variant['color'] ?? '';
            }

            if ($value !== '') {
                $variation_attributes[$taxonomy] = $value;
            }
        }
        update_post_meta($variation_id, '_product_attributes', $variation_attributes);

        // Link to provider data.
        update_post_meta($variation_id, '_pod_variant_id', $variant['variant_id'] ?? '');
        update_post_meta($variation_id, '_pod_provider', $variant['provider'] ?? '');

        // Variation image.
        $image_url = $variant['image'] ?: $fallback_thumb;
        if ($image_url) {
            $img_id = $this->sideload_image($image_url, $variation_id);
            if ($img_id && !is_wp_error($img_id)) {
                update_post_meta($variation_id, '_thumbnail_id', $img_id);
            }
        }

        return $variation_id;
    }

    /**
     * Apply markup to a variant cost.
     *
     * @param float $cost
     * @return float
     */
    private function apply_markup(float $cost): float
    {
        return round($cost * (1 + $this->markup_percent / 100), 2);
    }

    /**
     * Download an image from a URL and attach it to a post.
     *
     * @param string $url     Image URL.
     * @param int    $post_id Post to attach to.
     * @return int|\WP_Error Attachment ID or error.
     */
    private function sideload_image(string $url, int $post_id)
    {
        if (empty($url)) {
            return new \WP_Error('empty_url', __('No image URL provided.', 'pod-aggregator'));
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download the file first so we can detect its true MIME type.
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Build a filename with a proper extension from the actual file content.
        $filename = wp_basename(parse_url($url, PHP_URL_PATH)) ?: 'product-image';

        // If the filename has no recognized image extension, detect and add one.
        // Printful CDN URLs have no extension (e.g. ...fe2c_l), which causes
        // WordPress media_handle_sideload to fail because it can't detect MIME.
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

        if (!in_array($ext, $image_exts, true)) {
            // Detect MIME from the actual file contents.
            $mime = false;
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($tmp);
            } elseif (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                }
            }

            if ($mime && str_starts_with($mime, 'image/')) {
                $ext = str_replace('image/', '', $mime);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            } else {
                $ext = 'jpg';
            }

            // Rename the temp file with the extension.
            $new_tmp = $tmp . '.' . $ext;
            if (rename($tmp, $new_tmp)) {
                $tmp = $new_tmp;
            }
            $filename .= '.' . $ext;
        }

        $file_array = [
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp,
        ];

        $id = media_handle_sideload($file_array, $post_id);

        // Clean up temp file if media_handle_sideload didn't.
        if (is_wp_error($id) && file_exists($tmp)) {
            @unlink($tmp);
        }

        return $id;
    }

    /**
     * Generate a simple SKU for the parent product.
     *
     * @param string $provider
     * @param string $provider_product_id
     * @return string
     */
    private function generate_sku(string $provider, string $provider_product_id): string
    {
        return strtoupper(substr($provider, 0, 3)) . '-' . $provider_product_id;
    }

    /**
     * Generate a SKU for a variant.
     *
     * @param int   $wc_product_id
     * @param array $variant
     * @return string
     */
    private function generate_variant_sku(int $wc_product_id, array $variant): string
    {
        $base = get_post_meta($wc_product_id, '_sku', true) ?: 'POD-' . $wc_product_id;
        return $base . '-' . ($variant['variant_id'] ?? '0');
    }
}
