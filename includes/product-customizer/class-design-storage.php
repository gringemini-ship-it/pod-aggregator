<?php
/**
 * POD Aggregator — Design Storage.
 *
 * Persists POD_Design objects as a custom post type (pod_design)
 * and maps them to the `pod_aggregator_designs` table.
 *
 * @package POD_Aggregator\ProductCustomizer
 */

namespace POD_Aggregator\ProductCustomizer;

/**
 * Stores and retrieves Design objects.
 *
 * Designs are saved as JSON in the pod_design CPT post_content,
 * with metadata stored as post meta for queryability.
 *
 * @since 1.0.0
 */
class Design_Storage
{
    /** CPT slug. */
    public const CPT = 'pod_design';

    /** Post type meta keys. */
    public const META_DESIGN_JSON   = '_pod_design_json';
    public const META_PRODUCT_ID    = '_pod_product_id';
    public const META_PROVIDER      = '_pod_provider';
    public const META_DESIGN_UUID   = '_pod_design_uuid';
    public const META_PRINT_AREA     = '_pod_print_area';
    public const META_THUMBNAIL_URL  = '_pod_thumbnail_url';

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    /**
     * Save (insert or update) a Design.
     *
     * @param Design $design
     * @return int|WP_Error Post ID on success; WP_Error on failure.
     */
    public function save(Design $design)
    {
        $validate = $design->validate();
        if (is_wp_error($validate)) {
            return $validate;
        }

        // Check for existing post by design UUID.
        $existing = $this->find_by_uuid($design->get_id());

        $post_data = [
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => sanitize_text_field($design->get_name()),
            'post_content' => wp_json_encode($design->jsonSerialize()),
        ];

        if ($existing) {
            $post_data['ID'] = $existing;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store metadata.
        update_post_meta($post_id, self::META_DESIGN_JSON, wp_json_encode($design->jsonSerialize()));
        update_post_meta($post_id, self::META_PRODUCT_ID, $design->get_product_id());
        update_post_meta($post_id, self::META_PROVIDER, $design->get_provider());
        update_post_meta($post_id, self::META_DESIGN_UUID, $design->get_id());
        update_post_meta($post_id, self::META_PRINT_AREA, $design->get_area());
        update_post_meta($post_id, self::META_THUMBNAIL_URL, '');

        return (int) $post_id;
    }

    /**
     * Delete a design by UUID.
     *
     * @param string $uuid
     * @return bool True if deleted; false if not found.
     */
    public function delete(string $uuid): bool
    {
        $post_id = $this->find_by_uuid($uuid);
        if (!$post_id) {
            return false;
        }
        return (bool) wp_delete_post($post_id, true);
    }

    /**
     * Get a Design by UUID.
     *
     * @param string $uuid
     * @return Design|null
     */
    public function get(string $uuid): ?Design
    {
        $post_id = $this->find_by_uuid($uuid);
        if (!$post_id) {
            return null;
        }
        return $this->load_from_post($post_id);
    }

    /**
     * Get all designs for a WooCommerce product.
     *
     * @param int    $product_id
     * @param string $provider Optional provider filter.
     * @return Design[]
     */
    public function get_for_product(int $product_id, string $provider = ''): array
    {
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => self::META_PRODUCT_ID, 'value' => $product_id],
            ],
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        if ($provider) {
            $args['meta_query'][] = ['key' => self::META_PROVIDER, 'value' => $provider];
        }

        $posts = get_posts($args);
        return array_map([$this, 'load_from_post'], $posts);
    }

    /**
     * Get all preset (template) designs.
     *
     * @return Design[]
     */
    public function get_presets(): array
    {
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => self::META_DESIGN_UUID, // Just ensure meta exists.
        ];

        $posts = get_posts($args);
        return array_filter(
            array_map([$this, 'load_from_post'], $posts),
            fn(?Design $d) => $d !== null
        );
    }

    /**
     * Save a design thumbnail URL.
     *
     * @param string $uuid
     * @param string $url
     * @return void
     */
    public function save_thumbnail(string $uuid, string $url): void
    {
        $post_id = $this->find_by_uuid($uuid);
        if ($post_id) {
            update_post_meta($post_id, self::META_THUMBNAIL_URL, esc_url_raw($url));
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Find a post ID by design UUID.
     *
     * @param string $uuid
     * @return int|null
     */
    private function find_by_uuid(string $uuid): ?int
    {
        global $wpdb;

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::META_DESIGN_UUID,
                $uuid
            )
        );

        return $post_id ? (int) $post_id : null;
    }

    /**
     * Load a Design from a WordPress post.
     *
     * @param int|WP_Post $post
     * @return Design|null
     */
    public function load_from_post($post): ?Design
    {
        $post = get_post($post);
        if (!$post || $post->post_type !== self::CPT) {
            return null;
        }

        $json = $post->post_content;
        if (empty($json)) {
            $json = get_post_meta($post->ID, self::META_DESIGN_JSON, true);
        }

        if (empty($json)) {
            return null;
        }

        try {
            return Design::from_json($json);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Register the pod_design CPT (called on 'init').
     *
     * @return void
     */
    public function register_post_type(): void
    {
        register_post_type(
            self::CPT,
            [
                'labels'       => [
                    'name'               => __('POD Designs', 'pod-aggregator'),
                    'singular_name'      => __('POD Design', 'pod-aggregator'),
                    'add_new'           => __('Add New', 'pod-aggregator'),
                    'add_new_item'       => __('Add New POD Design', 'pod-aggregator'),
                    'edit_item'          => __('Edit POD Design', 'pod-aggregator'),
                    'new_item'           => __('New POD Design', 'pod-aggregator'),
                    'view_item'          => __('View POD Design', 'pod-aggregator'),
                    'search_items'       => __('Search POD Designs', 'pod-aggregator'),
                    'not_found'          => __('No designs found.', 'pod-aggregator'),
                    'not_found_in_trash' => __('No designs found in trash.', 'pod-aggregator'),
                ],
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => false,
                'capability_type'    => 'post',
                'capabilities'       => [
                    'edit_post'   => 'manage_woocommerce',
                    'delete_post' => 'manage_woocommerce',
                    'read_post'   => 'manage_woocommerce',
                ],
                'map_meta_cap'       => true,
                'hierarchical'        => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'show_in_nav_menus'   => false,
                'has_archive'        => false,
                'show_admin_column'  => false,
                'supports'           => ['title', 'custom-fields'],
                'rewrite'            => false,
            ]
        );
    }
}
