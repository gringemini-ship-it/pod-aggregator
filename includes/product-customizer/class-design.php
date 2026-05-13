<?php
/**
 * POD Aggregator — Design.
 *
 * A complete product design: a collection of DesignElement objects
 * plus metadata (print area, DPI, product reference).
 *
 * @package POD_Aggregator\ProductCustomizer
 */

namespace POD_Aggregator\ProductCustomizer;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;

/**
 * Print area definitions — maps named areas to physical dimensions.
 *
 * @since 1.0.0
 */
class Design implements Countable, IteratorAggregate, JsonSerializable
{
    /** Default DPI for print file generation. */
    public const DEFAULT_DPI = 300;

    /** Print area: front. */
    public const AREA_FRONT = 'front';

    /** Print area: back. */
    public const AREA_BACK = 'back';

    /** Print area: left sleeve. */
    public const AREA_LEFT_SLEEVE = 'left_sleeve';

    /** Print area: right sleeve. */
    public const AREA_RIGHT_SLEEVE = 'right_sleeve';

    /**
     * Print area dimensions in mm (width × height).
     * Add more areas as needed for specific product types.
     *
     * @var array<string, array{width_mm: float, height_mm: float}>
     */
    public static $print_areas = [
        self::AREA_FRONT        => ['width_mm' => 300, 'height_mm' => 400],
        self::AREA_BACK         => ['width_mm' => 300, 'height_mm' => 400],
        self::AREA_LEFT_SLEEVE  => ['width_mm' => 100, 'height_mm' => 100],
        self::AREA_RIGHT_SLEEVE => ['width_mm' => 100, 'height_mm' => 100],
    ];

    /** @var string Unique design ID (UUID). */
    private $id;

    /** @var string Print area this design belongs to (e.g. 'front'). */
    private $area;

    /** @var string Design name/title. */
    private $name;

    /** @var int WC product ID this design is for. */
    private $product_id;

    /** @var string Provider slug. */
    private $provider;

    /** @var string Provider product ID. */
    private $provider_product_id;

    /** @var DesignElement[] Elements sorted by z_index. */
    private $elements = [];

    /** @var int DPI for print file output. */
    private $dpi;

    /** @var int Unix timestamp. */
    private $created_at;

    /** @var int Unix timestamp. */
    private $updated_at;

    /**
     * Constructor.
     *
     * @param array $attrs {
     *   string      $id       Unique ID (generated if not provided).
     *   string      $area     Print area (e.g. 'front').
     *   string      $name     Design name.
     *   int         $product_id WC product ID.
     *   string      $provider Provider slug.
     *   string      $provider_product_id Provider product ID.
     *   array       $elements Array of DesignElement or arrays.
     *   int         $dpi      Print DPI (default: 300).
     *   int         $created_at Unix timestamp.
     *   int         $updated_at Unix timestamp.
     * }
     */
    public function __construct(array $attrs = [])
    {
        $this->id                  = !empty($attrs['id']) ? sanitize_key($attrs['id']) : $this->generate_id();
        $this->area                = !empty($attrs['area']) ? sanitize_key($attrs['area']) : self::AREA_FRONT;
        $this->name                = !empty($attrs['name']) ? sanitize_text_field($attrs['name']) : __('Untitled Design', 'pod-aggregator');
        $this->product_id          = !empty($attrs['product_id']) ? absint($attrs['product_id']) : 0;
        $this->provider            = !empty($attrs['provider']) ? sanitize_key($attrs['provider']) : 'printful';
        $this->provider_product_id = !empty($attrs['provider_product_id']) ? sanitize_text_field($attrs['provider_product_id']) : '';
        $this->dpi                 = !empty($attrs['dpi']) ? absint($attrs['dpi']) : self::DEFAULT_DPI;
        $this->created_at          = !empty($attrs['created_at']) ? absint($attrs['created_at']) : time();
        $this->updated_at          = !empty($attrs['updated_at']) ? absint($attrs['updated_at']) : time();

        foreach ((array) ($attrs['elements'] ?? []) as $el) {
            $this->add_element($el);
        }
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function get_id(): string
    {
        return $this->id;
    }

    public function get_area(): string
    {
        return $this->area;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_product_id(): int
    {
        return $this->product_id;
    }

    public function get_provider(): string
    {
        return $this->provider;
    }

    public function get_provider_product_id(): string
    {
        return $this->provider_product_id;
    }

    public function get_dpi(): int
    {
        return $this->dpi;
    }

    public function get_created_at(): int
    {
        return $this->created_at;
    }

    public function get_updated_at(): int
    {
        return $this->updated_at;
    }

    /**
     * Get elements sorted by z_index (ascending — lower z_index drawn first).
     *
     * @return DesignElement[]
     */
    public function get_elements(): array
    {
        $elements = $this->elements;
        usort($elements, fn(DesignElement $a, DesignElement $b) => $a->get_z_index() <=> $b->get_z_index());
        return $elements;
    }

    /**
     * Get the physical print dimensions in pixels at the configured DPI.
     *
     * @return array{width_px: int, height_px: int}
     */
    public function get_print_dimensions(): array
    {
        $area = self::$print_areas[$this->area] ?? ['width_mm' => 300, 'height_mm' => 400];
        $mm_to_inch = 1 / 25.4;
        $width_px  = (int) ceil($area['width_mm']  * $mm_to_inch * $this->dpi);
        $height_px = (int) ceil($area['height_mm'] * $mm_to_inch * $this->dpi);
        return ['width_px' => $width_px, 'height_px' => $height_px];
    }

    /**
     * Convert a canvas pixel coordinate to print mm.
     *
     * @param int $px Pixel value.
     * @return float Millimeter value.
     */
    public function px_to_mm(int $px): float
    {
        $area = self::$print_areas[$this->area] ?? ['width_mm' => 300, 'height_mm' => 400];
        $dims = $this->get_print_dimensions();
        $mm_per_px = $area['width_mm'] / $dims['width_px'];
        return $px * $mm_per_px;
    }

    // -------------------------------------------------------------------------
    // Element management
    // -------------------------------------------------------------------------

    /**
     * Add an element to this design.
     *
     * @param DesignElement|array $element Element instance or array.
     * @return $this
     */
    public function add_element($element): self
    {
        if (is_array($element)) {
            $element = DesignElement::from_array($element);
        }
        $this->elements[] = $element;
        return $this;
    }

    /**
     * Remove an element by its index in the current (unsorted) array.
     *
     * @param int $index
     * @return $this
     */
    public function remove_element(int $index): self
    {
        if (isset($this->elements[$index])) {
            array_splice($this->elements, $index, 1);
        }
        return $this;
    }

    /**
     * Clear all elements.
     *
     * @return $this
     */
    public function clear_elements(): self
    {
        $this->elements = [];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Countable / Iterator
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return count($this->elements);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->get_elements());
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    /**
     * Full JSON serialization (for storage in DB or REST).
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id'                  => $this->id,
            'area'                => $this->area,
            'name'                => $this->name,
            'product_id'          => $this->product_id,
            'provider'            => $this->provider,
            'provider_product_id' => $this->provider_product_id,
            'dpi'                 => $this->dpi,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'elements'            => array_map(
                fn(DesignElement $el) => $el->jsonSerialize(),
                $this->elements
            ),
        ];
    }

    /**
     * Serialize to JSON string.
     *
     * @return string
     */
    public function to_json(): string
    {
        return wp_json_encode($this->jsonSerialize());
    }

    /**
     * Create a Design from a JSON string.
     *
     * @param string $json
     * @return static
     */
    public static function from_json(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new static();
        }
        return static::from_array($data);
    }

    /**
     * Create a Design from a data array.
     *
     * @param array $data
     * @return static
     */
    public static function from_array(array $data): self
    {
        return new static($data);
    }

    /**
     * Validate design data (e.g. before saving or generating a print file).
     *
     * @return true|WP_Error True on success; WP_Error on failure.
     */
    public function validate()
    {
        if (empty($this->id)) {
            return new \WP_Error('pod_design_invalid', __('Design ID is missing.', 'pod-aggregator'));
        }

        if (empty($this->product_id)) {
            return new \WP_Error('pod_design_invalid', __('Product ID is required.', 'pod-aggregator'));
        }

        if (!isset(self::$print_areas[$this->area])) {
            return new \WP_Error('pod_design_invalid', __('Invalid print area.', 'pod-aggregator'));
        }

        if (empty($this->elements)) {
            return new \WP_Error('pod_design_invalid', __('Design must contain at least one element.', 'pod-aggregator'));
        }

        foreach ($this->elements as $el) {
            if (!($el instanceof DesignElement)) {
                return new \WP_Error('pod_design_invalid', __('Invalid element in design.', 'pod-aggregator'));
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a unique design ID.
     *
     * @return string
     */
    private function generate_id(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0x0fff) | 0x4000,
            wp_rand(0, 0x3fff) | 0x8000,
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff)
        );
    }

    /**
     * Get a specific print area dimensions.
     *
     * @param string $area
     * @return array{width_mm: float, height_mm: float}
     */
    public static function get_print_area(string $area): array
    {
        return self::$print_areas[$area] ?? ['width_mm' => 300, 'height_mm' => 400];
    }
}
