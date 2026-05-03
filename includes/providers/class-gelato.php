<?php
/**
 * POD Aggregator — Gelato API Adapter.
 *
 * @package POD_Aggregator\Provider
 */

namespace POD_Aggregator\Provider;

use POD_Aggregator\Provider_Interface;

/**
 * Gelato adapter implementing Provider_Interface.
 *
 * @since 1.0.0
 */
class Gelato_Adapter implements Provider_Interface
{
    /** @var string API token. */
    private $api_token;

    /** @var string Base API URL. */
    private $base_url = 'https://api.gelato.com/v1';

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
        $this->api_token = $settings['gelato_api_key'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function get_slug(): string
    {
        return 'gelato';
    }

    public function get_name(): string
    {
        return 'Gelato';
    }

    public function is_configured(): bool
    {
        return !empty($this->api_token);
    }

    /**
     * Build authorized headers for Gelato API.
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
     * Make a GET request to Gelato API.
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
     * Make a POST request to Gelato API.
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
            return new \WP_Error('gelato_api_error', $msg);
        }

        return $body['data'] ?? $body;
    }

    // -------------------------------------------------------------------------
    // Catalog
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Uses Gelato product catalog endpoint. Caches for cache_ttl seconds.
     */
    public function get_products(): array
    {
        if (!empty($this->products_cache)) {
            return $this->products_cache;
        }

        $transient_key = 'pod_agg_gelato_products_' . md5($this->api_token);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            $this->products_cache = $cached;
            return $cached;
        }

        $result = $this->get('/products', ['limit' => 100]);

        if (is_wp_error($result)) {
            $this->log_sync('fetch_products', 'error', null, null, [
                'error' => $result->get_error_message(),
            ]);
            return [];
        }

        // Gelato returns { data: { items: [...] } } or { items: [...] }
        $products = [];
        if (isset($result['items'])) {
            $products = $result['items'];
        } elseif (is_array($result)) {
            $products = $result;
        }

        $normalized = array_map([$this, 'normalize_product'], $products);

        set_transient($transient_key, $normalized, $this->cache_ttl);

        $this->products_cache = $normalized;
        return $normalized;
    }

    /**
     * {@inheritDoc}
     */
    public function get_product(string $product_id)
    {
        $result = $this->get("/products/{$product_id}");

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
     * Normalize a Gelato product into our standard format.
     *
     * Gelato product shape:
     * {
     *   "id": "prod_xxx",
     *   "name": "Product Name",
     *   "description": "Description",
     *   "images": [{ "url": "...", "type": "default" }],
     *   "variants": [...],
     *   "type": "ProductType"
     * }
     *
     * @param array $p Raw Gelato product.
     * @return array Normalized array.
     */
    private function normalize_product(array $p): array
    {
        $images = $p['images'] ?? [];
        $thumbnail = '';
        if (is_array($images) && !empty($images)) {
            $thumbnail = is_array($images[0]) ? ($images[0]['url'] ?? '') : ($images[0] ?? '');
        }

        return [
            'provider'             => 'gelato',
            'provider_product_id' => (string) ($p['id'] ?? ''),
            'name'                 => $p['name'] ?? '',
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
     * Normalize a Gelato variant.
     *
     * Gelato variant shape:
     * {
     *   "id": "var_xxx",
     *   "sku": "SKU",
     *   "price": { "USD": 19.99 },
     *   "cost": { "USD": 10.00 },
     *   "attributes": { "size": "XL", "color": "Red" },
     *   "availability": "in_stock"
     * }
     *
     * @param array $v Raw variant.
     * @return array
     */
    private function normalize_variant(array $v): array
    {
        $size  = '';
        $color = '';
        $attrs = $v['attributes'] ?? [];
        if (is_array($attrs)) {
            foreach ($attrs as $key => $val) {
                $key_lower = strtolower($key);
                if ($key_lower === 'size') {
                    $size = (string) $val;
                }
                if ($key_lower === 'color' || $key_lower === 'colour') {
                    $color = (string) $val;
                }
            }
        }

        // Price and cost may be arrays like { "USD": 19.99 } or flat.
        $price = is_array($v['price'] ?? null)
            ? (float) reset($v['price'])
            : (float) ($v['price'] ?? 0);
        $cost = is_array($v['cost'] ?? null)
            ? (float) reset($v['cost'])
            : (float) ($v['cost'] ?? 0);

        $availability = [];
        $avail = $v['availability'] ?? '';
        if ($avail === 'in_stock' || $avail === 'available') {
            $availability = ['in_stock'];
        }

        return [
            'variant_id'   => (string) ($v['id'] ?? ''),
            'name'         => $v['name'] ?? $v['sku'] ?? '',
            'sku'          => $v['sku'] ?? '',
            'price'        => $price,
            'cost'         => $cost,
            'currency'     => 'USD',
            'size'         => $size,
            'color'        => $color,
            'image'        => '',
            'availability' => $availability,
        ];
    }

    // -------------------------------------------------------------------------
    // Pricing
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Gelato pricing is multi-currency. Uses USD values.
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
     * Fetches product mockup images from Gelato product images.
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
        $name_parts = explode(' ', $address['name'] ?? '', 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        $items = [];
        foreach ($order_data['items'] as $item) {
            $items[] = [
                'productId'  => $item['provider_product_id'] ?? '',
                'variantId'  => $item['variant_id'],
                'quantity'   => (int) $item['qty'],
                'printFiles' => $this->build_print_files($item['design_data'] ?? []),
            ];
        }

        $payload = [
            'externalId' => 'wc_order_' . $order_data['woo_order_id'],
            'items'      => $items,
            'shippingAddress' => [
                'firstName' => sanitize_text_field($first_name),
                'lastName'  => sanitize_text_field($last_name),
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
     * Build Gelato print file objects from design data.
     *
     * @param array $design_data Design data array.
     * @return array
     */
    private function build_print_files(array $design_data): array
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
                'type' => $file['type'] ?? 'default',
                'url'  => esc_url_raw($file['url'] ?? ''),
            ];
        }

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function get_order_status(string $external_order_id): array
    {
        $result = $this->get("/orders/{$external_order_id}");

        if (is_wp_error($result)) {
            return ['status' => 'error', 'message' => $result->get_error_message()];
        }

        // Gelato status values: pending, processing, fulfilled, cancelled
        $status = $result['status'] ?? 'unknown';

        $status_map = [
            'pending'    => 'pending',
            'processing' => 'processing',
            'fulfilled'  => 'shipped',
            'shipped'    => 'shipped',
            'cancelled'  => 'cancelled',
            'failed'     => 'failed',
        ];

        return [
            'status'          => $status_map[$status] ?? $status,
            'raw_status'      => $status,
            'tracking_number' => $result['trackingNumber'] ?? '',
            'tracking_url'    => $result['trackingUrl'] ?? '',
            'carrier'         => $result['carrier'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function get_tracking_url(string $tracking_number): string
    {
        return 'https://www.gelato.com/orders/' . rawurlencode($tracking_number);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Log a sync event to our custom table.
     *
     * @param string      $event_type    Event type.
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

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'provider'      => 'gelato',
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
