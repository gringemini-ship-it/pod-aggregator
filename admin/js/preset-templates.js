/**
 * POD Aggregator — Preset Templates Admin JS
 *
 * Most logic is inline in class-preset-templates.php.
 * This file provides any additional progressive-enhancement JS.
 *
 * @package POD_Aggregator\\Admin
 */
(function ($) {
    'use strict';

    $(function () {
        // Auto-dismiss admin notices after 3 seconds.
        $('.pod-preset-templates-wrap .notice').each(function () {
            var $notice = $(this);
            setTimeout(function () {
                $notice.fadeOut('slow', function () { $notice.remove(); });
            }, 4000);
        });
    });

})(jQuery);
