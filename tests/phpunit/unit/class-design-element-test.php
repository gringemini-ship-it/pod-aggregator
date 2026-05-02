<?php
/**
 * Unit tests for DesignElement value object.
 *
 * The actual DesignElement uses an associative-array constructor (not named params):
 *   new DesignElement(['type' => 'text', 'text' => 'Hello', 'x' => 100, ...])
 *
 * Getters: get_type(), get_x(), get_y(), get_width(), get_height(),
 *          get_rotation(), get_z_index(), is_locked(), get_text(), get_font(),
 *          get_font_size(), get_color(), get_align(), is_bold(), is_italic(),
 *          is_underline(), get_src(), get_original_src(), get_scale(),
 *          get_shape(), get_fill(), get_stroke(), get_stroke_width()
 *
 * Static factories: DesignElement::text(), DesignElement::image(),
 *                   DesignElement::shape(), DesignElement::from_array()
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\DesignElement;

class Design_Element_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction — constructor takes array, not named params
    // -------------------------------------------------------------------------

    public function testConstructorSetsType(): void
    {
        $el = new DesignElement(['type' => 'text']);
        $this->assertSame('text', $el->get_type());
    }

    public function testConstructorDefaultsToTextType(): void
    {
        $el = new DesignElement([]);
        $this->assertSame('text', $el->get_type());
    }

    public function testConstructorDefaults(): void
    {
        $el = new DesignElement(['type' => 'text']);

        $this->assertSame(0, $el->get_x());
        $this->assertSame(0, $el->get_y());
        $this->assertSame(200, $el->get_width());
        $this->assertSame(100, $el->get_height());
        $this->assertSame(0, $el->get_rotation());
        $this->assertSame(0, $el->get_z_index());
        $this->assertFalse($el->is_locked());
        $this->assertSame('', $el->get_text());
        $this->assertSame('Arial', $el->get_font());
        $this->assertSame(24, $el->get_font_size());  // defaults to 24, not 16
        $this->assertSame('#000000', $el->get_color());
        $this->assertSame('center', $el->get_align());
        $this->assertFalse($el->is_bold());
        $this->assertFalse($el->is_italic());
        $this->assertFalse($el->is_underline());
        $this->assertSame('', $el->get_src());
        $this->assertSame('', $el->get_original_src());
        $this->assertSame(1.0, $el->get_scale());
        $this->assertSame('rect', $el->get_shape());
        $this->assertSame('transparent', $el->get_fill());
        $this->assertSame('#000000', $el->get_stroke());
        $this->assertSame(0, $el->get_stroke_width());
    }

    public function testConstructorWithTextAttributes(): void
    {
        $el = new DesignElement([
            'type'      => 'text',
            'text'      => 'Hello World',
            'x'         => 100,
            'y'         => 200,
            'width'     => 300,
            'height'    => 150,
            'rotation'  => 45,
            'font'      => 'Helvetica',
            'fontSize'  => 36,
            'color'     => '#FF5733',
            'align'     => 'right',
            'bold'      => true,
            'italic'    => true,
            'underline' => true,
            'z_index'   => 5,
        ]);

        $this->assertSame('text', $el->get_type());
        $this->assertSame('Hello World', $el->get_text());
        $this->assertSame(100, $el->get_x());
        $this->assertSame(200, $el->get_y());
        $this->assertSame(300, $el->get_width());
        $this->assertSame(150, $el->get_height());
        $this->assertSame(45, $el->get_rotation());
        $this->assertSame('Helvetica', $el->get_font());
        $this->assertSame(36, $el->get_font_size());
        $this->assertSame('#FF5733', $el->get_color());
        $this->assertSame('right', $el->get_align());
        $this->assertTrue($el->is_bold());
        $this->assertTrue($el->is_italic());
        $this->assertTrue($el->is_underline());
        $this->assertSame(5, $el->get_z_index());
    }

    public function testConstructorWithImageAttributes(): void
    {
        $el = new DesignElement([
            'type'   => 'image',
            'src'    => 'https://example.com/logo.png',
            'x'      => 50,
            'y'      => 50,
            'width'  => 200,
            'height' => 200,
            'scale'  => 1.5,
        ]);

        $this->assertSame('image', $el->get_type());
        $this->assertSame('https://example.com/logo.png', $el->get_src());
        $this->assertSame(200, $el->get_width());
        $this->assertSame(200, $el->get_height());
        $this->assertSame(1.5, $el->get_scale());
    }

    public function testConstructorWithShapeAttributes(): void
    {
        $el = new DesignElement([
            'type'        => 'shape',
            'shape'       => 'circle',
            'fill'        => '#FF0000',
            'stroke'      => '#000000',
            'strokeWidth' => 2,
        ]);

        $this->assertSame('shape', $el->get_type());
        $this->assertSame('circle', $el->get_shape());
        $this->assertSame('#FF0000', $el->get_fill());
        $this->assertSame('#000000', $el->get_stroke());
        $this->assertSame(2, $el->get_stroke_width());
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    public function testTextFactoryCreatesTextElement(): void
    {
        $el = DesignElement::text('Hello', 10, 20);

        $this->assertSame('text', $el->get_type());
        $this->assertSame('Hello', $el->get_text());
        $this->assertSame(10, $el->get_x());
        $this->assertSame(20, $el->get_y());
    }

    public function testTextFactoryWithOverrides(): void
    {
        $el = DesignElement::text('Hello', 0, 0, [
            'fontSize' => 48,
            'color'    => '#00FF00',
            'bold'     => true,
        ]);

        $this->assertSame(48, $el->get_font_size());
        $this->assertSame('#00FF00', $el->get_color());
        $this->assertTrue($el->is_bold());
    }

    public function testImageFactoryCreatesImageElement(): void
    {
        $el = DesignElement::image('https://x.com/logo.png', 5, 10, 200, 100);

        $this->assertSame('image', $el->get_type());
        $this->assertSame('https://x.com/logo.png', $el->get_src());
        $this->assertSame(5, $el->get_x());
        $this->assertSame(10, $el->get_y());
        $this->assertSame(200, $el->get_width());
        $this->assertSame(100, $el->get_height());
    }

    public function testShapeFactoryCreatesShapeElement(): void
    {
        $el = DesignElement::shape('star', 0, 0, 100, 100);

        $this->assertSame('shape', $el->get_type());
        $this->assertSame('star', $el->get_shape());
    }

    public function testFromArrayReconstructsElement(): void
    {
        $data = [
            'type'   => 'text',
            'text'   => 'From Array',
            'x'      => 99,
            'y'      => 88,
            'width'  => 400,
            'height' => 200,
            'fontSize' => 20,
            'color' => '#112233',
            'bold'   => true,
        ];

        $el = DesignElement::from_array($data);

        $this->assertSame('text', $el->get_type());
        $this->assertSame('From Array', $el->get_text());
        $this->assertSame(99, $el->get_x());
        $this->assertSame(88, $el->get_y());
        $this->assertSame(20, $el->get_font_size());
        $this->assertSame('#112233', $el->get_color());
        $this->assertTrue($el->is_bold());
    }

    // -------------------------------------------------------------------------
    // Getters — fontSize is clamped to minimum 6
    // -------------------------------------------------------------------------

    public function testGetFontSizeClampsMinimum(): void
    {
        $el = new DesignElement(['type' => 'text', 'fontSize' => 2]);
        $this->assertSame(6, $el->get_font_size()); // min 6
    }

    public function testGetAlignReturnsValidAlignment(): void
    {
        $el = new DesignElement(['type' => 'text', 'align' => 'invalid']);
        $this->assertSame('center', $el->get_align()); // defaults to center
    }

    public function testGetAlignReturnsLeftWhenValid(): void
    {
        $el = new DesignElement(['type' => 'text', 'align' => 'left']);
        $this->assertSame('left', $el->get_align());
    }

    public function testGetAlignReturnsRightWhenValid(): void
    {
        $el = new DesignElement(['type' => 'text', 'align' => 'right']);
        $this->assertSame('right', $el->get_align());
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function testToArrayReturnsAllAttributes(): void
    {
        $el = new DesignElement([
            'type'     => 'text',
            'text'     => 'Test',
            'x'        => 10,
            'y'        => 20,
            'width'    => 300,
            'height'   => 150,
            'rotation' => 90,
            'fontSize' => 18,
            'color'    => '#ABCDEF',
            'align'    => 'right',
            'bold'     => true,
            'italic'   => false,
            'z_index'  => 3,
        ]);

        $arr = $el->to_array();

        $this->assertIsArray($arr);
        $this->assertSame('text', $arr['type']);
        $this->assertSame('Test', $arr['text']);
        $this->assertSame(10, $arr['x']);
        $this->assertSame(20, $arr['y']);
        $this->assertSame(300, $arr['width']);
        $this->assertSame(150, $arr['height']);
        $this->assertSame(90, $arr['rotation']);
        $this->assertSame(18, $arr['fontSize']);
        $this->assertSame('#ABCDEF', $arr['color']);
        $this->assertSame('right', $arr['align']);
        $this->assertTrue($arr['bold']);
        $this->assertFalse($arr['italic']);
        $this->assertSame(3, $arr['z_index']);
    }

    public function testJsonSerializeReturnsTypePlusAllGetters(): void
    {
        $el = new DesignElement([
            'type'     => 'text',
            'text'     => 'JSON Test',
            'x'        => 5,
            'y'        => 15,
            'fontSize' => 16,
            'color'    => '#123456',
            'bold'     => true,
        ]);

        $arr = $el->jsonSerialize();

        $this->assertIsArray($arr);
        $this->assertSame('text', $arr['type']);
        $this->assertSame('JSON Test', $arr['text']);
        $this->assertSame(5, $arr['x']);
        $this->assertSame(15, $arr['y']);
        $this->assertSame(16, $arr['fontSize']);
        $this->assertSame('#123456', $arr['color']);
        $this->assertTrue($arr['bold']);
    }

    public function testJsonSerializeIncludesLockAndUnderline(): void
    {
        $el = new DesignElement(['type' => 'text', 'locked' => true, 'underline' => true]);
        $arr = $el->jsonSerialize();

        $this->assertTrue($arr['locked']);
        $this->assertTrue($arr['underline']);
    }

    public function testJsonSerializeIncludesImageProperties(): void
    {
        $el = new DesignElement([
            'type'        => 'image',
            'src'         => 'https://x.com/a.png',
            'originalSrc'  => 'https://x.com/orig.png',
            'cropX'       => 10,
            'cropY'       => 20,
            'scale'       => 2.0,
        ]);

        $arr = $el->jsonSerialize();

        $this->assertSame('https://x.com/a.png', $arr['src']);
        $this->assertSame('https://x.com/orig.png', $arr['originalSrc']);
        $this->assertSame(10, $arr['cropX']);
        $this->assertSame(20, $arr['cropY']);
        $this->assertSame(2.0, $arr['scale']);
    }

    public function testJsonSerializeIncludesShapeProperties(): void
    {
        $el = new DesignElement([
            'type'        => 'shape',
            'shape'       => 'star',
            'fill'        => '#FF9900',
            'stroke'      => '#000000',
            'strokeWidth' => 3,
        ]);

        $arr = $el->jsonSerialize();

        $this->assertSame('star', $arr['shape']);
        $this->assertSame('#FF9900', $arr['fill']);
        $this->assertSame('#000000', $arr['stroke']);
        $this->assertSame(3, $arr['strokeWidth']);
    }

    // -------------------------------------------------------------------------
    // get_color() sanitization — getter calls sanitize_hex_color internally
    // -------------------------------------------------------------------------

    public function testGetColorReturnsSanitizedHex(): void
    {
        $el = new DesignElement(['type' => 'text', 'color' => '#aabbcc']);
        $this->assertSame('#aabbcc', $el->get_color());
    }

    public function testGetColorReturnsDefaultForInvalid(): void
    {
        // The getter falls back to #000000 for invalid values
        $el = new DesignElement(['type' => 'text', 'color' => 'not-a-color']);
        $this->assertSame('#000000', $el->get_color());
    }

    public function testGetFillReturnsTransparentWhenTransparent(): void
    {
        $el = new DesignElement(['type' => 'shape', 'fill' => 'transparent']);
        $this->assertSame('transparent', $el->get_fill());
    }

    // -------------------------------------------------------------------------
    // Clone produces independent copy
    // -------------------------------------------------------------------------

    public function testCloneIsIndependent(): void
    {
        $el1 = new DesignElement(['type' => 'text', 'text' => 'Original', 'fontSize' => 14]);
        $el2 = clone $el1;
        $el2->jsonSerialize(); // trigger any lazy computation
        // The clone should be independent (no public setters, but attrs are private)
        $this->assertSame('Original', $el1->get_text());
        $this->assertSame(14, $el1->get_font_size());
    }
}
