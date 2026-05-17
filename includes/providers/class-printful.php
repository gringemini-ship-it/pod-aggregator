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
        $this->api_key = $settings['printful_api_key'] ?? '';
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
            'Content-Type' => 'application/json',
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
     * Uses Printful product catalog endpoint.
     * Caches result for cache_ttl seconds.
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

        $result = $this->get('/store/products');

        if (is_wp_error($result)) {
            $this->log_sync('fetch_products', 'error', null, null, null, $result->get_error_message());
            return [];
        }

        if (empty($result)) {
            $this->log_sync('fetch_products', 'error', null, null, null, 'Printful API returned an empty response');
            return [];
        }

        $products = $result['items'] ?? $result;

        // Normalize each product.
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
        $result = $this->get('/store/products/' . rawurlencode($product_id));

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
     * Normalize a Printful product into our standard format.
     *
     * @param array $p Raw Printful product.
     * @return array Normalized array.
     */
    private function normalize_product(array $p): array
    {
        return [
            'provider'            => 'printful',
            'provider_product_id' => (string) ($p['id'] ?? ''),
            'name'               => $p['name'] ?? '',
            'description'        => $p['description'] ?? '',
            'thumbnail_url'      => $p['image'] ?? '',
            'variants'           => array_map([$this, 'normalize_variant'], $p['variants'] ?? []),
            'model'              => $p['model'] ?? '',
            'brand'              => $p['brand'] ?? '',
            'category'           => $p['type'] ?? '',
            'files'              => $p['files'] ?? [],
            'dimensions'         => $p['dimensions'] ?? [],
        ];
    }

    /**
     * Normalize a Printful variant.
     *
     * @param array $v Raw variant.
     * @return array
     */
    private function normalize_variant(array $v): array
    {
        return [
            'variant_id'   => (string) ($v['id'] ?? ''),
            'name'         => $v['name'] ?? '',
            'sku'          => $v['sku'] ?? '',
            'price'        => (float) ($v['price'] ?? 0),
            'cost'         => (float) ($v['cost'] ?? 0),
            'currency'     => $v['currency'] ?? 'USD',
            'size'         => $v['size'] ?? '',
            'color'        => $v['color'] ?? '',
            'image'        => $v['image'] ?? '',
            'availability' => $v['availability'] ?? [],
        ];
    }

    // -------------------------------------------------------------------------
    // Pricing
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Printful returns cost in USD. If no retail_price is passed, returns cost.
     * A typical WooCommerce markup is added automatically (+30%).
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
     * Fetches product mockup images. Uses Printful's default product images.
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
     *   'woo_order_id'       => int,
     *   'items'              => [[
     *     'variant_id'          => string,
     *     'qty'                => int,
     *     'design_data'        => array (print file info),
     *   ]],
     *   'shipping_address' => [
     *     'name'    => string,
     *     'address1'=> string,
     *     'city'   => string,
     *     'state'  => string,
     *     'zip'    => string,
     *     'country'=> string,
     *   ],
     * ]
     */
    public function submit_order(array $order_data)
    {
        // Build Printful order payload.
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
            'external_id'   => 'wc_order_' . $order_data['woo_order_id'],
            'shipping'      => 'STANDARD',
            'items'         => $items,
            'recipient'     => [
                'name'       => sanitize_text_field($address['name'] ?? ''),
                'address1'   => sanitize_text_field($address['address1'] ?? ''),
                'address2'   => sanitize_text_field($address['address2'] ?? ''),
                'city'       => sanitize_text_field($address['city'] ?? ''),
                'state'      => sanitize_text_field($address['state'] ?? ''),
                'country'    => sanitize_text_field($address['country'] ?? ''),
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
            // Return placeholder — provider will use default.
            return [
                [
                    'type'      => 'default',
                    'url'       => '',
                ],
            ];
        }

        $files = [];
        foreach ((array) $design_data as $file) {
            $files[] = [
                'type'      => $file['type'] ?? 'default',
                'url'       => esc_url_raw($file['url'] ?? ''),
                'options'   => $file['options'] ?? [],
            ];
        }

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function get_order_status(string $external_order_id): array
    {
        $result = $this->get('/orders/' . rawurlencode($external_order_id));

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
     * @param string   $event_type    Event type (e.g. submit_order, fetch_products).
     * @param string   $status        pending|success|error.
     * @param string|null $external_id Provider's external order ID.
     * @param int|null $order_id      WooCommerce order ID.
     * @param array|null $payload     JSON-serializable payload.
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
                'provider'      => 'printful',
                'event_type'   => $event_type,
                'external_id'  => $external_id,
                'order_id'     => $order_id,
                'status'       => $status,
                'payload'      => $payload ? wp_json_encode($payload) : null,
                'error_message'=> $error_message,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }
}
