<?php
/**
 * POD Aggregator — Printify API Adapter.
 *
 * @package POD_Aggregator\Provider
 */

namespace POD_Aggregator\Provider;

use POD_Aggregator\Provider_Interface;

/**
 * Printify adapter implementing Provider_Interface.
 *
 * @since 1.0.0
 */
class Printify_Adapter implements Provider_Interface
{
    /** @var string API token. */
    private $api_token;

    /** @var string Shop ID. */
    private $shop_id;

    /** @var string Base API URL. */
    private $base_url = 'https://api.printify.com/v1';

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
        $this->api_token = $settings['printify_api_key'] ?? '';
        $this->shop_id   = $settings['printify_shop_id'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function get_slug(): string
    {
        return 'printify';
    }

    public function get_name(): string
    {
        return 'Printify';
    }

    public function is_configured(): bool
    {
        return !empty($this->api_token) && !empty($this->shop_id);
    }

    /**
     * Build authorized headers for Printify API.
     *
     * @return array
     */
    private function auth_headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->api_token,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a GET request to Printify API.
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
     * Make a POST request to Printify API.
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
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $body['message'] ?? $body['error'] ?? "HTTP {$code}";
            return new \WP_Error('printify_api_error', $msg);
        }

        // Printify wraps data in a `data` key on catalog endpoints.
        return $body['data'] ?? $body;
    }

    // -------------------------------------------------------------------------
    // Catalog
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Uses Printify product catalog endpoint with pagination.
     * Caches result for cache_ttl seconds.
     */
    public function get_products(): array
    {
        if (!empty($this->products_cache)) {
            return $this->products_cache;
        }

        $transient_key = 'pod_agg_printify_products_' . md5($this->api_token . $this->shop_id);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            $this->products_cache = $cached;
            return $cached;
        }

        $all_products = [];
        $page = 1;
        $has_more = true;

        // Printify pagination: ?page=1&limit=100
        while ($has_more) {
            $result = $this->get("/shops/{$this->shop_id}/products.json", [
                'page'  => $page,
                'limit' => 100,
            ]);

            if (is_wp_error($result)) {
                $this->log_sync('fetch_products', 'error', null, null, [
                    'error' => $result->get_error_message(),
                ]);
                return [];
            }

            $products = is_array($result) ? $result : [];
            $all_products = array_merge($all_products, $products);

            // Printify returns empty array when no more pages.
            $has_more = !empty($products);
            $page++;
        }

        // Normalize each product.
        $normalized = array_map([$this, 'normalize_product'], $all_products);

        set_transient($transient_key, $normalized, $this->cache_ttl);

        $this->products_cache = $normalized;
        return $normalized;
    }

    /**
     * {@inheritDoc}
     */
    public function get_product(string $product_id)
    {
        $result = $this->get("/shops/{$this->shop_id}/products/{$product_id}.json");

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->normalize_product($result);
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
     * Normalize a Printify product into our standard format.
     *
     * Printify product shape:
     * {
     *   "id": 123,
     *   "title": "Product Name",
     *   "description": "...",
     *   "images": [{ "url": "..." }],
     *   "variants": [...],
     *   "visible": true,
     *   "is_locked": false,
     *   ...
     * }
     *
     * @param array $p Raw Printify product.
     * @return array Normalized array.
     */
    private function normalize_product(array $p): array
    {
        $images = $p['images'] ?? [];
        $thumbnail = '';
        if (is_array($images) && !empty($images)) {
            // First image is the main product image.
            $thumbnail = is_array($images[0]) ? ($images[0]['url'] ?? '') : ($images[0] ?? '');
        }

        return [
            'provider'             => 'printify',
            'provider_product_id'  => (string) ($p['id'] ?? ''),
            'name'                 => $p['title'] ?? '',
            'description'          => $p['description'] ?? '',
            'thumbnail_url'        => $thumbnail,
            'variants'             => array_map([$this, 'normalize_variant'], $p['variants'] ?? []),
            'model'                => '',
            'brand'                => '',
            'category'             => $p['type'] ?? '',
            'files'                => $p['files'] ?? [],
            'dimensions'           => [],
        ];
    }

    /**
     * Normalize a Printify variant.
     *
     * Printify variant shape:
     * {
     *   "id": 456,
     *   "title": "Variant Name",
     *   "sku": "SKU123",
     *   "price": 19.99,
     *   "cost": 10.00,
     *   "currency": "USD",
     *   "options": [{"name": "Size", "value": "XL"}],
     *   "is_enabled": true,
     *   "is_available": true,
     *   "image": { "url": "..." }
     * }
     *
     * @param array $v Raw variant.
     * @return array
     */
    private function normalize_variant(array $v): array
    {
        // Extract size/color from options for consistent field names.
        $size  = '';
        $color = '';
        $options = $v['options'] ?? [];
        foreach ((array) $options as $opt) {
            if (is_array($opt)) {
                $name  = strtolower($opt['name'] ?? '');
                $value = $opt['value'] ?? '';
                if ($name === 'size') {
                    $size = $value;
                }
                if ($name === 'color' || $name === 'colour') {
                    $color = $value;
                }
            }
        }

        $variant_image = '';
        $img = $v['image'] ?? [];
        if (is_array($img)) {
            $variant_image = $img['url'] ?? '';
        }

        return [
            'variant_id'   => (string) ($v['id'] ?? ''),
            'name'         => $v['title'] ?? '',
            'sku'          => $v['sku'] ?? '',
            'price'        => (float) ($v['price'] ?? 0),
            'cost'         => (float) ($v['cost'] ?? 0),
            'currency'     => $v['currency'] ?? 'USD',
            'size'         => $size,
            'color'        => $color,
            'image'        => $variant_image,
            'availability' => !empty($v['is_available']) ? ['available'] : [],
        ];
    }

    // -------------------------------------------------------------------------
    // Pricing
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Printify returns cost in USD. If no retail_price is passed, returns cost + default markup.
     */
    public function calculate_price(array $variant, float $retail_price = 0.0): float
    {
        $cost = (float) ($variant['cost'] ?? 0);

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
     *
     * Fetches product mockup images from Printify's product images.
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
     * Order data shape expected:
     * [
     *   'woo_order_id'      => int,
     *   'items'             => [[
     *     'variant_id'   => string,
     *     'qty'         => int,
     *     'design_data' => array (print file info),
     *   ]],
     *   'shipping_address' => [
     *     'name'    => string,
     *     'address1'=> string,
     *     'address2'=> string,
     *     'city'   => string,
     *     'state'  => string,
     *     'zip'    => string,
     *     'country'=> string,
     *   ],
     * ]
     */
    public function submit_order(array $order_data)
    {
        $address = $order_data['shipping_address'];

        $line_items = [];
        foreach ($order_data['items'] as $item) {
            $line_items[] = [
                'id'        => (int) $item['variant_id'],
                'quantity'  => (int) $item['qty'],
                'files'     => $this->build_files($item['design_data'] ?? []),
            ];
        }

        $payload = [
            'external_id' => 'wc_order_' . $order_data['woo_order_id'],
            'line_items'  => $line_items,
            'shipping_address' => [
                'first_name' => sanitize_text_field(explode(' ', $address['name'] ?? '')[0] ?? ''),
                'last_name'  => sanitize_text_field(implode(' ', explode(' ', $address['name'] ?? '', -1)) ?: ''),
                'address1'   => sanitize_text_field($address['address1'] ?? ''),
                'address2'   => sanitize_text_field($address['address2'] ?? ''),
                'city'       => sanitize_text_field($address['city'] ?? ''),
                'state'      => sanitize_text_field($address['state'] ?? ''),
                'zip'        => sanitize_text_field($address['zip'] ?? ''),
                'country'    => sanitize_text_field($address['country'] ?? ''),
                'phone'      => sanitize_text_field($address['phone'] ?? ''),
                'email'      => sanitize_email($address['email'] ?? ''),
            ],
        ];

        $result = $this->post('/orders.json', $payload);

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
     * Build Printify file objects from design data.
     *
     * @param array $design_data {
     *   'url'      => string (remote print file URL),
     *   'type'     => 'default'|'front'|'back'|'custom',
     *   'position' => string ('front'|'back'|'custom'),
     *   'options'  => array,
     * }
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
        $result = $this->get("/orders/{$external_order_id}.json");

        if (is_wp_error($result)) {
            return ['status' => 'error', 'message' => $result->get_error_message()];
        }

        // Printify order status shape:
        // { "id": "...", "status": "fulfillment_pending", "tracking": "..." }
        $status = $result['status'] ?? 'unknown';

        // Map Printify status → standard status.
        $status_map = [
            'pending'                => 'pending',
            'fulfillment_pending'    => 'pending',
            'fulfillment_started'    => 'processing',
            'partially_fulfilled'    => 'processing',
            'fulfilled'              => 'shipped',
            'partially_shipped'      => 'partially_shipped',
            'cancelled'             => 'cancelled',
        ];

        return [
            'status'          => $status_map[$status] ?? $status,
            'raw_status'      => $status,
            'tracking_number' => $result['tracking_number'] ?? '',
            'tracking_url'    => $result['tracking_url'] ?? '',
            'carrier'         => $result['carrier'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function get_tracking_url(string $tracking_number): string
    {
        return 'https://www.printify.com/orders/' . rawurlencode($tracking_number);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Log a sync event to our custom table.
     *
     * @param string      $event_type    Event type (e.g. submit_order, fetch_products).
     * @param string      $status        pending|success|error.
     * @param string|null $external_id   Provider's external order ID.
     * @param int|null    $order_id      WooCommerce order ID.
     * @param array|null  $payload       JSON-serializable payload.
     * @param string|null $error_message Error message if error.
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

        // Table might not exist yet during early init.
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'provider'      => 'printify',
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
