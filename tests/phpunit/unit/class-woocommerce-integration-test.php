<?php
/**
 * Unit tests for WooCommerce Integration.
 *
 * Actual public API:
 *   - product_data_tab(array $tabs): array
 *   - product_data_panel(): void  (no params — uses global $post)
 *   - save_product_meta(int $post_id): void
 *   - add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
 *   - display_cart_item_data(array $data, array $item): array
 *   - create_order_line_item(\WC_Order_Item_Product $item, string $cart_item_key, array $values): void
 *   - forward_order_to_provider(int $order_id): void
 *   - add_order_actions(int $order_id): void
 *   - resend_order_to_provider(int $order_id): void
 *
 * Actual constants:
 *   META_POD_ENABLED = '_pod_enabled'
 *   META_PROVIDER = '_pod_provider'
 *   META_VARIANT_ID = '_pod_variant_id'
 *   META_DESIGN_DATA = '_pod_design_data'
 *   META_DESIGN_UUID = '_pod_design_uuid'
 *   META_DESIGN_THUMB = '_pod_design_thumb'
 *   META_DESIGN_NAME = '_pod_design_name'
 *   META_PRINT_FILE_URL = '_pod_print_file_url'
 *   META_PRINT_AREA = '_pod_print_area'
 *   META_PRINT_WIDTH_MM = '_pod_print_width_mm'
 *   META_PRINT_HEIGHT_MM = '_pod_print_height_mm'
 *   META_PRINT_DPI = '_pod_print_dpi'
 *
 * Note: Cart item data is stored under $cart_item_data['pod']['enabled'],
 * ['pod']['provider'], ['pod']['variant_id'], ['pod']['design_data'],
 * ['pod']['design_uuid'], ['pod']['design_thumb'], ['pod']['design_name'], ['pod']['print_area']
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\WooCommerce\Integration;

class WooCommerce_Integration_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants — actual meta key names
    // -------------------------------------------------------------------------

    public function testMetaKeyConstantsAreDefined(): void
    {
        $this->assertSame('_pod_enabled', Integration::META_POD_ENABLED);
        $this->assertSame('_pod_provider', Integration::META_PROVIDER);
        $this->assertSame('_pod_variant_id', Integration::META_VARIANT_ID);
        $this->assertSame('_pod_design_data', Integration::META_DESIGN_DATA);
        $this->assertSame('_pod_design_uuid', Integration::META_DESIGN_UUID);
        $this->assertSame('_pod_design_thumb', Integration::META_DESIGN_THUMB);
        $this->assertSame('_pod_design_name', Integration::META_DESIGN_NAME);
        $this->assertSame('_pod_print_file_url', Integration::META_PRINT_FILE_URL);
        $this->assertSame('_pod_print_area', Integration::META_PRINT_AREA);
        $this->assertSame('_pod_print_width_mm', Integration::META_PRINT_WIDTH_MM);
        $this->assertSame('_pod_print_height_mm', Integration::META_PRINT_HEIGHT_MM);
        $this->assertSame('_pod_print_dpi', Integration::META_PRINT_DPI);
    }

    // -------------------------------------------------------------------------
    // Public methods exist
    // -------------------------------------------------------------------------

    public function testProductDataTabExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'product_data_tab'));
    }

    public function testProductDataPanelExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'product_data_panel'));
    }

    public function testSaveProductMetaExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'save_product_meta'));
    }

    public function testAddCartItemDataExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'add_cart_item_data'));
    }

    public function testDisplayCartItemDataExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'display_cart_item_data'));
    }

    public function testCreateOrderLineItemExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'create_order_line_item'));
    }

    public function testForwardOrderToProviderExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'forward_order_to_provider'));
    }

    public function testAddOrderActionsExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'add_order_actions'));
    }

    public function testResendOrderToProviderExists(): void
    {
        $integration = new Integration();
        $this->assertTrue(method_exists($integration, 'resend_order_to_provider'));
    }

    // -------------------------------------------------------------------------
    // product_data_tab() — returns modified tabs array
    // -------------------------------------------------------------------------

    public function testProductDataTabAddsPodTab(): void
    {
        $integration = new Integration();
        $tabs = [];
        $result = $integration->product_data_tab($tabs);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pod_product', $result);
        $this->assertSame('pod_product_data', $result['pod_product']['target']);
        $this->assertSame('POD Product', $result['pod_product']['label']);
    }

    public function testProductDataTabPreservesOtherTabs(): void
    {
        $integration = new Integration();
        $tabs = [
            'general' => ['label' => 'General'],
            'shipping' => ['label' => 'Shipping'],
        ];

        $result = $integration->product_data_tab($tabs);

        $this->assertArrayHasKey('general', $result);
        $this->assertArrayHasKey('shipping', $result);
        $this->assertArrayHasKey('pod_product', $result);
    }

    // -------------------------------------------------------------------------
    // add_cart_item_data() — adds data to cart item
    // -------------------------------------------------------------------------

    public function testAddCartItemDataAddsPodArray(): void
    {
        $integration = new Integration();

        $result = $integration->add_cart_item_data([], 10, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pod', $result);
    }

    public function testAddCartItemDataPodArrayHasEnabledKey(): void
    {
        $integration = new Integration();

        $result = $integration->add_cart_item_data([], 10, 0);

        $this->assertArrayHasKey('enabled', $result['pod']);
    }

    public function testAddCartItemDataPodArrayHasProviderKey(): void
    {
        $integration = new Integration();

        $result = $integration->add_cart_item_data([], 10, 0);

        $this->assertArrayHasKey('provider', $result['pod']);
    }

    // -------------------------------------------------------------------------
    // display_cart_item_data() — returns modified data array
    // -------------------------------------------------------------------------

    public function testDisplayCartItemDataReturnsArray(): void
    {
        $integration = new Integration();

        $result = $integration->display_cart_item_data([], ['product_id' => 10]);

        $this->assertIsArray($result);
    }

    public function testDisplayCartItemDataReturnsUnchangedWhenNoPod(): void
    {
        $integration = new Integration();
        $original = ['key' => 'value'];

        $result = $integration->display_cart_item_data($original, ['product_id' => 10]);

        // When no 'pod' key in item, data is returned unchanged
        $this->assertSame($original, $result);
    }

    public function testDisplayCartItemDataAddsDesignDataWhenPresent(): void
    {
        $integration = new Integration();

        $result = $integration->display_cart_item_data([], [
            'product_id' => 10,
            'pod' => [
                'design_name' => 'My Design',
                'design_thumb' => 'https://x.com/thumb.png',
            ],
        ]);

        $this->assertNotEmpty($result);
    }

    // -------------------------------------------------------------------------
    // Method signatures
    // -------------------------------------------------------------------------

    public function testSaveProductMetaAcceptsIntParameter(): void
    {
        $refl = new \ReflectionMethod(Integration::class, 'save_product_meta');
        $params = $refl->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('post_id', $params[0]->getName());
    }

    public function testAddCartItemDataAcceptsThreeParameters(): void
    {
        $refl = new \ReflectionMethod(Integration::class, 'add_cart_item_data');
        $params = $refl->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('cart_item_data', $params[0]->getName());
        $this->assertSame('product_id', $params[1]->getName());
        $this->assertSame('variation_id', $params[2]->getName());
    }

    public function testDisplayCartItemDataAcceptsTwoArrayParameters(): void
    {
        $refl = new \ReflectionMethod(Integration::class, 'display_cart_item_data');
        $params = $refl->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('data', $params[0]->getName());
        $this->assertSame('item', $params[1]->getName());
    }
}
