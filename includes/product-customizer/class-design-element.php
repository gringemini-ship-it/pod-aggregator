<?php
/**
 * POD Aggregator — Design Element.
 *
 * A value object representing a single design layer/element
 * (text, image, shape, background) within a product customization.
 *
 * @package POD_Aggregator\ProductCustomizer
 */

namespace POD_Aggregator\ProductCustomizer;

/**
 * Design Element — immutable value object.
 *
 * Usage:
 *   $el = new DesignElement([
 *       'type'    => DesignElement::TYPE_TEXT,
 *       'text'    => 'Hello World',
 *       'x'       => 100,
 *       'y'       => 200,
 *       'width'   => 300,
 *       'height'  => 80,
 *       'color'   => '#FF0000',
 *       'font'    => 'Arial',
 *       'fontSize'=> 32,
 *       'z_index' => 1,
 *   ]);
 *
 * @since 1.0.0
 */
class DesignElement
{
    /** Supported element types. */
    public const TYPE_TEXT     = 'text';
    public const TYPE_IMAGE    = 'image';
    public const TYPE_SHAPE    = 'shape';
    public const TYPE_BARCODE  = 'barcode';

    /** Supported shape types. */
    public const SHAPE_CIRCLE  = 'circle';
    public const SHAPE_RECT    = 'rect';
    public const SHAPE_LINE    = 'line';
    public const SHAPE_STAR    = 'star';
    /** @var string Element type. */
    private $type;

    /** @var array Raw attributes. */
    private $attrs;

    /**
     * Constructor.
     *
     * @param array $attrs {
     *   string       $type      Element type: text|image|shape.
     *   int          $x        X position in pixels (from left).
     *   int          $y        Y position in pixels (from top).
     *   int          $width    Width in pixels.
     *   int          $height   Height in pixels.
     *   int          $rotation Rotation angle in degrees.
     *   int          $z_index  Layer order (higher = on top).
     *   bool         $locked   Prevent user from moving/editing.
     *   // Text-specific:
     *   string       $text     Text content.
     *   string       $font     Font family.
     *   int          $fontSize Font size in px.
     *   string       $color    Text color hex.
     *   string       $align    Text alignment: left|center|right.
     *   bool         $bold     Bold text.
     *   bool         $italic   Italic text.
     *   bool         $underline Underlined text.
     *   // Image-specific:
     *   string       $src      Image URL or attachment ID.
     *   string       $originalSrc Original image URL (before crop).
     *   int          $cropX    Crop offset X.
     *   int          $cropY    Crop offset Y.
     *   float        $scale    Image scale factor.
     *   // Shape-specific:
     *   string       $shape    Shape type: circle|rect|line|star.
     *   string       $fill     Fill color hex (transparent = no fill).
     *   string       $stroke   Stroke color hex.
     *   int          $strokeWidth Stroke width in px.
     * }
     */
    public function __construct(array $attrs)
    {
        $this->type  = isset($attrs['type']) ? sanitize_key($attrs['type']) : self::TYPE_TEXT;
        $this->attrs = wp_parse_args($attrs, [
            'x'            => 0,
            'y'            => 0,
            'width'        => 200,
            'height'       => 100,
            'rotation'     => 0,
            'z_index'      => 0,
            'locked'       => false,
            // Text
            'text'         => '',
            'font'         => 'Arial',
            'fontSize'     => 24,
            'color'        => '#000000',
            'align'        => 'center',
            'bold'         => false,
            'italic'       => false,
            'underline'    => false,
            // Image
            'src'          => '',
            'originalSrc'  => '',
            'cropX'        => 0,
            'cropY'        => 0,
            'scale'        => 1.0,
            // Shape
            'shape'        => self::SHAPE_RECT,
            'fill'         => 'transparent',
            'stroke'       => '#000000',
            'strokeWidth'  => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function get_type(): string
    {
        return $this->type;
    }

    public function get_x(): int
    {
        return (int) $this->attrs['x'];
    }

    public function get_y(): int
    {
        return (int) $this->attrs['y'];
    }

    public function get_width(): int
    {
        return (int) $this->attrs['width'];
    }

    public function get_height(): int
    {
        return (int) $this->attrs['height'];
    }

    public function get_rotation(): int
    {
        return (int) $this->attrs['rotation'];
    }

    public function get_z_index(): int
    {
        return (int) $this->attrs['z_index'];
    }

    public function is_locked(): bool
    {
        return !empty($this->attrs['locked']);
    }

    public function get_text(): string
    {
        return $this->attrs['text'];
    }

    public function get_font(): string
    {
        return sanitize_text_field($this->attrs['font']);
    }

    public function get_font_size(): int
    {
        return max(6, (int) $this->attrs['fontSize']);
    }

    public function get_color(): string
    {
        return sanitize_hex_color($this->attrs['color']) ?: '#000000';
    }

    public function get_align(): string
    {
        $valid = ['left', 'center', 'right'];
        return in_array($this->attrs['align'], $valid, true) ? $this->attrs['align'] : 'center';
    }

    public function is_bold(): bool
    {
        return !empty($this->attrs['bold']);
    }

    public function is_italic(): bool
    {
        return !empty($this->attrs['italic']);
    }

    public function is_underline(): bool
    {
        return !empty($this->attrs['underline']);
    }

    public function get_src(): string
    {
        return esc_url_raw($this->attrs['src']);
    }

    public function get_original_src(): string
    {
        return esc_url_raw($this->attrs['originalSrc']);
    }

    public function get_scale(): float
    {
        return (float) $this->attrs['scale'];
    }

    public function get_shape(): string
    {
        return sanitize_key($this->attrs['shape']);
    }

    public function get_fill(): string
    {
        return $this->attrs['fill'] === 'transparent'
            ? 'transparent'
            : (sanitize_hex_color($this->attrs['fill']) ?: 'transparent');
    }

    public function get_stroke(): string
    {
        return sanitize_hex_color($this->attrs['stroke']) ?: '#000000';
    }

    public function get_stroke_width(): int
    {
        return (int) $this->attrs['strokeWidth'];
    }

    /**
     * Get all attributes as an array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return $this->attrs;
    }

    /**
     * Serialize to JSON-safe array.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'type'         => $this->type,
            'x'            => $this->get_x(),
            'y'            => $this->get_y(),
            'width'        => $this->get_width(),
            'height'       => $this->get_height(),
            'rotation'     => $this->get_rotation(),
            'z_index'      => $this->get_z_index(),
            'locked'       => $this->is_locked(),
            'text'         => $this->get_text(),
            'font'         => $this->get_font(),
            'fontSize'     => $this->get_font_size(),
            'color'        => $this->get_color(),
            'align'        => $this->get_align(),
            'bold'         => $this->is_bold(),
            'italic'       => $this->is_italic(),
            'underline'    => $this->is_underline(),
            'src'          => $this->get_src(),
            'originalSrc'  => $this->get_original_src(),
            'cropX'        => (int) $this->attrs['cropX'],
            'cropY'        => (int) $this->attrs['cropY'],
            'scale'        => $this->get_scale(),
            'shape'        => $this->get_shape(),
            'fill'         => $this->get_fill(),
            'stroke'       => $this->get_stroke(),
            'strokeWidth'  => $this->get_stroke_width(),
        ];
    }

    // -------------------------------------------------------------------------
    // Factory helpers
    // -------------------------------------------------------------------------

    /**
     * Create a text element.
     *
     * @param string $text
     * @param int    $x
     * @param int    $y
     * @param array  $overrides
     * @return self
     */
    public static function text(string $text, int $x, int $y, array $overrides = []): self
    {
        return new self(array_merge($overrides, [
            'type' => self::TYPE_TEXT,
            'text' => $text,
            'x'    => $x,
            'y'    => $y,
        ]));
    }

    /**
     * Create an image element.
     *
     * @param string $src   Image URL or attachment ID.
     * @param int    $x
     * @param int    $y
     * @param int    $width
     * @param int    $height
     * @param array  $overrides
     * @return self
     */
    public static function image(string $src, int $x, int $y, int $width, int $height, array $overrides = []): self
    {
        return new self(array_merge($overrides, [
            'type'   => self::TYPE_IMAGE,
            'src'    => $src,
            'x'      => $x,
            'y'      => $y,
            'width'  => $width,
            'height' => $height,
        ]));
    }

    /**
     * Create a shape element.
     *
     * @param string $shape  Shape type.
     * @param int    $x
     * @param int    $y
     * @param int    $width
     * @param int    $height
     * @param array  $overrides
     * @return self
     */
    public static function shape(string $shape, int $x, int $y, int $width, int $height, array $overrides = []): self
    {
        return new self(array_merge($overrides, [
            'type'   => self::TYPE_SHAPE,
            'shape'  => $shape,
            'x'      => $x,
            'y'      => $y,
            'width'  => $width,
            'height' => $height,
        ]));
    }

    /**
     * Create from a JSON-serializable array (e.g. from DB or REST).
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self
    {
        return new self($data);
    }
}
