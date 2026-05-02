<?php
/**
 * POD Aggregator — Design Preset Templates Admin UI.
 *
 * Provides an admin interface for creating, editing, and managing
 * starter design templates that users can pick from in the customizer.
 *
 * Templates are stored as pod_design CPT posts with the
 * _pod_is_preset meta flag set to '1'.
 *
 * @package POD_Aggregator\Admin
 */

namespace POD_Aggregator\Admin;

use POD_Aggregator\ProductCustomizer\Design_Storage;
use POD_Aggregator\ProductCustomizer\Design;

/**
 * Admin UI for managing preset design templates.
 *
 * @since 1.0.0
 */
class Preset_Templates
{
    /** Menu slug. */
    public const MENU_SLUG = 'pod-aggregator-presets';

    /** Meta key flagging a design as a preset. */
    public const META_IS_PRESET = '_pod_is_preset';

    /** Meta key for preset category. */
    public const META_CATEGORY = '_pod_preset_category';

    /** Meta key for preset thumbnail preview URL. */
    public const META_THUMBNAIL = '_pod_preset_thumbnail';

    /** Default preset categories. */
    public const DEFAULT_CATEGORIES = [
        'text'      => 'Text Designs',
        'logos'     => 'Logos & Badges',
        'minimal'   => 'Minimalist',
        'bold'      => 'Bold & Graphic',
        'vintage'   => 'Vintage & Retro',
        'abstract'  => 'Abstract Art',
    ];

    /** @var Design_Storage */
    private $storage;

    /** @var string[] */
    private $categories;

    public function __construct()
    {
        $this->storage    = new Design_Storage();
        $this->categories = apply_filters('pod_aggregator_preset_categories', self::DEFAULT_CATEGORIES);
    }

    /**
     * Register the admin menu page.
     *
     * @return void
     */
    public function register_menu(): void
    {
        add_submenu_page(
            'pod-aggregator',
            __('Design Templates', 'pod-aggregator'),
            __('Design Templates', 'pod-aggregator'),
            'manage_network',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin assets for the templates page.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void
    {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_media();

        $asset_base = POD_AGGREGATOR_PLUGIN_URL;

        wp_enqueue_style(
            'pod-preset-templates',
            $asset_base . 'admin/css/preset-templates.css',
            [],
            POD_AGGREGATOR_VERSION
        );

        wp_enqueue_script(
            'pod-preset-templates',
            $asset_base . 'admin/js/preset-templates.js',
            ['jquery', 'wp-color-picker'],
            POD_AGGREGATOR_VERSION,
            true
        );

        wp_localize_script('pod-preset-templates', 'PODPresets', [
            'nonce'       => wp_create_nonce('pod_preset_templates'),
            'restBase'    => rest_url('pod-aggregator/v1/presets'),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'categories'  => $this->categories,
            'i18n'        => [
                'title'           => __('Design Templates', 'pod-aggregator'),
                'addNew'          => __('Add New Template', 'pod-aggregator'),
                'editTemplate'    => __('Edit Template', 'pod-aggregator'),
                'deleteConfirm'  => __('Delete this template? This cannot be undone.', 'pod-aggregator'),
                'saved'           => __('Template saved.', 'pod-aggregator'),
                'deleted'         => __('Template deleted.', 'pod-aggregator'),
                'name'            => __('Template Name', 'pod-aggregator'),
                'category'        => __('Category', 'pod-aggregator'),
                'thumbnail'       => __('Preview Image', 'pod-aggregator'),
                'designJson'      => __('Design JSON', 'pod-aggregator'),
                'save'            => __('Save Template', 'pod-aggregator'),
                'cancel'          => __('Cancel', 'pod-aggregator'),
                'delete'          => __('Delete', 'pod-aggregator'),
                'uploadImage'     => __('Upload Image', 'pod-aggregator'),
                'noPresets'       => __('No templates yet. Create your first starter design!', 'pod-aggregator'),
                'uncategorized'   => __('Uncategorized', 'pod-aggregator'),
            ],
        ]);
    }

    /**
     * Handle AJAX: save a preset template.
     *
     * @return void
     */
    public function ajax_save_preset(): void
    {
        check_ajax_referer('pod_preset_templates', 'nonce');

        if (!current_user_can('manage_network')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'pod-aggregator')], 403);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $name        = sanitize_text_field($body['name'] ?? '');
        $category    = sanitize_key($body['category'] ?? '');
        $thumbnail   = esc_url_raw($body['thumbnail'] ?? '');
        $design_json = $body['design_json'] ?? '{}';

        if (empty($name)) {
            wp_send_json_error(['message' => __('Template name is required.', 'pod-aggregator')], 400);
            return;
        }

        // Validate design JSON.
        $design_data = json_decode($design_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid design JSON.', 'pod-aggregator')], 400);
            return;
        }

        // Ensure it has elements.
        if (empty($design_data['elements'])) {
            wp_send_json_error(['message' => __('Template must have at least one element.', 'pod-aggregator')], 400);
            return;
        }

        // Wrap in a Design object to normalise.
        $design = new Design($design_data);

        // Force preset metadata.
        $design_json_encoded = wp_json_encode($design->jsonSerialize());

        $existing_uuid = sanitize_key($body['uuid'] ?? '');

        if ($existing_uuid) {
            // Update existing.
            $existing = $this->storage->get($existing_uuid);
            if (!$existing) {
                wp_send_json_error(['message' => __('Template not found.', 'pod-aggregator')], 404);
                return;
            }

            $design_data_merged = $design->jsonSerialize();
            $design_data_merged['id']        = $existing_uuid;
            $design_data_merged['created_at'] = $existing->get_created_at();
            $design_data_merged['updated_at'] = time();

            $updated_design = new Design($design_data_merged);
            $post_id = $this->storage->save($updated_design);
        } else {
            // Create new preset.
            $design_data_new = $design->jsonSerialize();
            $design_data_new['name'] = $name;
            $design_data_new['provider'] = 'preset';

            $new_design = new Design($design_data_new);
            $post_id = $this->storage->save($new_design);
        }

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()], 500);
            return;
        }

        // Mark as preset.
        update_post_meta($post_id, self::META_IS_PRESET, '1');
        update_post_meta($post_id, self::META_CATEGORY, $category);
        update_post_meta($post_id, self::META_THUMBNAIL, $thumbnail);

        $uuid = $this->storage->get($existing_uuid ?: ($new_design ?? $design)->get_id())->get_id();

        wp_send_json_success([
            'uuid'      => $uuid,
            'post_id'   => $post_id,
            'message'   => __('Template saved.', 'pod-aggregator'),
        ]);
    }

    /**
     * Handle AJAX: delete a preset template.
     *
     * @return void
     */
    public function ajax_delete_preset(): void
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

        $deleted = $this->storage->delete($uuid);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Template not found.', 'pod-aggregator')], 404);
            return;
        }

        wp_send_json_success(['message' => __('Template deleted.', 'pod-aggregator')]);
    }

    /**
     * Register the presets REST namespace.
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        register_rest_route('pod-aggregator/v1', '/presets', [
            'methods'             => 'GET',
            'callback'           => [$this, 'rest_get_presets'],
            'permission_callback' => '__return_true',
            'args'               => [
                'category' => [
                    'sanitize_callback' => 'sanitize_key',
                    'required'          => false,
                ],
            ],
        ]);

        register_rest_route('pod-aggregator/v1', '/presets/(?P<uuid>[a-z0-9\-]+)', [
            'methods'             => 'GET',
            'callback'           => [$this, 'rest_get_preset'],
            'permission_callback' => '__return_true',
            'args'               => [
                'uuid' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /**
     * REST: get all presets.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function rest_get_presets(\WP_REST_Request $request): \WP_REST_Response
    {
        $category = $request->get_param('category');

        $presets = $this->storage->get_presets();

        // Filter by category if requested.
        if ($category) {
            $presets = array_filter($presets, function ($preset) use ($category) {
                $post_id = $this->storage->find_by_uuid($preset->get_id());
                return $post_id && get_post_meta($post_id, self::META_CATEGORY, true) === $category;
            });
        }

        $data = array_map(function ($preset) {
            $post_id = $this->storage->find_by_uuid($preset->get_id());
            return [
                'uuid'      => $preset->get_id(),
                'name'      => $preset->get_name(),
                'area'      => $preset->get_area(),
                'category'  => $post_id ? get_post_meta($post_id, self::META_CATEGORY, true) : '',
                'thumbnail' => $post_id ? get_post_meta($post_id, self::META_THUMBNAIL, true) : '',
                'elements'  => $preset->jsonSerialize(),
            ];
        }, $presets);

        return new \WP_REST_Response(['presets' => array_values($data)]);
    }

    /**
     * REST: get a single preset by UUID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function rest_get_preset(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $uuid   = $request->get_param('uuid');
        $preset = $this->storage->get($uuid);

        if (!$preset) {
            return new \WP_Error(
                'pod_preset_not_found',
                __('Preset not found.', 'pod-aggregator'),
                ['status' => 404]
            );
        }

        $post_id = $this->storage->find_by_uuid($uuid);

        return new \WP_REST_Response([
            'preset' => [
                'uuid'      => $preset->get_id(),
                'name'      => $preset->get_name(),
                'area'      => $preset->get_area(),
                'category'  => $post_id ? get_post_meta($post_id, self::META_CATEGORY, true) : '',
                'thumbnail' => $post_id ? get_post_meta($post_id, self::META_THUMBNAIL, true) : '',
                'design'    => $preset->jsonSerialize(),
            ],
        ]);
    }

    /**
     * Find a post ID by preset UUID.
     *
     * @param string $uuid
     * @return int|null
     */
    public function find_by_uuid(string $uuid): ?int
    {
        global $wpdb;

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                '_pod_design_uuid',
                $uuid
            )
        );

        return $post_id ? (int) $post_id : null;
    }

    /**
     * Render the admin templates page.
     *
     * @return void
     */
    public function render_page(): void
    {
        $presets = $this->storage->get_presets();

        // Group by category.
        $by_category = [];
        foreach ($presets as $preset) {
            $post_id  = $this->find_by_uuid($preset->get_id());
            $category = $post_id ? get_post_meta($post_id, self::META_CATEGORY, true) : '';
            if (!isset($by_category[$category])) {
                $by_category[$category] = [];
            }
            $by_category[$category][] = [
                'uuid'      => $preset->get_id(),
                'name'      => $preset->get_name(),
                'area'      => $preset->get_area(),
                'thumbnail' => $post_id ? get_post_meta($post_id, self::META_THUMBNAIL, true) : '',
                'design'    => $preset->jsonSerialize(),
            ];
        }

        $categories = $this->categories;
        ?>

        <div class="wrap pod-preset-templates-wrap">
            <h1>
                <?php esc_html_e('Design Templates', 'pod-aggregator'); ?>
                <a href="#" class="page-title-action" id="pod-add-preset-btn">
                    <?php esc_html_e('Add New Template', 'pod-aggregator'); ?>
                </a>
            </h1>

            <p>
                <?php esc_html_e('Starter designs that users can pick from the customizer gallery. Templates are assigned to print areas and categories.', 'pod-aggregator'); ?>
            </p>

            <?php if (empty($presets)): ?>
                <div class="pod-presets-empty">
                    <p><?php esc_html_e('No templates yet. Create your first starter design!', 'pod-aggregator'); ?></p>
                </div>
            <?php else: ?>

                <?php foreach ($categories as $cat_key => $cat_label): ?>
                    <?php if (empty($by_category[$cat_key])) continue; ?>
                    <h2 class="pod-preset-category-title"><?php echo esc_html($cat_label); ?></h2>
                    <div class="pod-preset-grid" data-category="<?php echo esc_attr($cat_key); ?>">
                        <?php foreach ($by_category[$cat_key] as $preset): ?>
                            <?php $this->render_preset_card($preset); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($by_category[''])): ?>
                    <h2 class="pod-preset-category-title"><?php esc_html_e('Uncategorized', 'pod-aggregator'); ?></h2>
                    <div class="pod-preset-grid" data-category="">
                        <?php foreach ($by_category[''] as $preset): ?>
                            <?php $this->render_preset_card($preset); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <?php /* Hidden edit modal */ ?>
        <div id="pod-preset-modal" class="pod-preset-modal" style="display:none;">
            <div class="pod-preset-modal__backdrop"></div>
            <div class="pod-preset-modal__dialog">
                <div class="pod-preset-modal__header">
                    <h2 id="pod-preset-modal-title"><?php esc_html_e('Add New Template', 'pod-aggregator'); ?></h2>
                    <button type="button" class="pod-preset-modal__close" id="pod-preset-modal-close">&times;</button>
                </div>
                <div class="pod-preset-modal__body">
                    <input type="hidden" id="pod-preset-uuid" value="">

                    <p>
                        <label for="pod-preset-name"><?php esc_html_e('Template Name', 'pod-aggregator'); ?></label><br>
                        <input type="text" id="pod-preset-name" class="widefat" placeholder="<?php esc_attr_e('e.g. Bold Text Banner', 'pod-aggregator'); ?>">
                    </p>

                    <p>
                        <label for="pod-preset-category"><?php esc_html_e('Category', 'pod-aggregator'); ?></label><br>
                        <select id="pod-preset-category" class="widefat">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="pod-preset-thumbnail"><?php esc_html_e('Preview Image URL', 'pod-aggregator'); ?></label><br>
                        <input type="text" id="pod-preset-thumbnail" class="widefat" placeholder="https://...">
                        <button type="button" class="button" id="pod-preset-upload-thumb"><?php esc_html_e('Upload Image', 'pod-aggregator'); ?></button>
                    </p>

                    <p>
                        <label for="pod-preset-json"><?php esc_html_e('Design JSON', 'pod-aggregator'); ?></label><br>
                        <textarea id="pod-preset-json" class="widefat" rows="12"
                                  placeholder='{"area":"front","product_id":1,"elements":[...]}'></textarea>
                        <span class="description">
                            <?php esc_html_e('Paste a Design JSON object. You can create one using the [pod_customizer] shortcode and saving a design.', 'pod-aggregator'); ?>
                        </span>
                    </p>
                </div>
                <div class="pod-preset-modal__footer">
                    <button type="button" class="button button-primary" id="pod-preset-save"><?php esc_html_e('Save Template', 'pod-aggregator'); ?></button>
                    <button type="button" class="button" id="pod-preset-cancel"><?php esc_html_e('Cancel', 'pod-aggregator'); ?></button>
                    <button type="button" class="button button-link-delete" id="pod-preset-delete" style="display:none; float:right;"><?php esc_html_e('Delete', 'pod-aggregator'); ?></button>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var modal = $('#pod-preset-modal');
            var uuidInput = $('#pod-preset-uuid');
            var nameInput = $('#pod-preset-name');
            var catSelect = $('#pod-preset-category');
            var thumbInput = $('#pod-preset-thumbnail');
            var jsonInput = $('#pod-preset-json');

            function openModal(preset) {
                preset = preset || {};
                uuidInput.val(preset.uuid || '');
                nameInput.val(preset.name || '');
                catSelect.val(preset.category || '');
                thumbInput.val(preset.thumbnail || '');
                jsonInput.val(preset.design ? JSON.stringify(preset.design, null, 2) : '');
                $('#pod-preset-modal-title').text(preset.uuid ? '<?php esc_attr_e('Edit Template', 'pod-aggregator'); ?>' : '<?php esc_attr_e('Add New Template', 'pod-aggregator'); ?>');
                $('#pod-preset-delete').toggle(!!preset.uuid);
                modal.show();
            }

            function closeModal() {
                modal.hide();
            }

            $('#pod-add-preset-btn, #pod-preset-modal .pod-preset-modal__close, #pod-preset-cancel').on('click', function(e) {
                e.preventDefault();
                openModal();
            });

            modal.find('.pod-preset-modal__backdrop').on('click', closeModal);

            $('#pod-preset-modal-close').on('click', closeModal);

            $(document).on('click', '.pod-preset-edit-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                openModal({
                    uuid: btn.data('uuid'),
                    name: btn.data('name'),
                    category: btn.data('category'),
                    thumbnail: btn.data('thumbnail'),
                    design: btn.data('design') ? JSON.parse(btn.data('design')) : null,
                });
            });

            $('#pod-preset-save').on('click', function() {
                var uuid = uuidInput.val();
                var data = {
                    nonce: PODPresets.nonce,
                    name: nameInput.val(),
                    category: catSelect.val(),
                    thumbnail: thumbInput.val(),
                    design_json: jsonInput.val(),
                    uuid: uuid,
                };

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'pod_save_preset',
                        nonce: PODPresets.nonce,
                        ...data,
                    },
                }).done(function(r) {
                    if (r.success) {
                        location.reload();
                    } else {
                        alert(r.data.message || 'Error');
                    }
                }).fail(function() {
                    alert('Request failed');
                });
            });

            $('#pod-preset-delete').on('click', function() {
                if (!confirm(PODPresets.i18n.deleteConfirm)) return;
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'pod_delete_preset',
                        nonce: PODPresets.nonce,
                        uuid: uuidInput.val(),
                    },
                }).done(function(r) {
                    if (r.success) {
                        location.reload();
                    } else {
                        alert(r.data.message || 'Error');
                    }
                });
            });

            $('#pod-preset-upload-thumb').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php esc_attr_e('Select Preview Image', 'pod-aggregator'); ?>',
                    multiple: false,
                    button: { text: '<?php esc_attr_e('Use Image', 'pod-aggregator'); ?>' },
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    thumbInput.val(attachment.url);
                });
                frame.open();
            });
        });
        </script>

        <?php
    }

    /**
     * Render a single preset card.
     *
     * @param array $preset
     * @return void
     */
    private function render_preset_card(array $preset): void
    {
        $thumb = $preset['thumbnail'];
        ?>
        <div class="pod-preset-card"
             data-uuid="<?php echo esc_attr($preset['uuid']); ?>"
             data-name="<?php echo esc_attr($preset['name']); ?>"
             data-category="<?php echo esc_attr($preset['category'] ?? ''); ?>"
             data-thumbnail="<?php echo esc_attr($thumb); ?>"
             data-design="<?php echo esc_attr(wp_json_encode($preset['design'])); ?>">

            <div class="pod-preset-card__thumb">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($preset['name']); ?>">
                <?php else: ?>
                    <div class="pod-preset-card__placeholder">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pod-preset-card__info">
                <strong><?php echo esc_html($preset['name']); ?></strong>
                <br><small><?php echo esc_html($preset['area']); ?></small>
            </div>

            <div class="pod-preset-card__actions">
                <button type="button" class="button button-small pod-preset-edit-btn"
                        data-uuid="<?php echo esc_attr($preset['uuid']); ?>"
                        data-name="<?php echo esc_attr($preset['name']); ?>"
                        data-category="<?php echo esc_attr($preset['category'] ?? ''); ?>"
                        data-thumbnail="<?php echo esc_attr($thumb); ?>"
                        data-design="<?php echo esc_attr(wp_json_encode($preset['design'])); ?>">
                    <?php esc_html_e('Edit', 'pod-aggregator'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}
