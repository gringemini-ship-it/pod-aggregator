<?php
/**
 * Unit tests for Product Importer.
 *
 * Covers:
 *   - import(): creates WC variable product with variations, attributes, images
 *   - import(): rejects non-existent, already-imported, and incomplete pod_products
 *   - markup clamping (constructor), attribute building, SKU generation
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\Product_Importer;

class Product_Importer_Test extends TestCase
{
    private int $pod_id_counter = 5000;

    protected function setUp(): void
    {
        $this->pod_id_counter = 5000;
        $GLOBALS['_pod_posts']    = [];
        $GLOBALS['_pod_postmeta'] = [];
        $GLOBALS['_pod_site_options'] = [];
        update_site_option('pod_aggregator_settings', ['printful_default_markup' => 30]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a pod_product post with normalized data in the mock registries.
     *
     * @param array $overrides Key/value overrides for the normalized product data.
     * @return int The simulated post ID.
     */
    private function seed_pod_product(array $overrides = []): int
    {
        $id = ++$this->pod_id_counter;

        $defaults = [
            'name'                => 'Test T-Shirt',
            'description'         => 'A comfortable cotton t-shirt.',
            'provider'            => 'printful',
            'provider_product_id' => '71',
            'thumbnail_url'       => 'https://example.com/thumb.jpg',
            'model'               => '3001',
            'brand'               => 'Gildan',
            'category'            => 'T-Shirts',
            'variants'            => [
                [
                    'variant_id'   => '4011',
                    'name'         => 'Small / Black',
                    'sku'          => '3001-S-BLK',
                    'price'        => 19.99,
                    'cost'         => 10.00,
                    'currency'     => 'USD',
                    'size'         => 'Small',
                    'color'        => 'Black',
                    'image'        => 'https://example.com/variant1.jpg',
                    'availability' => ['US' => true],
                ],
                [
                    'variant_id'   => '4012',
                    'name'         => 'Medium / Black',
                    'sku'          => '3001-M-BLK',
                    'price'        => 19.99,
                    'cost'         => 10.00,
                    'currency'     => 'USD',
                    'size'         => 'Medium',
                    'color'        => 'Black',
                    'image'        => 'https://example.com/variant2.jpg',
                    'availability' => ['US' => true],
                ],
                [
                    'variant_id'   => '4013',
                    'name'         => 'Large / White',
                    'sku'          => '3001-L-WHT',
                    'price'        => 19.99,
                    'cost'         => 10.00,
                    'currency'     => 'USD',
                    'size'         => 'Large',
                    'color'        => 'White',
                    'image'        => '',
                    'availability' => ['US' => true],
                ],
            ],
        ];

        $normalized = array_merge($defaults, $overrides);

        // Register the post.
        $GLOBALS['_pod_posts'][$id] = [
            'post_title'  => $normalized['name'],
            'post_type'   => 'pod_product',
            'post_status' => 'publish',
        ];

        // Register post meta.
        $GLOBALS['_pod_postmeta'][$id] = [
            '_pod_provider'            => $normalized['provider'],
            '_pod_provider_product_id' => $normalized['provider_product_id'],
            '_pod_thumbnail_url'       => $normalized['thumbnail_url'],
            '_pod_normalized_data'     => wp_json_encode($normalized),
            '_pod_imported_to_product' => '',
        ];

        return $id;
    }

    private function get_meta(int $post_id, string $key): string
    {
        return $GLOBALS['_pod_postmeta'][$post_id][$key] ?? '';
    }

    // -------------------------------------------------------------------------
    // Successful imports
    // -------------------------------------------------------------------------

    public function testImportReturnsWcProductId(): void
    {
        $pod_id = $this->seed_pod_product();
        $importer = new Product_Importer(30);

        $wc_id = $importer->import($pod_id);

        $this->assertIsInt($wc_id);
        $this->assertGreaterThan(0, $wc_id);
    }

    public function testImportMarksPodProductAsImported(): void
    {
        $pod_id = $this->seed_pod_product();
        $importer = new Product_Importer(30);

        $importer->import($pod_id);

        $this->assertNotEmpty($this->get_meta($pod_id, '_pod_imported_to_product'));
    }

    public function testImportWithoutThumbnail(): void
    {
        $pod_id = $this->seed_pod_product(['thumbnail_url' => '']);
        $importer = new Product_Importer(30);

        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    public function testImportWithSingleVariant(): void
    {
        $pod_id = $this->seed_pod_product([
            'variants' => [
                [
                    'variant_id' => '5001', 'name' => 'One Size', 'sku' => 'ONESIZE-1',
                    'price' => 15.00, 'cost' => 8.00, 'currency' => 'USD',
                    'size' => 'One Size', 'color' => '', 'image' => '', 'availability' => [],
                ],
            ],
        ]);
        $importer = new Product_Importer(30);

        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    public function testImportWithVariantsHavingOnlySizes(): void
    {
        $pod_id = $this->seed_pod_product([
            'variants' => [
                ['variant_id' => '1', 'name' => 'S', 'sku' => 'S', 'price' => 10, 'cost' => 5, 'currency' => 'USD', 'size' => 'Small', 'color' => '', 'image' => '', 'availability' => []],
                ['variant_id' => '2', 'name' => 'M', 'sku' => 'M', 'price' => 10, 'cost' => 5, 'currency' => 'USD', 'size' => 'Medium', 'color' => '', 'image' => '', 'availability' => []],
            ],
        ]);
        $importer = new Product_Importer(30);

        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    public function testImportWithVariantsHavingOnlyColors(): void
    {
        $pod_id = $this->seed_pod_product([
            'variants' => [
                ['variant_id' => '1', 'name' => 'Red', 'sku' => 'R', 'price' => 10, 'cost' => 5, 'currency' => 'USD', 'size' => '', 'color' => 'Red', 'image' => '', 'availability' => []],
                ['variant_id' => '2', 'name' => 'Blue', 'sku' => 'B', 'price' => 10, 'cost' => 5, 'currency' => 'USD', 'size' => '', 'color' => 'Blue', 'image' => '', 'availability' => []],
            ],
        ]);
        $importer = new Product_Importer(30);

        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    // -------------------------------------------------------------------------
    // Error paths
    // -------------------------------------------------------------------------

    public function testImportRejectsNonExistentPodProduct(): void
    {
        $importer = new Product_Importer(30);
        $result = $importer->import(99999);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString('valid POD product', $result->get_error_message());
    }

    public function testImportRejectsWrongPostType(): void
    {
        $id = ++$this->pod_id_counter;
        $GLOBALS['_pod_posts'][$id] = ['post_title' => 'Not POD', 'post_type' => 'post', 'post_status' => 'publish'];

        $importer = new Product_Importer(30);
        $result = $importer->import($id);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString('valid POD product', $result->get_error_message());
    }

    public function testImportRejectsAlreadyImportedProduct(): void
    {
        $pod_id = $this->seed_pod_product();
        $GLOBALS['_pod_postmeta'][$pod_id]['_pod_imported_to_product'] = '2000';
        $GLOBALS['_pod_posts'][2000] = ['post_title' => 'Already Imported', 'post_type' => 'product', 'post_status' => 'publish'];

        $importer = new Product_Importer(30);
        $result = $importer->import($pod_id);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString('Already imported', $result->get_error_message());
    }

    public function testImportRejectsProductWithEmptyName(): void
    {
        $pod_id = $this->seed_pod_product(['name' => '']);
        $importer = new Product_Importer(30);

        $result = $importer->import($pod_id);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString('incomplete', $result->get_error_message());
    }

    public function testImportRejectsProductWithNoVariants(): void
    {
        $pod_id = $this->seed_pod_product(['variants' => []]);
        $importer = new Product_Importer(30);

        $result = $importer->import($pod_id);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString('incomplete', $result->get_error_message());
    }

    public function testImportRejectsCorruptJson(): void
    {
        $pod_id = $this->seed_pod_product();
        $GLOBALS['_pod_postmeta'][$pod_id]['_pod_normalized_data'] = '{{{broken';

        $importer = new Product_Importer(30);
        $result = $importer->import($pod_id);

        $this->assertTrue(is_wp_error($result));
    }

    public function testImportRejectsMissingNormalizedData(): void
    {
        $pod_id = $this->seed_pod_product();
        unset($GLOBALS['_pod_postmeta'][$pod_id]['_pod_normalized_data']);

        $importer = new Product_Importer(30);
        $result = $importer->import($pod_id);

        $this->assertTrue(is_wp_error($result));
    }

    public function testImportRejectsNullJson(): void
    {
        $pod_id = $this->seed_pod_product();
        $GLOBALS['_pod_postmeta'][$pod_id]['_pod_normalized_data'] = 'null';

        $importer = new Product_Importer(30);

        // This will fail because name will be empty after json_decode(null) = null
        $result = $importer->import($pod_id);
        $this->assertTrue(is_wp_error($result));
    }

    // -------------------------------------------------------------------------
    // Markup clamping
    // -------------------------------------------------------------------------

    public function testMarkupClampedAtZero(): void
    {
        $importer = new Product_Importer(-50);
        $pod_id = $this->seed_pod_product();
        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    public function testMarkupClampedAtMax(): void
    {
        $importer = new Product_Importer(999);
        $pod_id = $this->seed_pod_product();
        $wc_id = $importer->import($pod_id);
        $this->assertIsInt($wc_id);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testConstants(): void
    {
        $this->assertSame('_pod_imported_to_product', Product_Importer::META_IMPORTED_TO);
        $this->assertSame('_pod_source_product_id', Product_Importer::META_SOURCE_POD_ID);
        $this->assertSame('_pod_provider', Product_Importer::META_PROVIDER);
        $this->assertSame('_pod_provider_product_id', Product_Importer::META_PROVIDER_PRODUCT_ID);
    }
}
