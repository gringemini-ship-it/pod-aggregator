<?php
/**
 * POD Aggregator — Product Customizer REST API.
 *
 * Registers REST routes:
 *   POST /pod-aggregator/v1/design           — Create a design
 *   GET  /pod-aggregator/v1/design/{uuid}    — Retrieve a design
 *   PUT  /pod-aggregator/v1/design/{uuid}    — Update a design
 *   DELETE /pod-aggregator/v1/design/{uuid} — Delete a design
 *   POST /pod-aggregator/v1/design/{uuid}/print-file — Generate print file
 *   POST /pod-aggregator/v1/design/{uuid}/preview    — Generate preview
 *
 * @package POD_Aggregator\ProductCustomizer
 */

namespace POD_Aggregator\ProductCustomizer;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API for POD designs and print file generation.
 *
 * @since 1.0.0
 */
class REST_Controller
{
    /** REST namespace. */
    public const REST_NAMESPACE = 'pod-aggregator/v1';

    /** @var Design_Storage */
    private $storage;

    /** @var Print_Generator */
    private $print_gen;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->storage   = new Design_Storage();
        $this->print_gen = new Print_Generator();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        // ---- Designs collection ----
        register_rest_route(self::REST_NAMESPACE, '/designs', [
            'methods'             => 'POST',
            'callback'           => [$this, 'create_design'],
            'permission_callback' => [$this, 'design_permission'],
            'schema'             => [$this, 'design_schema'],
        ]);

        // ---- Single design ----
        register_rest_route(self::REST_NAMESPACE, '/designs/(?P<uuid>[a-z0-9\-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_design'],
            'permission_callback' => [$this, 'design_permission'],
            'args'                => [
                'uuid' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/designs/(?P<uuid>[a-z0-9\-]+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_design'],
            'permission_callback' => [$this, 'design_permission'],
            'args'                => [
                'uuid' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/designs/(?P<uuid>[a-z0-9\-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_design'],
            'permission_callback' => [$this, 'design_permission'],
            'args'                => [
                'uuid' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // ---- Print file generation ----
        register_rest_route(self::REST_NAMESPACE, '/designs/(?P<uuid>[a-z0-9\-]+)/print-file', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate_print_file'],
            'permission_callback' => [$this, 'design_permission'],
            'args'                => [
                'uuid' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // ---- Preview generation ----
        register_rest_route(self::REST_NAMESPACE, '/designs/(?P<uuid>[a-z0-9\-]+)/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate_preview'],
            'permission_callback' => [$this, 'design_permission'],
            'args'                => [
                'uuid' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Permission
    // -------------------------------------------------------------------------

    /**
     * Check if the current user can manage designs.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function design_permission(WP_REST_Request $request): bool
    {
        // Ensure WordPress is loaded and user is authenticated.
        if (!function_exists('wp_get_current_user')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }

        $user = wp_get_current_user();

        // Logged-in users with shop_manager or admin capabilities.
        if ($user->exists()) {
            if (current_user_can('manage_woocommerce') || current_user_can('edit_posts')) {
                return true;
            }
        }

        // For unauthenticated requests (e.g. cart restoration), allow
        // read-only access to published designs (GET only).
        if ($request->get_method() === 'GET') {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Design CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new design.
     *
     * POST /pod-aggregator/v1/designs
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_design(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();

        // Apply input sanitization.
        $data = $this->sanitize_design_data($body);
        if (is_wp_error($data)) {
            return $data;
        }

        $design = new Design($data);
        $post_id = $this->storage->save($design);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return new WP_REST_Response([
            'uuid'      => $design->get_id(),
            'post_id'   => $post_id,
            'design'    => $design->jsonSerialize(),
        ], 201);
    }

    /**
     * Get a design by UUID.
     *
     * GET /pod-aggregator/v1/designs/{uuid}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_design(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = $request->get_param('uuid');
        $design = $this->storage->get($uuid);

        if (!$design) {
            return new WP_Error(
                'pod_design_not_found',
                __('Design not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'design' => $design->jsonSerialize(),
        ]);
    }

    /**
     * Update an existing design.
     *
     * PUT /pod-aggregator/v1/designs/{uuid}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_design(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = $request->get_param('uuid');
        $existing = $this->storage->get($uuid);

        if (!$existing) {
            return new WP_Error(
                'pod_design_not_found',
                __('Design not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        $body = $request->get_json_params();
        $data = $this->sanitize_design_data($body);
        if (is_wp_error($data)) {
            return $data;
        }

        // Preserve immutable fields.
        $data['id']     = $uuid;
        $data['created_at'] = $existing->get_created_at();
        $data['updated_at'] = time();

        $design = new Design($data);
        $post_id = $this->storage->save($design);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return new WP_REST_Response([
            'uuid'   => $design->get_id(),
            'design' => $design->jsonSerialize(),
        ]);
    }

    /**
     * Delete a design.
     *
     * DELETE /pod-aggregator/v1/designs/{uuid}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_design(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = $request->get_param('uuid');
        $deleted = $this->storage->delete($uuid);

        if (!$deleted) {
            return new WP_Error(
                'pod_design_not_found',
                __('Design not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response(['deleted' => true, 'uuid' => $uuid]);
    }

    // -------------------------------------------------------------------------
    // Print file generation
    // -------------------------------------------------------------------------

    /**
     * Generate a high-DPI print file from a design.
     *
     * POST /pod-aggregator/v1/designs/{uuid}/print-file
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function generate_print_file(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = $request->get_param('uuid');
        $design = $this->storage->get($uuid);

        if (!$design) {
            return new WP_Error(
                'pod_design_not_found',
                __('Design not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        // Optional: override DPI via request.
        $body = $request->get_json_params();
        if (!empty($body['dpi'])) {
            $design = new Design(array_merge(
                $design->jsonSerialize(),
                ['dpi' => absint($body['dpi'])]
            ));
        }

        $result = $this->print_gen->generate($design);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'print_file' => [
                'file_url'  => $result['file_url'],
                'file_path' => $result['file_path'],
                'width_px'  => $result['width_px'],
                'height_px' => $result['height_px'],
                'dpi'       => $result['dpi'],
            ],
        ]);
    }

    /**
     * Generate a preview image for a design.
     *
     * POST /pod-aggregator/v1/designs/{uuid}/preview
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function generate_preview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = $request->get_param('uuid');
        $design = $this->storage->get($uuid);

        if (!$design) {
            return new WP_Error(
                'pod_design_not_found',
                __('Design not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        $body = $request->get_json_params();
        $max_width = !empty($body['max_width']) ? absint($body['max_width']) : 600;

        $result = $this->print_gen->generate_preview($design, $max_width);

        if (is_wp_error($result)) {
            return $result;
        }

        // Save thumbnail URL to storage.
        $this->storage->save_thumbnail($uuid, $result['file_url']);

        return new WP_REST_Response([
            'preview' => [
                'file_url'  => $result['file_url'],
                'width_px'  => $result['width_px'],
                'height_px' => $result['height_px'],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Sanitization
    // -------------------------------------------------------------------------

    /**
     * Sanitize design data from a REST request body.
     *
     * @param array $body
     * @return array|WP_Error
     */
    private function sanitize_design_data(array $body): array
    {
        if (empty($body)) {
            return new WP_Error(
                'pod_design_empty_body',
                __('Request body is empty.', 'pod-aggregator'),
                ['status' => 400]
            );
        }

        $elements = [];
        foreach ((array) ($body['elements'] ?? []) as $i => $el) {
            $el = $this->sanitize_element($el, $i);
            if (is_wp_error($el)) {
                return $el;
            }
            $elements[] = $el;
        }

        return [
            'name'                 => sanitize_text_field($body['name'] ?? ''),
            'area'                 => sanitize_key($body['area'] ?? 'front'),
            'product_id'           => absint($body['product_id'] ?? 0),
            'provider'             => sanitize_key($body['provider'] ?? 'printful'),
            'provider_product_id' => sanitize_text_field($body['provider_product_id'] ?? ''),
            'dpi'                  => absint($body['dpi'] ?? 300),
            'elements'             => $elements,
        ];
    }

    /**
     * Sanitize a single design element.
     *
     * @param array $el
     * @param int   $index
     * @return array|WP_Error
     */
    private function sanitize_element(array $el, int $index): array
    {
        $type = sanitize_key($el['type'] ?? 'text');
        $base = [
            'type'      => $type,
            'x'         => absint($el['x'] ?? 0),
            'y'         => absint($el['y'] ?? 0),
            'width'     => max(1, absint($el['width'] ?? 200)),
            'height'    => max(1, absint($el['height'] ?? 100)),
            'rotation'  => absint($el['rotation'] ?? 0),
            'z_index'   => absint($el['z_index'] ?? $index),
            'locked'    => !empty($el['locked']),
        ];

        switch ($type) {
            case DesignElement::TYPE_TEXT:
                return array_merge($base, [
                    'text'      => sanitize_text_field($el['text'] ?? ''),
                    'font'      => sanitize_text_field($el['font'] ?? 'Arial'),
                    'fontSize'  => max(6, absint($el['fontSize'] ?? 24)),
                    'color'     => sanitize_hex_color($el['color'] ?? '#000000') ?? '#000000',
                    'align'     => in_array($el['align'] ?? '', ['left', 'center', 'right'], true)
                        ? $el['align'] : 'center',
                    'bold'      => !empty($el['bold']),
                    'italic'    => !empty($el['italic']),
                    'underline' => !empty($el['underline']),
                ]);

            case DesignElement::TYPE_IMAGE:
                return array_merge($base, [
                    'src'         => esc_url_raw($el['src'] ?? ''),
                    'originalSrc' => esc_url_raw($el['originalSrc'] ?? ''),
                    'cropX'       => absint($el['cropX'] ?? 0),
                    'cropY'       => absint($el['cropY'] ?? 0),
                    'scale'       => (float) ($el['scale'] ?? 1.0),
                ]);

            case DesignElement::TYPE_SHAPE:
                return array_merge($base, [
                    'shape'       => in_array($el['shape'] ?? '', [
                        DesignElement::SHAPE_CIRCLE,
                        DesignElement::SHAPE_RECT,
                        DesignElement::SHAPE_LINE,
                        DesignElement::SHAPE_STAR,
                    ], true) ? $el['shape'] : DesignElement::SHAPE_RECT,
                    'fill'        => $el['fill'] === 'transparent'
                        ? 'transparent'
                        : (sanitize_hex_color($el['fill'] ?? '') ?? 'transparent'),
                    'stroke'      => sanitize_hex_color($el['stroke'] ?? '#000000') ?? '#000000',
                    'strokeWidth' => absint($el['strokeWidth'] ?? 0),
                ]);

            default:
                return new WP_Error(
                    'pod_design_invalid_element',
                    sprintf(__('Unknown element type: %s', 'pod-aggregator'), $type),
                    ['status' => 400]
                );
        }
    }

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    /**
     * Return the schema for the designs endpoint.
     *
     * @return array
     */
    public function design_schema(): array
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'pod_design',
            'type'       => 'object',
            'properties' => [
                'uuid'        => ['type' => 'string'],
                'name'        => ['type' => 'string'],
                'area'        => ['type' => 'string'],
                'product_id'  => ['type' => 'integer'],
                'provider'    => ['type' => 'string'],
                'dpi'         => ['type' => 'integer'],
                'elements'    => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'type'      => ['type' => 'string'],
                            'x'         => ['type' => 'integer'],
                            'y'         => ['type' => 'integer'],
                            'width'     => ['type' => 'integer'],
                            'height'    => ['type' => 'integer'],
                            'rotation'  => ['type' => 'integer'],
                            'z_index'   => ['type' => 'integer'],
                            'locked'    => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
