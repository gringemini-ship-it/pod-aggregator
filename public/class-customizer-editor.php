/**
 * POD Aggregator — Product Customizer Canvas Editor
 *
 * Uses Fabric.js to provide an interactive product design canvas.
 *
 * Features:
 * - Text tool (add, edit, font, size, color, bold/italic/underline)
 * - Image tool (upload, drag, scale, crop)
 * - Shape tool (rectangle, circle, line)
 * - Layer management (z-index ordering, visibility toggle, lock)
 * - Undo/Redo (command history stack)
 * - Selection, move, resize, rotate
 * - Delete selected
 * - Export to/from JSON design format
 *
 * @package POD_Aggregator\Public
 */

namespace POD_Aggregator\Public0;

class POD_Customizer_Editor
{
    /** @var string Default print area. */
    public const DEFAULT_AREA = 'front';

    /** @var int Canvas scale: pixels per mm (for editing canvas). */
    public const CANVAS_SCALE_PX_PER_MM = 3;

    /** @var string[] Available fonts for the text tool. */
    public static $available_fonts = [
        'Arial',
        'Georgia',
        'Times New Roman',
        'Courier New',
        'Verdana',
        'Trebuchet MS',
        'Impact',
        'Comic Sans MS',
        'Lucida Console',
        'Palatino Linotype',
    ];

    /** @var string[] Available shape types. */
    public static $shape_types = [
        'rect'   => 'Rectangle',
        'circle' => 'Circle',
        'line'   => 'Line',
    ];

    /**
     * Enqueue editor assets (Fabric.js + editor JS/CSS).
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        $asset_base = plugins_url('public', dirname(__DIR__));

        // Fabric.js from CDN (or bundle your own).
        wp_enqueue_script(
            'fabric-js',
            'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
            [],
            '5.3.1',
            false
        );

        // Color picker.
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Editor JS.
        wp_enqueue_script(
            'pod-customizer-editor',
            $asset_base . '/js/pod-customizer-editor.js',
            ['fabric-js', 'jquery', 'wp-color-picker'],
            POD_AGGREGATOR_VERSION,
            true
        );

        // Editor CSS.
        wp_enqueue_style(
            'pod-customizer-editor',
            $asset_base . '/css/pod-customizer-editor.css',
            [],
            POD_AGGREGATOR_VERSION
        );

        // Expose data to JS.
        wp_localize_script('pod-customizer-editor', 'PODCustomizer', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('pod_customizer_editor'),
            'restBase'       => rest_url('pod-aggregator/v1/designs'),
            'restNonce'      => wp_create_nonce('wp_rest'),
            'presetsRestBase' => rest_url('pod-aggregator/v1/presets'),
            'availableFonts' => self::$available_fonts,
            'shapeTypes'     => self::$shape_types,
            'productId'      => 0, // Set dynamically per-instance below.
            'i18n'           => [
                'addText'    => __('Add Text', 'pod-aggregator'),
                'addImage'   => __('Add Image', 'pod-aggregator'),
                'addShape'   => __('Add Shape', 'pod-aggregator'),
                'layers'     => __('Layers', 'pod-aggregator'),
                'properties' => __('Properties', 'pod-aggregator'),
                'undo'       => __('Undo', 'pod-aggregator'),
                'redo'       => __('Redo', 'pod-aggregator'),
                'delete'     => __('Delete', 'pod-aggregator'),
                'lock'       => __('Lock', 'pod-aggregator'),
                'unlock'     => __('Unlock', 'pod-aggregator'),
                'duplicate'  => __('Duplicate', 'pod-aggregator'),
                'bringFront' => __('Bring to Front', 'pod-aggregator'),
                'sendBack'   => __('Send to Back', 'pod-aggregator'),
                'font'       => __('Font', 'pod-aggregator'),
                'fontSize'   => __('Size', 'pod-aggregator'),
                'color'      => __('Color', 'pod-aggregator'),
                'bold'       => __('Bold', 'pod-aggregator'),
                'italic'     => __('Italic', 'pod-aggregator'),
                'underline'  => __('Underline', 'pod-aggregator'),
                'alignLeft'  => __('Align Left', 'pod-aggregator'),
                'alignCenter'=> __('Align Center', 'pod-aggregator'),
                'alignRight' => __('Align Right', 'pod-aggregator'),
                'uploadImage'=> __('Upload Image', 'pod-aggregator'),
                'enterText'  => __('Enter text...', 'pod-aggregator'),
                'rectangle'  => __('Rectangle', 'pod-aggregator'),
                'circle'     => __('Circle', 'pod-aggregator'),
                'line'       => __('Line', 'pod-aggregator'),
                'fillColor'  => __('Fill', 'pod-aggregator'),
                'strokeColor'=> __('Stroke', 'pod-aggregator'),
                'strokeWidth'=> __('Stroke Width', 'pod-aggregator'),
                'save'       => __('Save Design', 'pod-aggregator'),
                'saving'     => __('Saving...', 'pod-aggregator'),
                'saved'      => __('Saved!', 'pod-aggregator'),
                'noElements' => __('No elements yet. Add text, image, or shape.', 'pod-aggregator'),
                'confirmClear' => __('Clear all elements? This cannot be undone.', 'pod-aggregator'),
                'templates'  => __('Starter Templates', 'pod-aggregator'),
                'applyTemplate' => __('Apply Template', 'pod-aggregator'),
                'addToCart'  => __('Add to Cart', 'pod-aggregator'),
                'savingCart' => __('Adding to cart...', 'pod-aggregator'),
                'addedCart'  => __('Added!', 'pod-aggregator'),
                'cartError'  => __('Error. Please try again.', 'pod-aggregator'),
            ],
        ]);
    }

    /**
     * Render the customizer editor HTML.
     *
     * Called by the [pod_customizer] shortcode.
     *
     * @param int    $product_id  WC product ID.
     * @param string $area        Print area (front/back/left_sleeve/right_sleeve).
     * @param string $design_uuid Existing design UUID to load (optional).
     * @return string HTML output.
     */
    public function render_editor(int $product_id, string $area, string $design_uuid = ''): string
    {
        // Physical print area dimensions in mm.
        $area_dims = \POD_Aggregator\ProductCustomizer\Design::get_print_area($area);
        $width_mm  = $area_dims['width_mm'];
        $height_mm = $area_dims['height_mm'];

        // Canvas pixel dimensions (3px per mm).
        $canvas_w = (int) round($width_mm  * self::CANVAS_SCALE_PX_PER_MM);
        $canvas_h = (int) round($height_mm * self::CANVAS_SCALE_PX_PER_MM);

        // CSS dimensions for display (may differ from canvas pixel dims for responsiveness).
        $css_w = min($canvas_w, 900); // Max display width 900px.
        $css_h = (int) round($css_w * ($canvas_h / $canvas_w));

        ob_start();
        ?>
        <div class="pod-customizer"
             data-product-id="<?php echo esc_attr($product_id); ?>"
             data-area="<?php echo esc_attr($area); ?>"
             data-design-uuid="<?php echo esc_attr($design_uuid); ?>"
             data-canvas-w="<?php echo esc_attr($canvas_w); ?>"
             data-canvas-h="<?php echo esc_attr($canvas_h); ?>"
             data-css-w="<?php echo esc_attr($css_w); ?>"
             data-css-h="<?php echo esc_attr($css_h); ?>"
             data-width-mm="<?php echo esc_attr($width_mm); ?>"
             data-height-mm="<?php echo esc_attr($height_mm); ?>">

            <?php // Toolbar ?>
            <div class="pod-customizer__toolbar">
                <div class="pod-customizer__tool-group">
                    <button type="button" class="pod-tool-btn" data-tool="select" title="<?php esc_attr_e('Select / Move', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-move"></span>
                    </button>
                    <button type="button" class="pod-tool-btn" data-tool="text" title="<?php esc_attr_e('Add Text', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-text"></span>
                    </button>
                    <button type="button" class="pod-tool-btn" data-tool="image" title="<?php esc_attr_e('Add Image', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-format-image"></span>
                    </button>
                    <button type="button" class="pod-tool-btn" data-tool="shape" title="<?php esc_attr_e('Add Shape', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                </div>

                <div class="pod-customizer__tool-divider"></div>

                <div class="pod-customizer__tool-group">
                    <button type="button" class="pod-tool-btn pod-tool-btn--action" data-action="undo" title="<?php esc_attr_e('Undo', 'pod-aggregator'); ?>" disabled>
                        <span class="dashicons dashicons-undo"></span>
                    </button>
                    <button type="button" class="pod-tool-btn pod-tool-btn--action" data-action="redo" title="<?php esc_attr_e('Redo', 'pod-aggregator'); ?>" disabled>
                        <span class="dashicons dashicons-redo"></span>
                    </button>
                </div>

                <div class="pod-customizer__tool-divider"></div>

                <div class="pod-customizer__tool-group">
                    <button type="button" class="pod-tool-btn pod-tool-btn--danger" data-action="delete" title="<?php esc_attr_e('Delete Selected', 'pod-aggregator'); ?>" disabled>
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <button type="button" class="pod-tool-btn pod-tool-btn--danger" data-action="clear" title="<?php esc_attr_e('Clear All', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>

                <div class="pod-customizer__tool-spacer"></div>

                <div class="pod-customizer__tool-group">
                    <button type="button" class="pod-tool-btn pod-tool-btn--primary" data-action="save">
                        <span class="dashicons dashicons-save"></span>
                        <span class="pod-tool-btn__label"><?php esc_html_e('Save', 'pod-aggregator'); ?></span>
                    </button>
                    <button type="button" class="pod-tool-btn" data-action="templates" title="<?php esc_attr_e('Starter Templates', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-portfolio"></span>
                        <span class="pod-tool-btn__label"><?php esc_html_e('Templates', 'pod-aggregator'); ?></span>
                    </button>
                    <button type="button" class="pod-tool-btn" data-action="ai-generate" title="<?php esc_attr_e('AI Generate Design', 'pod-aggregator'); ?>">
                        <span class="dashicons dashicons-superhero"></span>
                        <span class="pod-tool-btn__label"><?php esc_html_e('AI', 'pod-aggregator'); ?></span>
                    </button>
                </div>
            </div>

            <?php // Main editor area ?>
            <div class="pod-customizer__workspace">
                <?php // Layers panel ?>
                <aside class="pod-customizer__layers">
                    <h3 class="pod-customizer__panel-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('Layers', 'pod-aggregator'); ?>
                    </h3>
                    <ul class="pod-customizer__layers-list" id="pod-layers-list">
                        <li class="pod-layers-empty"><?php esc_html_e('No elements yet.', 'pod-aggregator'); ?></li>
                    </ul>
                </aside>

                <?php // Canvas area ?>
                <div class="pod-customizer__canvas-wrap">
                    <div class="pod-customizer__print-area-hint">
                        <?php
                        printf(
                            esc_html__('Print area: %dmm × %dmm', 'pod-aggregator'),
                            (int) $width_mm,
                            (int) $height_mm
                        );
                        ?>
                    </div>
                    <div class="pod-customizer__canvas-frame" style="width: <?php echo esc_attr($css_w); ?>px;">
                        <canvas id="pod-canvas"></canvas>
                    </div>
                </div>

                <?php // Properties panel ?>
                <aside class="pod-customizer__properties">
                    <h3 class="pod-customizer__panel-title">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_attr_e('Properties', 'pod-aggregator'); ?>
                    </h3>

                    <div class="pod-customizer__no-selection" id="pod-no-selection">
                        <p><?php esc_html_e('Select an element to edit its properties.', 'pod-aggregator'); ?></p>
                    </div>

                    <div class="pod-customizer__props-content" id="pod-props-content" style="display:none;">

                        <?php // Text properties ?>
                        <div class="pod-prop-group" id="pod-prop-text" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Text', 'pod-aggregator'); ?></label>
                            <textarea id="pod-prop-text-content" class="pod-prop-textarea" rows="3"
                                      placeholder="<?php esc_attr_e('Enter text...', 'pod-aggregator'); ?>"></textarea>
                        </div>

                        <div class="pod-prop-group" id="pod-prop-font" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Font', 'pod-aggregator'); ?></label>
                            <select id="pod-prop-font-family" class="pod-prop-select">
                                <?php foreach (self::$available_fonts as $font): ?>
                                    <option value="<?php echo esc_attr($font); ?>"><?php echo esc_html($font); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pod-prop-group pod-prop-row--inline" id="pod-prop-size" style="display:none;">
                            <div class="pod-prop-half">
                                <label class="pod-prop-label"><?php esc_html_e('Size', 'pod-aggregator'); ?></label>
                                <input type="number" id="pod-prop-font-size" class="pod-prop-number" min="6" max="200" value="24">
                            </div>
                            <div class="pod-prop-half">
                                <label class="pod-prop-label"><?php esc_html_e('Color', 'pod-aggregator'); ?></label>
                                <input type="text" id="pod-prop-text-color" class="pod-prop-color" value="#000000">
                            </div>
                        </div>

                        <div class="pod-prop-group" id="pod-prop-text-style" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Style', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-btn-group">
                                <button type="button" class="pod-prop-toggle" data-style="bold" title="<?php esc_attr_e('Bold', 'pod-aggregator'); ?>">B</button>
                                <button type="button" class="pod-prop-toggle" data-style="italic" title="<?php esc_attr_e('Italic', 'pod-aggregator'); ?>"><em>I</em></button>
                                <button type="button" class="pod-prop-toggle" data-style="underline" title="<?php esc_attr_e('Underline', 'pod-aggregator'); ?>"><u>U</u></button>
                            </div>
                        </div>

                        <div class="pod-prop-group" id="pod-prop-align" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Alignment', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-btn-group">
                                <button type="button" class="pod-prop-toggle" data-align="left" title="<?php esc_attr_e('Align Left', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-editor-alignleft"></span>
                                </button>
                                <button type="button" class="pod-prop-toggle" data-align="center" title="<?php esc_attr_e('Align Center', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-editor-aligncenter"></span>
                                </button>
                                <button type="button" class="pod-prop-toggle" data-align="right" title="<?php esc_attr_e('Align Right', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-editor-alignright"></span>
                                </button>
                            </div>
                        </div>

                        <?php // Shape properties ?>
                        <div class="pod-prop-group" id="pod-prop-shape" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Fill Color', 'pod-aggregator'); ?></label>
                            <input type="text" id="pod-prop-fill-color" class="pod-prop-color" value="transparent">
                        </div>

                        <div class="pod-prop-group" id="pod-prop-stroke" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Stroke', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-row--inline">
                                <input type="text" id="pod-prop-stroke-color" class="pod-prop-color pod-prop-half" value="#000000">
                                <input type="number" id="pod-prop-stroke-width" class="pod-prop-number pod-prop-half" min="0" max="20" value="2" placeholder="<?php esc_attr_e('Width', 'pod-aggregator'); ?>">
                            </div>
                        </div>

                        <?php // Image properties (shown when image selected) ?>
                        <div class="pod-prop-group" id="pod-prop-image" style="display:none;">
                            <label class="pod-prop-label"><?php esc_html_e('Image', 'pod-aggregator'); ?></label>
                            <button type="button" class="pod-prop-btn" id="pod-btn-replace-image">
                                <?php esc_html_e('Replace Image', 'pod-aggregator'); ?>
                            </button>
                        </div>

                        <?php // Common (all elements) ?>
                        <div class="pod-prop-group">
                            <label class="pod-prop-label"><?php esc_html_e('Position', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-row--inline">
                                <input type="number" id="pod-prop-x" class="pod-prop-number pod-prop-half" min="0" step="1" placeholder="X">
                                <input type="number" id="pod-prop-y" class="pod-prop-number pod-prop-half" min="0" step="1" placeholder="Y">
                            </div>
                        </div>

                        <div class="pod-prop-group">
                            <label class="pod-prop-label"><?php esc_html_e('Size', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-row--inline">
                                <input type="number" id="pod-prop-width" class="pod-prop-number pod-prop-half" min="1" step="1" placeholder="<?php esc_attr_e('Width', 'pod-aggregator'); ?>">
                                <input type="number" id="pod-prop-height" class="pod-prop-number pod-prop-half" min="1" step="1" placeholder="<?php esc_attr_e('Height', 'pod-aggregator'); ?>">
                            </div>
                        </div>

                        <div class="pod-prop-group">
                            <label class="pod-prop-label"><?php esc_html_e('Rotation', 'pod-aggregator'); ?></label>
                            <input type="number" id="pod-prop-rotation" class="pod-prop-number" min="0" max="360" step="1" value="0">
                        </div>

                        <div class="pod-prop-group">
                            <label class="pod-prop-label"><?php esc_html_e('Layer Order', 'pod-aggregator'); ?></label>
                            <div class="pod-prop-btn-group">
                                <button type="button" class="pod-prop-action" data-layer="front" title="<?php esc_attr_e('Bring to Front', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                </button>
                                <button type="button" class="pod-prop-action" data-layer="forward" title="<?php esc_attr_e('Bring Forward', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                                </button>
                                <button type="button" class="pod-prop-action" data-layer="backward" title="<?php esc_attr_e('Send Backward', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <button type="button" class="pod-prop-action" data-layer="back" title="<?php esc_attr_e('Send to Back', 'pod-aggregator'); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt"></span>
                                </button>
                            </div>
                        </div>

                        <div class="pod-prop-group">
                            <label class="pod-prop-label"><?php esc_html_e('Lock', 'pod-aggregator'); ?></label>
                            <button type="button" class="pod-prop-btn pod-prop-btn--toggle" id="pod-btn-lock">
                                <span class="dashicons dashicons-lock"></span>
                                <span id="pod-lock-label"><?php esc_html_e('Lock Element', 'pod-aggregator'); ?></span>
                            </button>
                        </div>

                        <div class="pod-prop-group">
                            <button type="button" class="pod-prop-btn pod-prop-btn--danger" id="pod-btn-duplicate">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php esc_html_e('Duplicate', 'pod-aggregator'); ?>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>

            <?php // Hidden file input for image upload ?>
            <input type="file" id="pod-image-upload" accept="image/*" style="display:none;">

            <?php // Template gallery modal ?>
            <div id="pod-templates-modal" class="pod-templates-modal" style="display:none;">
                <div class="pod-templates-modal__backdrop"></div>
                <div class="pod-templates-modal__dialog">
                    <div class="pod-templates-modal__header">
                        <h2><?php esc_html_e('Starter Templates', 'pod-aggregator'); ?></h2>
                        <button type="button" class="pod-templates-modal__close" id="pod-templates-modal-close">&times;</button>
                    </div>
                    <div class="pod-templates-modal__tabs" id="pod-templates-tabs">
                        <button type="button" class="pod-templates-modal__tab active" data-category=""><?php esc_html_e('All', 'pod-aggregator'); ?></button>
                        <?php foreach (\POD_Aggregator\Admin\Preset_Templates::DEFAULT_CATEGORIES as $key => $label): ?>
                            <button type="button" class="pod-templates-modal__tab" data-category="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="pod-templates-modal__body" id="pod-templates-body">
                        <div class="pod-templates-modal__grid" id="pod-templates-grid">
                            <div class="pod-templates-modal__loading"><?php esc_html_e('Loading templates...', 'pod-aggregator'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // Add to Cart bar ?>
            <div class="pod-add-to-cart-bar" id="pod-add-to-cart-bar" style="display:none;">
                <span class="pod-add-to-cart-bar__label"><?php esc_html_e('Design:', 'pod-aggregator'); ?></span>
                <span class="pod-add-to-cart-bar__design-name" id="pod-cart-design-name"><?php esc_html_e('Unsaved design', 'pod-aggregator'); ?></span>
                <div class="pod-add-to-cart-bar__actions">
                    <button type="button" class="button" id="pod-btn-save-and-cart">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e('Save & Add to Cart', 'pod-aggregator'); ?>
                    </button>
                    <a href="#" class="button" id="pod-btn-view-cart" style="display:none;">
                        <?php esc_html_e('View Cart', 'pod-aggregator'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get the canvas pixel dimensions for a given print area.
     *
     * @param string $area
     * @return array{width_px: int, height_px: int}
     */
    public static function get_canvas_dimensions(string $area): array
    {
        $dims = \POD_Aggregator\ProductCustomizer\Design::get_print_area($area);
        return [
            'width_px'  => (int) round($dims['width_mm']  * self::CANVAS_SCALE_PX_PER_MM),
            'height_px' => (int) round($dims['height_mm'] * self::CANVAS_SCALE_PX_PER_MM),
        ];
    }
}
