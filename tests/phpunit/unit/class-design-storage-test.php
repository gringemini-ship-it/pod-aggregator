<?php
/**
 * Unit tests for Design_Storage (CPT persistence).
 *
 * Actual public API:
 *   - save(Design $design): int|WP_Error
 *   - get(int $post_id): Design|WP_Error
 *   - delete(int $post_id): bool|WP_Error
 *   - get_for_product(int $product_id): Design[]
 *   - get_presets(): array
 *   - load_from_post(int $post_id): Design|WP_Error
 *   - save_thumbnail(Design $design, string $thumbnail_url): bool
 *   - register_post_type(): void
 *
 * Constants:
 *   - CPT = 'pod_design'
 *   - META_DESIGN_JSON = '_pod_design_json'
 *   - META_PRODUCT_ID = '_pod_product_id'
 *   - META_PROVIDER = '_pod_provider'
 *   - META_DESIGN_UUID = '_pod_design_uuid'
 *   - META_PRINT_AREA = '_pod_print_area'
 *   - META_THUMBNAIL_URL = '_pod_thumbnail_url'
 *
 * NOTE: The actual class uses private find_by_uuid(). Tests here cover
 * the public API and the serialization contract (Design <-> array).
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\Design_Storage;
use POD_Aggregator\ProductCustomizer\Design;
use POD_Aggregator\ProductCustomizer\DesignElement;

class Design_Storage_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testCptConstant(): void
    {
        $this->assertSame('pod_design', Design_Storage::CPT);
    }

    public function testMetaKeyConstants(): void
    {
        $this->assertSame('_pod_design_json', Design_Storage::META_DESIGN_JSON);
        $this->assertSame('_pod_product_id', Design_Storage::META_PRODUCT_ID);
        $this->assertSame('_pod_provider', Design_Storage::META_PROVIDER);
        $this->assertSame('_pod_design_uuid', Design_Storage::META_DESIGN_UUID);
        $this->assertSame('_pod_print_area', Design_Storage::META_PRINT_AREA);
        $this->assertSame('_pod_thumbnail_url', Design_Storage::META_THUMBNAIL_URL);
    }

    // -------------------------------------------------------------------------
    // save() validation — delegates to Design::validate()
    // -------------------------------------------------------------------------

    public function testSaveRejectsDesignWithMissingProductId(): void
    {
        $storage = new Design_Storage();
        $design = new Design([]); // product_id = 0
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $storage->save($design);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pod_design_invalid', $result->get_error_code());
    }

    public function testSaveRejectsDesignWithInvalidArea(): void
    {
        $storage = new Design_Storage();
        $design = new Design([
            'id'         => 'test-uuid',
            'product_id' => 1,
            'area'       => 'nonexistent_area',
        ]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $storage->save($design);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // -------------------------------------------------------------------------
    // get() validation
    // -------------------------------------------------------------------------

    public function testGetReturnsNullForNonExistentDesign(): void
    {
        $storage = new Design_Storage();
        // get() searches by UUID string; non-existent returns null
        $result = $storage->get('non-existent-uuid');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // delete() returns false for non-existent
    // -------------------------------------------------------------------------

    public function testDeleteReturnsFalseForNonExistentDesign(): void
    {
        $storage = new Design_Storage();
        // delete() searches by UUID string; non-existent returns false
        $result = $storage->delete('non-existent-uuid');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // get_for_product() returns empty array for no results
    // -------------------------------------------------------------------------

    public function testGetForProductReturnsEmptyArrayWhenNoDesigns(): void
    {
        $storage = new Design_Storage();
        $result = $storage->get_for_product(0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // Design serialization roundtrip (core contract)
    // -------------------------------------------------------------------------

    public function testDesignElementRoundtripJsonSerialize(): void
    {
        $original = DesignElement::text('Hello', 100, 200);
        $json = wp_json_encode($original->jsonSerialize());
        $data = json_decode($json, true);
        $restored = DesignElement::from_array($data);

        $this->assertSame($original->get_text(), $restored->get_text());
        $this->assertSame($original->get_x(), $restored->get_x());
        $this->assertSame($original->get_y(), $restored->get_y());
        $this->assertSame($original->get_type(), $restored->get_type());
    }

    public function testDesignRoundtripJsonSerialize(): void
    {
        $design = new Design([
            'id'         => 'storage-uuid-001',
            'area'       => 'back',
            'name'       => 'Test Design',
            'product_id' => 123,
            'provider'   => 'printful',
            'dpi'        => 150,
        ]);
        $design->add_element(DesignElement::text('Line 1', 10, 10));
        $design->add_element(DesignElement::image('https://x.com/logo.png', 50, 50, 100, 100));
        $design->add_element(DesignElement::shape('circle', 0, 0, 50, 50));

        $json = wp_json_encode($design->jsonSerialize());
        $data = json_decode($json, true);
        $restored = Design::from_array($data);

        $this->assertSame('storage-uuid-001', $restored->get_id());
        $this->assertSame('back', $restored->get_area());
        $this->assertSame('Test Design', $restored->get_name());
        $this->assertSame(123, $restored->get_product_id());
        $this->assertSame('printful', $restored->get_provider());
        $this->assertSame(150, $restored->get_dpi());
        $this->assertCount(3, $restored->get_elements());

        $elements = $restored->get_elements();
        $this->assertSame('Line 1', $elements[0]->get_text());
        $this->assertSame('image', $elements[1]->get_type());
        $this->assertSame('https://x.com/logo.png', $elements[1]->get_src());
        $this->assertSame('shape', $elements[2]->get_type());
        $this->assertSame('circle', $elements[2]->get_shape());
    }

    public function testDesignToJsonRoundtrip(): void
    {
        $design = new Design(['product_id' => 999, 'name' => 'JSON Roundtrip']);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $restored = Design::from_json($design->to_json());

        $this->assertSame(999, $restored->get_product_id());
        $this->assertSame('Hello', $restored->get_elements()[0]->get_text());
    }

    public function testDesignEmptyElementsArrayRoundtrip(): void
    {
        $data = [
            'id'         => 'empty-uuid',
            'product_id' => 1,
            'elements'   => [],
        ];

        $design = Design::from_array($data);
        $this->assertCount(0, $design);

        $json = $design->to_json();
        $restored = Design::from_json($json);
        $this->assertCount(0, $restored);
    }
}
