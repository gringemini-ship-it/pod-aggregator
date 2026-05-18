<?php
/**
 * POD Aggregator — Printful API Adapter.
 *
 * @package POD_Aggregator\Provider
 */

namespace POD_Aggregator\Provider;

use POD_Aggregator\Provider_Interface;

/**
 * Printful adapter implementing Provider_Interface.
 *
 * @since 1.0.0
 */
class Printful_Adapter implements Provider_Interface
{
    /** @var string API key. */
    private $api_key;

    /** @var int Store ID for order operations. */
    private $store_id;

    /** @var string Base API URL. */
    private $base_url = 'https://api.printful.com';

    /** @var array Cached products. */
    private $products_cache = [];

    /** @var int Cache TTL in seconds (1 hour). */
    private $cache_ttl = 3600;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $settings = get_site_option('pod_aggregator_settings', []);
        $this->api_key  = $settings['printful_api_key'] ?? '';
        $this->store_id = (int) ($settings['printful_store_id'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function get_slug(): string
    {
        return 'printful';
    }

    public function get_name(): string
    {
        return 'Printful';
    }

    public function is_configured(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Build authorized headers for Printful API.
     *
     * @return array
     */
    private function auth_headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Make a GET request to Printful API.
     *
     * @param string $endpoint API endpoint path.
     * @param array  $args    Query args.
     * @return array|\WP_Error
     */
    private function get(string $endpoint, array $args = [])
    {
        $url = add_query_arg($args, $this->base_url . $endpoint);

        $response = wp_remote_get($url, [
            'headers' => $this->auth_headers(),
            'timeout' => 20,
        ]);

        return $this->parse_response($response);
    }

    /**
     * Make a POST request to Printful API.
     *
     * @param string $endpoint API endpoint path.
     * @param array  $body    JSON-serializable body.
     * @return array|\WP_Error
     */
    private function post(string $endpoint, array $body)
    {
        $response = wp_remote_post($this->base_url . $endpoint, [
            'headers' => $this->auth_headers(),
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        return $this->parse_response($response);
    }

    /**
     * Parse WP HTTP response into data or WP_Error.
     *
     * @param array|\WP_Error $response Raw WP HTTP response.
     * @return array|\WP_Error
     */
    private function parse_response($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);

        if ($code >= 400) {
            $msg = $body['error']['message'] ?? "HTTP {$code}";
            if ($raw && !isset($body['error']['message'])) {
                $msg .= ' — ' . substr(wp_strip_all_tags($raw), 0, 200);
            }
            return new \WP_Error('printful_api_error', $msg);
        }

        return $body['result'] ?? $body;
    }

    // -------------------------------------------------------------------------
    // Catalog
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Fetches the full Printful catalog by iterating through product categories.
     *
     * The Printful /products endpoint returns only ~98 featured products when
     * called without a category_id, and ignores the offset parameter entirely.
     * To get the complete catalog (300+ products), we must query each product
     * category individually and deduplicate.
     *
     * Caches for cache_ttl seconds. No store_id required for catalog access.
     */
    public function get_products(): array
    {
        if (empty($this->api_key)) {
            $this->log_sync('fetch_products', 'error', null, null, null, 'No Printful API key configured');
            return [];
        }

        if (!empty($this->products_cache)) {
            return $this->products_cache;
        }

        $transient_key = 'pod_agg_printful_products_' . md5($this->api_key);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            $this->products_cache = $cached;
            return $cached;
        }

        $all_products = [];
        $seen_ids     = [];

        // Helper: ingest a product page, skipping duplicates.
        $ingest = function (array $page) use (&$all_products, &$seen_ids) {
            foreach ($page as $p) {
                $pid = (string) ($p['id'] ?? '');
                if ($pid === '' || isset($seen_ids[$pid])) {
                    continue;
                }
                $seen_ids[$pid] = true;
                $all_products[] = $this->normalize_product($p);
            }
        };

        // 1. Fetch the main catalog page (no category filter). This returns
        //    ~98 featured products that span all categories.
        $main = $this->get('/products', ['limit' => 100]);
        if (!is_wp_error($main) && is_array($main)) {
            $ingest($main);
        }

        // 2. Discover product categories.
        $categories = $this->get_categories();
        if (!empty($categories)) {
            $product_cats = $this->filter_product_categories($categories);

            foreach ($product_cats as $cat_id) {
                $page = $this->get('/products', [
                    'limit'       => 100,
                    'category_id' => $cat_id,
                ]);

                if (is_wp_error($page)) {
                    continue;
                }

                if (!is_array($page) || empty($page)) {
                    continue;
                }

                $ingest($page);
            }
        }

        if (empty($all_products)) {
            $this->log_sync('fetch_products', 'error', null, null, null, 'Printful catalog returned no products');
            return [];
        }

        set_transient($transient_key, $all_products, $this->cache_ttl);

        $this->products_cache = $all_products;
        return $all_products;
    }

    /**
     * Fetch all Printful product categories.
     *
     * @return array Category objects, each with id, parent_id, title.
     */
    private function get_categories(): array
    {
        $result = $this->get('/categories');

        if (is_wp_error($result)) {
            return [];
        }

        return $result['categories'] ?? [];
    }

    /**
     * Filter categories down to real product categories.
     *
     * Excludes:
     *   - "Collections" (parent_id 116) and their children
     *   - "Brands" (parent_id 159) and their children
     *   - "All *" aggregator categories (ids 226-230, 277)
     *
     * These contain duplicate products already visible in the real
     * product categories (parents 1-5) and their subcategories.
     *
     * @param array $categories Raw categories from /categories.
     * @return int[] Category IDs to query for products.
     */
    private function filter_product_categories(array $categories): array
    {
        // Build parent lookup: parent_id → [child_ids].
        $children = [];
        foreach ($categories as $c) {
            $pid = (int) ($c['parent_id'] ?? 0);
            $children[$pid][] = (int) $c['id'];
        }

        // Walk from the top-level product categories (1-5) and collect
        // all descendant category IDs recursively.
        $aggregator_ids  = [226, 227, 228, 229, 230, 277];

        $product_cat_ids = [];

        $walk = function (int $parent_id) use (&$walk, $children, &$product_cat_ids, $aggregator_ids) {
            if (!isset($children[$parent_id])) {
                return;
            }
            foreach ($children[$parent_id] as $child_id) {
                if (in_array($child_id, $aggregator_ids, true)) {
                    continue;
                }
                $product_cat_ids[] = $child_id;
                // Recurse into grandchildren.
                $walk($child_id);
            }
        };

        // Start from the five top-level product category parents.
        // Include the parents themselves (they are parent_id=0 entries).
        foreach ([1, 2, 3, 4, 5] as $root) {
            $product_cat_ids[] = $root;
            $walk($root);
        }

        return array_unique($product_cat_ids);
    }

    /**
     * {@inheritDoc}
     *
     * Fetch a single product with full variant data from /products/{id}.
     * Response is {product: {...}, variants: [{...}]}.
     */
    public function get_product(string $product_id)
    {
        $result = $this->get('/products/' . rawurlencode($product_id));

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->normalize_product_detail($result);
    }

    /**
     * {@inheritDoc}
     */
    public function get_product_variants(string $product_id): array
    {
        $product = $this->get_product($product_id);

        if (is_wp_error($product)) {
            return [];
        }

        return $product['variants'] ?? [];
    }

    /**
     * Normalize a Printful catalog product (list format — no variants).
     *
     * @param array $p Raw Printful product from /products list.
     * @return array Normalized array.
     */
    private function normalize_product(array $p): array
    {
        return [
            'provider'            => 'printful',
            'provider_product_id' => (string) ($p['id'] ?? ''),
            'name'                => $p['title'] ?? $p['name'] ?? '',
            'description'         => $p['description'] ?? '',
            'thumbnail_url'       => $p['image'] ?? '',
            'variants'            => [],
            'model'               => $p['model'] ?? $p['type_name'] ?? '',
            'brand'               => $p['brand'] ?? '',
            'category'            => $p['type'] ?? '',
            'files'               => $p['files'] ?? [],
            'dimensions'          => $p['dimensions'] ?? [],
            'variant_count'       => (int) ($p['variant_count'] ?? 0),
        ];
    }

    /**
     * Normalize a Printful product detail response (/products/{id}).
     *
     * Response format: {product: {...}, variants: [{...}, ...]}
     *
     * @param array $r Raw response from parse_response.
     * @return array Normalized array with variants.
     */
    private function normalize_product_detail(array $r): array
    {
        // Detail response has product and variants as sibling keys.
        $p = $r['product'] ?? $r;

        $variants = [];
        if (isset($r['variants']) && is_array($r['variants'])) {
            $variants = $r['variants'];
        } elseif (isset($p['variants']) && is_array($p['variants'])) {
            $variants = $p['variants'];
        }

        return [
            'provider'            => 'printful',
            'provider_product_id' => (string) ($p['id'] ?? ''),
            'name'                => $p['title'] ?? $p['name'] ?? '',
            'description'         => $p['description'] ?? '',
            'thumbnail_url'       => $p['image'] ?? '',
            'variants'            => array_map([$this, 'normalize_variant'], $variants),
            'model'               => $p['model'] ?? $p['type_name'] ?? '',
            'brand'               => $p['brand'] ?? '',
            'category'            => $p['type'] ?? '',
            'files'              => $p['files'] ?? [],
            'dimensions'         => $p['dimensions'] ?? [],
            'variant_count'      => count($variants),
        ];
    }

    /**
     * Normalize a Printful variant.
     *
     * In the current Printful API, variants have a 'price' field which is
     * what Printful charges (the cost). There is no separate 'cost' field.
     *
     * @param array $v Raw variant.
     * @return array
     */
    private function normalize_variant(array $v): array
    {
        $price = (float) ($v['price'] ?? 0);

        return [
            'variant_id'   => (string) ($v['id'] ?? ''),
            'name'         => $v['name'] ?? '',
            'sku'          => $v['sku'] ?? '',
            'price'        => $price,
            'cost'         => $price,
            'currency'     => $v['currency'] ?? 'USD',
            'size'         => $v['size'] ?? '',
            'color'        => $v['color'] ?? '',
            'color_code'   => $v['color_code'] ?? '',
            'image'        => $v['image'] ?? '',
            'availability' => $v['availability_regions'] ?? [],
        ];
    }

    // -------------------------------------------------------------------------
    // Pricing
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Printful variant price is the fulfillment cost. Markup is applied
     * by the importer; this method returns the calculated retail price.
     */
    public function calculate_price(array $variant, float $retail_price = 0.0): float
    {
        // In the current API, 'price' IS the cost.
        $cost = (float) ($variant['cost'] ?? $variant['price'] ?? 0);

        if ($retail_price > 0) {
            return $retail_price;
        }

        // Default markup: 30% on cost.
        return round($cost * 1.30, 2);
    }

    // -------------------------------------------------------------------------
    // Mockups
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function get_mockups(string $product_id, array $options = []): array
    {
        $product = $this->get_product($product_id);

        if (is_wp_error($product) || empty($product['thumbnail_url'])) {
            return [];
        }

        return [
            [
                'url'    => $product['thumbnail_url'],
                'type'   => 'default',
                'width'  => 0,
                'height' => 0,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Submits an order to Printful. Requires a store_id (Manual Order / API
     * platform store) configured in plugin settings.
     */
    public function submit_order(array $order_data)
    {
        if (empty($this->store_id)) {
            $err = new \WP_Error(
                'printful_no_store_id',
                __('No Printful store ID configured. Add it in POD Aggregator → Settings.', 'pod-aggregator')
            );
            $this->log_sync(
                'submit_order',
                'error',
                null,
                $order_data['woo_order_id'] ?? null,
                null,
                $err->get_error_message()
            );
            return $err;
        }

        $items = [];
        foreach ($order_data['items'] as $item) {
            $items[] = [
                'variant_id' => (int) $item['variant_id'],
                'quantity'   => (int) $item['qty'],
                'files'      => $this->build_files($item['design_data'] ?? []),
            ];
        }

        $address = $order_data['shipping_address'];

        $payload = [
            'store_id'      => $this->store_id,
            'external_id'   => 'wc_order_' . $order_data['woo_order_id'],
            'shipping'      => 'STANDARD',
            'items'         => $items,
            'recipient'     => [
                'name'       => sanitize_text_field($address['name'] ?? ''),
                'address1'   => sanitize_text_field($address['address1'] ?? ''),
                'address2'   => sanitize_text_field($address['address2'] ?? ''),
                'city'       => sanitize_text_field($address['city'] ?? ''),
                'state_code' => sanitize_text_field($address['state'] ?? ''),
                'country_code' => sanitize_text_field($address['country'] ?? ''),
                'zip'        => sanitize_text_field($address['zip'] ?? ''),
                'phone'      => sanitize_text_field($address['phone'] ?? ''),
                'email'      => sanitize_email($address['email'] ?? ''),
            ],
        ];

        $result = $this->post('/orders', $payload);

        $this->log_sync(
            'submit_order',
            is_wp_error($result) ? 'error' : 'success',
            $result['id'] ?? null,
            $order_data['woo_order_id'],
            $payload,
            is_wp_error($result) ? $result->get_error_message() : null
        );

        return $result;
    }

    /**
     * Build Printful file objects from design data.
     *
     * @param array $design_data
     * @return array
     */
    private function build_files(array $design_data): array
    {
        if (empty($design_data)) {
            return [
                [
                    'type' => 'default',
                    'url'  => '',
                ],
            ];
        }

        $files = [];
        foreach ((array) $design_data as $file) {
            $files[] = [
                'type'    => $file['type'] ?? 'default',
                'url'     => esc_url_raw($file['url'] ?? ''),
                'options' => $file['options'] ?? [],
            ];
        }

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function get_order_status(string $external_order_id): array
    {
        $args = [];
        if ($this->store_id) {
            $args['store_id'] = $this->store_id;
        }

        $result = $this->get('/orders/' . rawurlencode($external_order_id), $args);

        if (is_wp_error($result)) {
            return ['status' => 'error', 'message' => $result->get_error_message()];
        }

        return [
            'status'          => $result['status'] ?? 'unknown',
            'tracking_number' => $result['shipment_number'] ?? '',
            'tracking_url'    => $result['shipment_tracking_url'] ?? '',
            'carrier'         => $result['tracking_carrier'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function get_tracking_url(string $tracking_number): string
    {
        return 'https://www.printful.com/tracking/' . rawurlencode($tracking_number);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Log a sync event to our custom table.
     *
     * @param string      $event_type
     * @param string      $status
     * @param string|null $external_id
     * @param int|null    $order_id
     * @param array|null  $payload
     * @param string|null $error_message
     * @return void
     */
    private function log_sync(
        string $event_type,
        string $status,
        ?string $external_id,
        ?int $order_id,
        ?array $payload = null,
        ?string $error_message = null
    ) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'pod_aggregator_sync_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'provider'      => 'printful',
                'event_type'    => $event_type,
                'external_id'   => $external_id,
                'order_id'      => $order_id,
                'status'        => $status,
                'payload'       => $payload ? wp_json_encode($payload) : null,
                'error_message' => $error_message,
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }
}
