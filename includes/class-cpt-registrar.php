<?php
/**
 * CPT Registrar for POD Aggregator custom post types.
 *
 * @package POD_Aggregator
 */

namespace POD_Aggregator;

/**
 * Registers POD-specific custom post types and rewrite rules.
 *
 * @since 1.0.0
 */
class CPT_Registrar
{
    /**
     * Register POD product CPT.
     * Hooked to 'init' so it runs before flush_rewrite_rules on activation.
     *
     * @return void
     */
    public function register_post_types()
    {
        register_post_type(
            'pod_product',
            [
                'labels'       => [
                    'name'               => __('POD Products', 'pod-aggregator'),
                    'singular_name'      => __('POD Product', 'pod-aggregator'),
                    'add_new'            => __('Add New', 'pod-aggregator'),
                    'add_new_item'       => __('Add New POD Product', 'pod-aggregator'),
                    'edit_item'          => __('Edit POD Product', 'pod-aggregator'),
                    'new_item'           => __('New POD Product', 'pod-aggregator'),
                    'view_item'          => __('View POD Product', 'pod-aggregator'),
                    'search_items'       => __('Search POD Products', 'pod-aggregator'),
                    'not_found'          => __('No POD products found.', 'pod-aggregator'),
                    'not_found_in_trash' => __('No POD products found in trash.', 'pod-aggregator'),
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
                'show_admin_column'  => true,
                'menu_icon'          => 'dashicons-images-alt',
                'supports'           => ['title', 'custom-fields'],
                'rewrite'            => false,
            ]
        );
    }
}

/**
 * Return the active provider adapter by slug.
 *
 * @param string $slug Provider slug (e.g. 'printful').
 * @return Provider_Interface|null
 */
function pod_aggregator_get_provider(string $slug): ?Provider_Interface
{
    static $adapters = [];

    if (isset($adapters[$slug])) {
        return $adapters[$slug];
    }

    switch ($slug) {
        case 'printful':
            $adapters[$slug] = new \POD_Aggregator\Provider\Printful_Adapter();
            break;
        case 'printify':
            $adapters[$slug] = new \POD_Aggregator\Provider\Printify_Adapter();
            break;
        case 'gelato':
            $adapters[$slug] = new \POD_Aggregator\Provider\Gelato_Adapter();
            break;
        default:
            return null;
    }

    return $adapters[$slug];
}
