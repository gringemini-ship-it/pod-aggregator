<?php
/**
 * POD Aggregator — Print File Generator.
 *
 * Converts a Design (JSON) into high-DPI print-ready PNG files
 * using GD (or Imagick if available).
 *
 * @package POD_Aggregator\ProductCustomizer
 */

namespace POD_Aggregator\ProductCustomizer;

use WP_Error;

/**
 * Generates print files from Design objects.
 *
 * @since 1.0.0
 */
class Print_Generator
{
    /** @var string Upload directory subfolder. */
    public const UPLOAD_SUBDIR = 'pod-prints';

    /** @var string[] Allowed image MIME types. */
    public const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** @var int Max upload size in bytes (5MB). */
    public const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

    /**
     * Generate a print-ready PNG file from a Design.
     *
     * Output is saved to wp-content/uploads/pod-prints/{design_id}/{area}.png
     *
     * @param Design $design
     * @return array|WP_Error {
     *   @type string $file_path Absolute path to the generated file.
     *   @type string $file_url  Public URL to the file.
     *   @type int    $width_px  Width in pixels.
     *   @type int    $height_px Height in pixels.
     *   @type int    $dpi       DPI of the output.
     * }
     */
    public function generate(Design $design)
    {
        if (!extension_loaded('gd')) {
            return new WP_Error(
                'pod_gd_missing',
                __('GD image extension is required for print file generation.', 'pod-aggregator')
            );
        }

        $validate = $design->validate();
        if (is_wp_error($validate)) {
            return $validate;
        }

        $dims = $design->get_print_dimensions();
        $width  = $dims['width_px'];
        $height = $dims['height_px'];
        $dpi    = $design->get_dpi();

        // Create the GD image at print resolution.
        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return new WP_Error(
                'pod_print_gd_failed',
                __('Failed to create GD image resource.', 'pod-aggregator')
            );
        }

        // Preserve transparency for PNG.
        imagealphablending($img, false);
        imagesavealpha($img, true);

        // White background (default; transparent areas can be left white).
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // Draw each element.
        foreach ($design->get_elements() as $el) {
            $this->draw_element($img, $el, $design);
        }

        // Build output path.
        $upload_dir = $this->get_upload_dir();
        if (is_wp_error($upload_dir)) {
            imagedestroy($img);
            return $upload_dir;
        }

        $subdir = $design->get_id();
        $filename = sanitize_file_name($design->get_area() . '.png');
        $relative_path = self::UPLOAD_SUBDIR . "/{$subdir}/{$filename}";
        $full_path = $upload_dir['basedir'] . '/' . $relative_path;

        // Create subdirectory if needed.
        wp_mkdir_p(dirname($full_path));

        // Save as PNG.
        $saved = imagepng($img, $full_path, 9); // Max compression.
        imagedestroy($img);

        if (!$saved) {
            return new WP_Error(
                'pod_print_save_failed',
                sprintf(__('Failed to save print file to %s.', 'pod-aggregator'), $full_path)
            );
        }

        // Set file permissions.
        chmod($full_path, 0644);

        return [
            'file_path'  => $full_path,
            'file_url'   => $upload_dir['baseurl'] . '/' . $relative_path,
            'width_px'   => $width,
            'height_px'  => $height,
            'dpi'        => $dpi,
        ];
    }

    /**
     * Generate a preview JPEG at screen resolution (72dpi) for cart/thumbnail.
     *
     * @param Design $design
     * @param int    $max_width  Max preview width (default 600).
     * @return array|WP_Error
     */
    public function generate_preview(Design $design, int $max_width = 600)
    {
        if (!extension_loaded('gd')) {
            return new WP_Error(
                'pod_gd_missing',
                __('GD image extension is required for preview generation.', 'pod-aggregator')
            );
        }

        $validate = $design->validate();
        if (is_wp_error($validate)) {
            return $validate;
        }

        $dims = $design->get_print_dimensions();
        $print_width  = $dims['width_px'];
        $print_height = $dims['height_px'];

        // Scale down to preview size.
        $ratio = $print_height > 0 ? $print_width / $print_height : 1;
        if ($print_width > $max_width) {
            $width  = $max_width;
            $height = (int) round($max_width / $ratio);
        } else {
            $width  = $print_width;
            $height = $print_height;
        }

        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return new WP_Error('pod_preview_gd_failed', __('Failed to create preview image.', 'pod-aggregator'));
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // Scale + draw elements.
        $scale_x = $width / $print_width;
        $scale_y = $height / $print_height;

        foreach ($design->get_elements() as $el) {
            $this->draw_element($img, $el, $design, $scale_x, $scale_y);
        }

        $upload_dir = $this->get_upload_dir();
        if (is_wp_error($upload_dir)) {
            imagedestroy($img);
            return $upload_dir;
        }

        $relative_path = sprintf(
            '%s/%s/preview_%s.jpg',
            self::UPLOAD_SUBDIR,
            $design->get_id(),
            $design->get_area()
        );
        $full_path = $upload_dir['basedir'] . '/' . $relative_path;

        wp_mkdir_p(dirname($full_path));

        $saved = imagejpeg($img, $full_path, 85);
        imagedestroy($img);

        if (!$saved) {
            return new WP_Error('pod_preview_save_failed', __('Failed to save preview.', 'pod-aggregator'));
        }

        return [
            'file_path' => $full_path,
            'file_url'  => $upload_dir['baseurl'] . '/' . $relative_path,
            'width_px'  => $width,
            'height_px' => $height,
        ];
    }

    // -------------------------------------------------------------------------
    // Element rendering
    // -------------------------------------------------------------------------

    /**
     * Draw a single DesignElement onto a GD image resource.
     *
     * @param resource       $img     GD image resource.
     * @param DesignElement  $el      Element to draw.
     * @param Design         $design  Parent design (for DPI conversion).
     * @param float          $scale_x Horizontal scale factor (for preview).
     * @param float          $scale_y Vertical scale factor.
     * @return void
     */
    private function draw_element($img, DesignElement $el, Design $design, float $scale_x = 1.0, float $scale_y = 1.0): void
    {
        $type = $el->get_type();
        $x    = (int) round($el->get_x() * $scale_x);
        $y    = (int) round($el->get_y() * $scale_y);
        $w    = (int) round($el->get_width() * $scale_x);
        $h    = (int) round($el->get_height() * $scale_y);

        // Skip invisible / zero-size elements.
        if ($w <= 0 || $h <= 0) {
            return;
        }

        switch ($type) {
            case DesignElement::TYPE_TEXT:
                $this->draw_text($img, $el, $x, $y, $w);
                break;

            case DesignElement::TYPE_IMAGE:
                $this->draw_image($img, $el, $x, $y, $w, $h);
                break;

            case DesignElement::TYPE_SHAPE:
                $this->draw_shape($img, $el, $x, $y, $w, $h);
                break;

            default:
                break;
        }
    }

    /**
     * Draw a text element.
     *
     * @param resource      $img
     * @param DesignElement $el
     * @param int          $x
     * @param int          $y
     * @param int          $w
     * @return void
     */
    private function draw_text($img, DesignElement $el, int $x, int $y, int $w): void
    {
        $text = $el->get_text();
        if (empty($text)) {
            return;
        }

        $color = $this->parse_color($img, $el->get_color());
        $size  = max(6, (int) round($el->get_font_size() * 1.0)); // Scale not applied here.
        $align = $el->get_align();

        // Bold + italic font selection.
        $font_file = $this->get_font_path($el->get_font(), $el->is_bold(), $el->is_italic());

        if ($font_file && file_exists($font_file)) {
            // Use TrueType font.
            $angle = 0;
            $bbox  = imagettfbbox($size, $angle, $font_file, $text);
            $tw    = abs($bbox[2] - $bbox[0]);

            if ($align === 'left') {
                $tx = $x;
            } elseif ($align === 'right') {
                $tx = $x + $w - $tw;
            } else {
                $tx = $x + ($w - $tw) / 2;
            }

            imagettftext($img, $size, $angle, (int) $tx, (int) ($y + $size), $color, $font_file, $text);
        } else {
            // GD built-in fonts (1–5).
            $gd_font = 4; // Approx 16px.
            $fw = imagefontwidth($gd_font) * strlen($text);

            if ($align === 'left') {
                $tx = $x;
            } elseif ($align === 'right') {
                $tx = $x + $w - $fw;
            } else {
                $tx = $x + ($w - $fw) / 2;
            }

            imagestring($img, $gd_font, (int) $tx, (int) $y, $text, $color);
        }

        // Underline.
        if ($el->is_underline()) {
            $uly = $y + (int) round($el->get_font_size() / 12) + 2;
            imageline($img, $x, $uly, $x + $w - 1, $uly, $color);
        }
    }

    /**
     * Draw an image element.
     *
     * @param resource      $img
     * @param DesignElement $el
     * @param int          $x
     * @param int          $y
     * @param int          $w
     * @param int          $h
     * @return void
     */
    private function draw_image($img, DesignElement $el, int $x, int $y, int $w, int $h): void
    {
        $src = $el->get_src();
        if (empty($src)) {
            return;
        }

        // Load the source image.
        $src_img = $this->load_image_from_url_or_path($src);
        if (!$src_img) {
            return;
        }

        $src_w = imagesx($src_img);
        $src_h = imagesy($src_img);

        if ($src_w <= 0 || $src_h <= 0) {
            imagedestroy($src_img);
            return;
        }

        // Preserve aspect ratio; fit inside $w × $h.
        $ratio = min($w / $src_w, $h / $src_h);
        $dst_w = (int) round($src_w * $ratio);
        $dst_h = (int) round($src_h * $ratio);
        $dst_x = $x + ($w - $dst_w) / 2;
        $dst_y = $y + ($h - $dst_h) / 2;

        // Resize and copy.
        $resized = imagecreatetruecolor($dst_w, $dst_h);
        if (!$resized) {
            imagedestroy($src_img);
            return;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $src_img, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

        // Copy onto main canvas.
        imagealphablending($img, true);
        imagecopy($img, $resized, (int) $dst_x, (int) $dst_y, 0, 0, $dst_w, $dst_h);

        imagedestroy($resized);
        imagedestroy($src_img);
    }

    /**
     * Draw a shape element.
     *
     * @param resource      $img
     * @param DesignElement $el
     * @param int          $x
     * @param int          $y
     * @param int          $w
     * @param int          $h
     * @return void
     */
    private function draw_shape($img, DesignElement $el, int $x, int $y, int $w, int $h): void
    {
        $fill   = $el->get_fill();
        $stroke = $el->get_stroke();
        $sw     = $el->get_stroke_width();

        $fill_color   = 'transparent' === $fill ? null : $this->parse_color($img, $fill);
        $stroke_color = $this->parse_color($img, $stroke);

        switch ($el->get_shape()) {
            case DesignElement::SHAPE_CIRCLE:
                $cx = $x + (int) round($w / 2);
                $cy = $y + (int) round($h / 2);
                $r  = (int) round(min($w, $h) / 2);
                if ($fill_color) {
                    imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $fill_color);
                }
                if ($sw > 0) {
                    imagesetthickness($img, $sw);
                    imageellipse($img, $cx, $cy, $r * 2, $r * 2, $stroke_color);
                }
                break;

            case DesignElement::SHAPE_LINE:
                imagesetthickness($img, max(1, $sw));
                imageline($img, $x, $y, $x + $w, $y + $h, $stroke_color);
                break;

            case DesignElement::SHAPE_RECT:
            default:
                if ($fill_color) {
                    imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $fill_color);
                }
                if ($sw > 0) {
                    imagesetthickness($img, $sw);
                    imagerectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $stroke_color);
                }
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the WordPress upload directory, creating it if needed.
     *
     * @return array|WP_Error
     */
    private function get_upload_dir(): array
    {
        $dir = wp_upload_dir(null, true, true);
        if (!empty($dir['error'])) {
            return new WP_Error('pod_upload_dir_error', $dir['error']);
        }
        return $dir;
    }

    /**
     * Parse a hex color into a GD color resource.
     *
     * @param resource $img
     * @param string   $hex   Hex color e.g. '#FF0000'.
     * @return int GD color index.
     */
    private function parse_color($img, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return imagecolorallocate($img, $r, $g, $b);
    }

    /**
     * Load a GD image from a URL or local path.
     *
     * @param string $src
     * @return resource|false
     */
    private function load_image_from_url_or_path($src)
    {
        // Try as local file first.
        if (file_exists($src) && is_file($src)) {
            return $this->gd_from_file($src);
        }

        // Try as WordPress attachment.
        if (absint($src) > 0) {
            $path = get_attached_file(absint($src));
            if ($path && file_exists($path)) {
                return $this->gd_from_file($path);
            }
        }

        // Try as URL via HTTP.
        $response = wp_remote_get($src, ['timeout' => 10]);
        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        $tmp = tmpfile();
        if (!$tmp) {
            return false;
        }

        fwrite($tmp, $body);
        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'];

        $img = $this->gd_from_file($path);
        fclose($tmp);
        return $img;
    }

    /**
     * Create a GD image from a local file path.
     *
     * @param string $path
     * @return resource|false
     */
    private function gd_from_file(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Get the path to a font file for GD rendering.
     *
     * Tries common system font directories and wp-content/fonts.
     *
     * @param string $font_family e.g. 'Arial'.
     * @param bool   $bold
     * @param bool   $italic
     * @return string|null Path to .ttf file or null.
     */
    private function get_font_path(string $font_family, bool $bold, bool $italic): ?string
    {
        static $font_dirs = null;
        if ($font_dirs === null) {
            $font_dirs = [
                '/usr/share/fonts/truetype/',
                '/usr/share/fonts/',
                '/System/Library/Fonts/',          // macOS
                'C:/Windows/Fonts/',                // Windows
                WP_CONTENT_DIR . '/fonts/',
            ];
        }

        // Normalize family name.
        $family = strtolower(preg_replace('/[^a-z]/i', '', $font_family));

        $suffix = '';
        if ($bold && $italic) {
            $suffix = 'bi';
        } elseif ($bold) {
            $suffix = 'bd';
        } elseif ($italic) {
            $suffix = 'i';
        }

        $candidates = [
            "{$family}{$suffix}.ttf",
            "{$family}.ttf",
            "{$font_family}{$suffix}.ttf",
            "{$font_family}.ttf",
        ];

        foreach ($font_dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $path = $dir . $candidate;
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
