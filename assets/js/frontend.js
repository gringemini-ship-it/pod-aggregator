/**
 * POD Aggregator — Frontend JavaScript
 *
 * Handles AJAX add-to-cart and design data submission for POD products.
 *
 * @package POD_Aggregator
 */

(function ($) {
    'use strict';

    /**
     * Initialize POD customizer on the current page.
     */
    function initCustomizer() {
        $('.pod-customizer').each(function () {
            var $container = $(this);
            var productId = $container.find('.pod-add-to-cart-btn').data('product-id');

            // Bind update preview button.
            $container.on('click', '.pod-update-preview-btn', function () {
                updatePreview($container, productId);
            });

            // Bind variant change to price update.
            $container.on('change', '.pod-variant-select', function () {
                var price = $(this).find(':selected').data('price');
                if (price !== undefined) {
                    $container.find('.pod-price-display').text(formatPrice(price));
                }
            });
        });
    }

    /**
     * Update the product mockup preview with current design data.
     *
     * @param {jQuery} $container
     * @param {string} productId
     */
    function updatePreview($container, productId) {
        var $canvas = $('#pod-design-canvas-' + productId);
        if (!$canvas.length) return;

        var text = $container.find('.pod-text-input[data-position="front"]').val() || '';
        var ctx = $canvas[0].getContext('2d');

        // Clear canvas.
        ctx.clearRect(0, 0, $canvas[0].width, $canvas[0].height);

        // Draw text.
        if (text) {
            ctx.fillStyle = '#000';
            ctx.font = 'bold 24px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(text, $canvas[0].width / 2, $canvas[0].height / 2);
        }
    }

    /**
     * Format a price number as a WooCommerce-style price string.
     *
     * @param {number} price
     * @return {string}
     */
    function formatPrice(price) {
        return '$' + parseFloat(price).toFixed(2);
    }

    /**
     * Global function to open customizer for a product (used by catalog).
     *
     * @param {string} productId
     * @param {string} provider
     */
    window.podOpenCustomizer = function (productId, provider) {
        // Load customizer via AJAX and display in a modal or inline.
        var $ = jQuery;

        $.ajax({
            url: PODConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pod_load_customizer',
                nonce: PODConfig.nonce,
                product_id: productId,
                provider: provider
            },
            success: function (response) {
                if (response.success && response.data.html) {
                    // Create a simple modal with the customizer.
                    var $modal = $(
                        '<div class="pod-customizer-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;">' +
                        '<div style="background:#fff;max-width:960px;width:90%;max-height:90vh;overflow-y:auto;padding:30px;position:relative;border-radius:4px;">' +
                        '<button class="pod-modal-close" style="position:absolute;top:10px;right:15px;font-size:20px;background:none;border:none;cursor:pointer;">&times;</button>' +
                        '<div class="pod-modal-content"></div>' +
                        '</div></div>'
                    );

                    $modal.find('.pod-modal-content').html(response.data.html);
                    $modal.appendTo('body');

                    $modal.on('click', '.pod-modal-close', function () {
                        $modal.remove();
                    });

                    $modal.on('click', function (e) {
                        if (e.target === this) {
                            $modal.remove();
                        }
                    });
                }
            }
        });
    };

    // Initialize when DOM is ready.
    $(document).ready(function () {
        initCustomizer();
    });

})(jQuery);
