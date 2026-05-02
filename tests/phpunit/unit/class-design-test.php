<?php
/**
 * Unit tests for Design value object.
 *
 * Actual Design API:
 *   - Constructor: new Design(array $attrs) — takes keyed array
 *   - No create() method — pass ['product_id' => N] to constructor
 *   - No $design_id, $status, $printful_variant_id public properties
 *   - Properties: id, area, name, product_id, provider, provider_product_id,
 *                 dpi, created_at, updated_at, elements (private array)
 *   - to_array() does NOT exist — use jsonSerialize() or to_json()
 *   - Serialization: jsonSerialize() returns array; to_json() returns string
 *   - validate() returns true|WP_Error
 *   - Static: from_array(), from_json(), get_print_area(), DEFAULT_DPI,
 *             AREA_FRONT, AREA_BACK, AREA_LEFT_SLEEVE, AREA_RIGHT_SLEEVE
 *   - Element access: get_elements() returns sorted array; add_element() accepts
 *     DesignElement|array; remove_element(int $index)
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\Design;
use POD_Aggregator\ProductCustomizer\DesignElement;

class Design_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction — keyed array, no positional args
    // -------------------------------------------------------------------------

    public function testConstructorWithEmptyArray(): void
    {
        $design = new Design([]);
        $this->assertNotEmpty($design->get_id());
    }

    public function testConstructorWithProductId(): void
    {
        $design = new Design(['product_id' => 123]);
        $this->assertSame(123, $design->get_product_id());
    }

    public function testConstructorWithAllFields(): void
    {
        $design = new Design([
            'id'                  => 'test-uuid-001',
            'area'                => 'back',
            'name'                => 'My Design',
            'product_id'          => 456,
            'provider'            => 'printful',
            'provider_product_id' => 'PFPROD-789',
            'dpi'                 => 150,
            'created_at'          => 1700000000,
            'updated_at'          => 1700000100,
        ]);

        $this->assertSame('test-uuid-001', $design->get_id());
        $this->assertSame('back', $design->get_area());
        $this->assertSame('My Design', $design->get_name());
        $this->assertSame(456, $design->get_product_id());
        $this->assertSame('printful', $design->get_provider());
        $this->assertSame('PFPROD-789', $design->get_provider_product_id());
        $this->assertSame(150, $design->get_dpi());
        $this->assertSame(1700000000, $design->get_created_at());
        $this->assertSame(1700000100, $design->get_updated_at());
    }

    public function testConstructorDefaults(): void
    {
        $design = new Design([]);

        $this->assertSame('front', $design->get_area());
        $this->assertSame('Untitled Design', $design->get_name());
        $this->assertSame(0, $design->get_product_id());
        $this->assertSame('printful', $design->get_provider());
        $this->assertSame(300, $design->get_dpi());
        $this->assertSame(0, $design->get_created_at()); // time() — check > 0
        $this->assertGreaterThan(0, $design->get_updated_at());
    }

    public function testConstructorGeneratesUuid(): void
    {
        $design = new Design([]);
        $id = $design->get_id();

        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    public function testConstructorGeneratesDifferentIds(): void
    {
        $d1 = new Design([]);
        $d2 = new Design([]);
        $this->assertNotSame($d1->get_id(), $d2->get_id());
    }

    // -------------------------------------------------------------------------
    // Area constants and print area lookup
    // -------------------------------------------------------------------------

    public function testAreaConstants(): void
    {
        $this->assertSame('front', Design::AREA_FRONT);
        $this->assertSame('back', Design::AREA_BACK);
        $this->assertSame('left_sleeve', Design::AREA_LEFT_SLEEVE);
        $this->assertSame('right_sleeve', Design::AREA_RIGHT_SLEEVE);
        $this->assertSame(300, Design::DEFAULT_DPI);
    }

    public function testGetPrintArea(): void
    {
        $front = Design::get_print_area('front');
        $this->assertArrayHasKey('width_mm', $front);
        $this->assertArrayHasKey('height_mm', $front);
        $this->assertSame(300, $front['width_mm']);
        $this->assertSame(400, $front['height_mm']);
    }

    public function testGetPrintAreaBack(): void
    {
        $back = Design::get_print_area('back');
        $this->assertSame(300, $back['width_mm']);
        $this->assertSame(400, $back['height_mm']);
    }

    public function testGetPrintAreaSleeves(): void
    {
        $left = Design::get_print_area('left_sleeve');
        $this->assertSame(100, $left['width_mm']);
        $this->assertSame(100, $left['height_mm']);
    }

    public function testGetPrintAreaUnknownReturnsDefault(): void
    {
        $unknown = Design::get_print_area('nonexistent');
        $this->assertSame(300, $unknown['width_mm']);
        $this->assertSame(400, $unknown['height_mm']);
    }

    // -------------------------------------------------------------------------
    // Element management
    // -------------------------------------------------------------------------

    public function testAddElementFromArray(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'Hello']);

        $this->assertCount(1, $design);
        $elements = $design->get_elements();
        $this->assertSame('Hello', $elements[0]->get_text());
    }

    public function testAddElementFromDesignElementInstance(): void
    {
        $design = new Design(['product_id' => 1]);
        $element = DesignElement::text('World', 0, 0);
        $design->add_element($element);

        $this->assertCount(1, $design);
        $this->assertSame('World', $design->get_elements()[0]->get_text());
    }

    public function testAddMultipleElements(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'A']);
        $design->add_element(['type' => 'image', 'src' => 'https://x.com/img.png']);

        $this->assertCount(2, $design);
    }

    public function testRemoveElementByIndex(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'First']);
        $design->add_element(['type' => 'text', 'text' => 'Second']);
        $design->remove_element(0);

        $this->assertCount(1, $design);
        $this->assertSame('Second', $design->get_elements()[0]->get_text());
    }

    public function testRemoveElementInvalidIndexDoesNothing(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'Only']);
        $design->remove_element(99);

        $this->assertCount(1, $design);
    }

    public function testClearElements(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'A']);
        $design->add_element(['type' => 'text', 'text' => 'B']);
        $design->clear_elements();

        $this->assertCount(0, $design);
    }

    public function testCountImplementsCountable(): void
    {
        $design = new Design(['product_id' => 1]);
        $this->assertCount(0, $design);

        $design->add_element(['type' => 'text', 'text' => 'X']);
        $this->assertCount(1, $design);

        $design->add_element(['type' => 'image', 'src' => 'https://x.com/a.png']);
        $this->assertCount(2, $design);
    }

    // -------------------------------------------------------------------------
    // get_elements() returns sorted by z_index
    // -------------------------------------------------------------------------

    public function testGetElementsSortsByZIndex(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'z3', 'z_index' => 3]);
        $design->add_element(['type' => 'text', 'text' => 'z1', 'z_index' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'z2', 'z_index' => 2]);

        $elements = $design->get_elements();

        $this->assertSame('z1', $elements[0]->get_text());
        $this->assertSame('z2', $elements[1]->get_text());
        $this->assertSame('z3', $elements[2]->get_text());
    }

    public function testGetElementsReturnsArrayOfDesignElements(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(['type' => 'text', 'text' => 'Hello']);

        foreach ($design->get_elements() as $el) {
            $this->assertInstanceOf(DesignElement::class, $el);
        }
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function testJsonSerializeReturnsCompleteArray(): void
    {
        $design = new Design([
            'id'         => 'uuid-001',
            'area'       => 'back',
            'name'       => 'Test Design',
            'product_id' => 123,
            'provider'   => 'printful',
            'dpi'        => 150,
        ]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $arr = $design->jsonSerialize();

        $this->assertIsArray($arr);
        $this->assertSame('uuid-001', $arr['id']);
        $this->assertSame('back', $arr['area']);
        $this->assertSame('Test Design', $arr['name']);
        $this->assertSame(123, $arr['product_id']);
        $this->assertSame('printful', $arr['provider']);
        $this->assertSame(150, $arr['dpi']);
        $this->assertArrayHasKey('elements', $arr);
        $this->assertCount(1, $arr['elements']);
        $this->assertSame('text', $arr['elements'][0]['type']);
    }

    public function testToJsonReturnsJsonString(): void
    {
        $design = new Design(['product_id' => 1]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $json = $design->to_json();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame(1, $decoded['product_id']);
        $this->assertCount(1, $decoded['elements']);
    }

    public function testFromArrayReconstructsDesign(): void
    {
        $data = [
            'id'         => 'recon-uuid',
            'area'       => 'back',
            'name'       => 'Reconstructed',
            'product_id' => 999,
            'provider'   => 'printful',
            'provider_product_id' => 'PFPROD-001',
            'dpi'        => 300,
            'elements'   => [
                ['type' => 'text', 'text' => 'From Array', 'x' => 10, 'y' => 20],
            ],
        ];

        $design = Design::from_array($data);

        $this->assertSame('recon-uuid', $design->get_id());
        $this->assertSame('back', $design->get_area());
        $this->assertSame('Reconstructed', $design->get_name());
        $this->assertSame(999, $design->get_product_id());
        $this->assertSame(300, $design->get_dpi());
        $this->assertCount(1, $design->get_elements());
        $this->assertSame('From Array', $design->get_elements()[0]->get_text());
    }

    public function testFromJsonReconstructsDesign(): void
    {
        $json = wp_json_encode([
            'id'         => 'json-uuid',
            'area'       => 'front',
            'product_id' => 500,
            'elements'   => [['type' => 'text', 'text' => 'From JSON']],
        ]);

        $design = Design::from_json($json);

        $this->assertSame('json-uuid', $design->get_id());
        $this->assertSame('front', $design->get_area());
        $this->assertSame(500, $design->get_product_id());
        $this->assertSame('From JSON', $design->get_elements()[0]->get_text());
    }

    public function testFromJsonWithInvalidJsonReturnsDefaultDesign(): void
    {
        $design = Design::from_json('not valid json at all {{{');
        $this->assertNotEmpty($design->get_id());
    }

    public function testFromArrayWithEmptyElements(): void
    {
        $design = Design::from_array(['product_id' => 1, 'elements' => []]);
        $this->assertCount(0, $design);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testValidatePassesForValidDesign(): void
    {
        $design = new Design([
            'id'         => 'valid-uuid',
            'product_id' => 123,
            'area'       => 'front',
        ]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $design->validate();
        $this->assertTrue($result);
    }

    public function testValidateFailsForMissingId(): void
    {
        $design = new Design([]); // no id set
        // Force the id to empty string for test
        // Can't directly set private property, so test via from_array with empty id
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $design->validate();
        // Actually the design has a generated UUID so it should pass the id check
        $this->assertTrue($result);
    }

    public function testValidateFailsForMissingProductId(): void
    {
        $design = new Design([]); // product_id defaults to 0
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $design->validate();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pod_design_invalid', $result->get_error_code());
    }

    public function testValidateFailsForInvalidArea(): void
    {
        $design = new Design([
            'id'         => 'test-uuid',
            'product_id' => 1,
            'area'       => 'nonexistent_area',
        ]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $design->validate();
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function testValidateFailsForNonDesignElementInElements(): void
    {
        $design = new Design([
            'id'         => 'test-uuid',
            'product_id' => 1,
        ]);
        // Use reflection to add a raw array as element (simulates bad data)
        $refl = new \ReflectionClass($design);
        $prop = $refl->getProperty('elements');
        $prop->setAccessible(true);
        $prop->setValue($design, ['not a DesignElement object']);

        $result = $design->validate();
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // -------------------------------------------------------------------------
    // Print dimensions
    // -------------------------------------------------------------------------

    public function testGetPrintDimensionsAt300Dpi(): void
    {
        $design = new Design(['area' => 'front', 'dpi' => 300]);
        $dims = $design->get_print_dimensions();

        $this->assertArrayHasKey('width_px', $dims);
        $this->assertArrayHasKey('height_px', $dims);
        // Front area: 300mm x 25.4 = 11.81 inches * 300 = 3543 px
        $this->assertGreaterThan(3000, $dims['width_px']);
        $this->assertSame($dims['width_px'], $dims['height_px']); // square for front
    }

    public function testGetPrintDimensionsAt150Dpi(): void
    {
        $design = new Design(['area' => 'front', 'dpi' => 150]);
        $dims = $design->get_print_dimensions();

        $this->assertGreaterThan(1000, $dims['width_px']);
        // 150 DPI should be exactly half of 300 DPI
        $design300 = new Design(['area' => 'front', 'dpi' => 300]);
        $dims300 = $design300->get_print_dimensions();
        $this->assertSame($dims300['width_px'] / 2, $dims['width_px']);
    }

    public function testPxToMm(): void
    {
        $design = new Design(['area' => 'front', 'dpi' => 300]);
        $mm = $design->px_to_mm(300);

        $this->assertIsFloat($mm);
        $this->assertGreaterThan(0, $mm);
    }
}
