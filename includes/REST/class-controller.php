<?php
/**
 * POD Aggregator — REST API Webhook Controller.
 *
 * Receives webhook callbacks from POD providers (Printful, etc.)
 * and updates WooCommerce order status accordingly.
 *
 * @package POD_Aggregator\REST
 */

namespace POD_Aggregator\REST;

/**
 * Registers REST API routes for POD webhooks.
 *
 * @since 1.0.0
 */
class Controller
{
    /** REST namespace. */
    public const REST_NAMESPACE = 'pod-aggregator/v1';

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes()
    {
        // Printful webhook.
        // POST only; signature is verified in the callback.
        register_rest_route(self::REST_NAMESPACE, '/webhook/printful', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_printful_webhook'],
            'permission_callback' => [$this, 'webhook_permission'],
            'show_in_index'       => false,
        ]);

        // Generic provider webhook (for future providers).
        register_rest_route(self::REST_NAMESPACE, '/webhook/(?P<provider>[a-z]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_provider_webhook'],
            'permission_callback' => [$this, 'webhook_permission'],
            'show_in_index'       => false,
            'args'                => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /**
     * Permission check for all webhook routes.
     * Allows POST requests; signature validation is done per-provider in the handler.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function webhook_permission(\WP_REST_Request $request): bool
    {
        // Reject any non-POST method (belt-and-suspenders; WP already enforces methods).
        if ($request->get_method() !== 'POST') {
            return false;
        }
        return true;
    }

    /**
     * Handle Printful webhook POST requests.
     *
     * Printful sends events as JSON with an 'event' field.
     * Webhook URL: /wp-json/pod-aggregator/v1/webhook/printful
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_printful_webhook(\WP_REST_Request $request)
    {
        // Validate Printful webhook signature header.
        $signature = $request->get_header('x-printful-signature');
        if (!$this->verify_printful_signature($signature, $request->get_body())) {
            return new \WP_Error(
                'pod_aggregator_invalid_signature',
                __('Invalid webhook signature.', 'pod-aggregator'),
                ['status' => 403]
            );
        }

        $body = json_decode($request->get_body(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('pod_aggregator_invalid_json', __('Invalid JSON body.', 'pod-aggregator'), ['status' => 400]);
        }

        $event = sanitize_text_field($body['event'] ?? '');
        $data  = $body['data'] ?? [];

        switch ($event) {
            case 'order_created':
                return $this->handle_order_created($data, 'printful');

            case 'order_updated':
                return $this->handle_order_updated($data, 'printful');

            case 'shipment_created':
                return $this->handle_shipment_created($data, 'printful');

            default:
                // Acknowledge but ignore unknown events.
                return new \WP_REST_Response(['received' => true, 'event' => $event], 200);
        }
    }

    /**
     * Generic provider webhook handler.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_provider_webhook(\WP_REST_Request $request)
    {
        $provider = sanitize_key($request->get_param('provider'));

        // Dispatch to provider-specific handler.
        switch ($provider) {
            case 'printful':
                return $this->handle_printful_webhook($request);
            default:
                return new \WP_Error(
                    'pod_aggregator_unknown_provider',
                    sprintf(__('Unknown POD provider: %s', 'pod-aggregator'), $provider),
                    ['status' => 400]
                );
        }
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    /**
     * Handle order created event.
     *
     * @param array  $data     Event data.
     * @param string $provider Provider slug.
     * @return \WP_REST_Response
     */
    private function handle_order_created(array $data, string $provider): \WP_REST_Response
    {
        $external_id = sanitize_text_field($data['id'] ?? '');

        if (empty($external_id)) {
            return new \WP_REST_Response(['error' => 'Missing order ID'], 400);
        }

        // Find WooCommerce order by our stored external ID.
        $order_id = $this->find_wc_order_by_external_id($external_id, $provider);

        if (!$order_id) {
            // Try with wc_order_ prefix (our format).
            $order_id = $this->find_wc_order_by_external_id('wc_order_' . $external_id, $provider);
        }

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('processing');
                $order->add_order_note(
                    sprintf(__('POD Aggregator: Order confirmed by %s (External ID: %s)', 'pod-aggregator'), ucfirst($provider), $external_id)
                );
            }
        }

        return new \WP_REST_Response(['received' => true, 'order_id' => $order_id ?? null], 200);
    }

    /**
     * Handle order updated event.
     *
     * @param array  $data     Event data.
     * @param string $provider Provider slug.
     * @return \WP_REST_Response
     */
    private function handle_order_updated(array $data, string $provider): \WP_REST_Response
    {
        $external_id = sanitize_text_field($data['id'] ?? '');

        if (empty($external_id)) {
            return new \WP_REST_Response(['error' => 'Missing order ID'], 400);
        }

        $order_id = $this->find_wc_order_by_external_id($external_id, $provider);

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(
                    sprintf(__('POD Aggregator: Order updated by %s', 'pod-aggregator'), ucfirst($provider))
                );
            }
        }

        return new \WP_REST_Response(['received' => true], 200);
    }

    /**
     * Handle shipment created event (tracking update).
     *
     * @param array  $data     Event data.
     * @param string $provider Provider slug.
     * @return \WP_REST_Response
     */
    private function handle_shipment_created(array $data, string $provider): \WP_REST_Response
    {
        $external_id    = sanitize_text_field($data['order_id'] ?? '');
        $tracking       = sanitize_text_field($data['tracking_number'] ?? '');
        $carrier        = sanitize_text_field($data['carrier'] ?? '');
        $tracking_url   = esc_url_raw($data['tracking_url'] ?? '');

        if (empty($external_id)) {
            return new \WP_REST_Response(['error' => 'Missing order ID'], 400);
        }

        $order_id = $this->find_wc_order_by_external_id($external_id, $provider);

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !empty($tracking)) {
                $order->update_status('completed');
                $order->update_meta_data('_pod_tracking_number', $tracking);
                $order->update_meta_data('_pod_tracking_carrier', $carrier);
                $order->update_meta_data('_pod_tracking_url', $tracking_url);
                $order->save();
                $order->add_order_note(
                    sprintf(
                        /* translators: %1$s = provider, %2$s = carrier, %3$s = tracking number */
                        __('POD Aggregator: Shipment created via %1$s. Carrier: %2$s. Tracking: %3$s', 'pod-aggregator'),
                        ucfirst($provider),
                        $carrier,
                        $tracking
                    )
                );
            }
        }

        return new \WP_REST_Response(['received' => true], 200);
    }

    // -------------------------------------------------------------------------
    // Signature verification
    // -------------------------------------------------------------------------

    /**
     * Verify Printful webhook HMAC signature.
     *
     * Printful signs payloads with HMAC-SHA256 using your API secret.
     *
     * @param string|null $signature x-printful-signature header value.
     * @param string      $payload   Raw request body.
     * @return bool
     */
    private function verify_printful_signature(?string $signature, string $payload): bool
    {
        if (empty($signature)) {
            return false;
        }

        $settings = get_site_option('pod_aggregator_settings', []);
        $api_key  = $settings['printful_api_key'] ?? '';

        if (empty($api_key)) {
            return false;
        }

        // Printful webhook signature format: "t=timestamp,v1=signature".
        // The signature is HMAC-SHA256(timestamp.".".$payload, api_key).
        // We do a simple comparison — a real implementation should use timing-safe comparison.
        $parts = [];
        parse_str(str_replace(',', '&', $signature), $parts);

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $timestamp = $parts['t'];
        $expected  = $parts['v1'];
        $secret    = $api_key;

        $signed_payload = $timestamp . '.' . $payload;
        $computed      = hash_hmac('sha256', $signed_payload, $secret);

        // Use hash_equals for timing-safe comparison if available.
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $computed);
        }

        return $expected === $computed;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a WooCommerce order by POD provider external ID.
     *
     * @param string $external_id External order ID from provider.
     * @param string $provider    Provider slug.
     * @return int|null Order ID or null.
     */
    private function find_wc_order_by_external_id(string $external_id, string $provider): ?int
    {
        global $wpdb;

        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pod_external_order_id' AND meta_value = %s LIMIT 1",
                $external_id
            )
        );

        return $order_id ? (int) $order_id : null;
    }
}
