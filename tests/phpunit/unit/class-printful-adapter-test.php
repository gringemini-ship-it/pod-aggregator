<?php
/**
 * Unit tests for Printful Adapter.
 *
 * Actual public API:
 *   - __construct(): reads API key from site_option
 *   - is_configured(): bool — returns true if API key is set
 *   - get_products(): array|WP_Error
 *   - get_product(int $id): array|WP_Error
 *   - get_product_variants(int $product_id): array|WP_Error
 *   - calculate_price(array $variant, float $retail_price = 0.0): float
 *   - submit_order(int $order_id, array $order_data): array|WP_Error
 *   - get_mockups(int $product_id): array|WP_Error
 *   - get_order_status(string $external_order_id): array|WP_Error
 *   - get_tracking_url(string $tracking_number): string
 *   - get_slug(): string  (returns 'printful')
 *   - get_name(): string  (returns 'Printful')
 *
 * NOTE: No public normalize_product() or normalize_order() — those are private.
 * No public get_api_key() — API key is read-only from options.
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\Provider\Printful_Adapter;

class Printful_Adapter_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Provider identity
    // -------------------------------------------------------------------------

    public function testGetNameReturnsPrintful(): void
    {
        $adapter = new Printful_Adapter();
        $this->assertSame('Printful', $adapter->get_name());
    }

    public function testGetSlugReturnsPrintful(): void
    {
        $adapter = new Printful_Adapter();
        $this->assertSame('printful', $adapter->get_slug());
    }

    // -------------------------------------------------------------------------
    // is_configured()
    // -------------------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWhenApiKeySet(): void
    {
        update_site_option('pod_aggregator_settings', ['printful_api_key' => 'TEST_KEY_123']);
        $adapter = new Printful_Adapter();

        $this->assertTrue($adapter->is_configured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        update_site_option('pod_aggregator_settings', ['printful_api_key' => '']);
        $adapter = new Printful_Adapter();

        $this->assertFalse($adapter->is_configured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyNotSet(): void
    {
        update_site_option('pod_aggregator_settings', []);
        $adapter = new Printful_Adapter();

        $this->assertFalse($adapter->is_configured());
    }

    // -------------------------------------------------------------------------
    // calculate_price()
    // -------------------------------------------------------------------------

    public function testCalculatePriceAppliesDefaultMarkup(): void
    {
        $adapter = new Printful_Adapter();

        // Cost 100.00 * 1.30 = 130.00
        $result = $adapter->calculate_price(['cost' => 100.00]);

        $this->assertSame(130.00, $result);
    }

    public function testCalculatePriceWithRetailPriceOverride(): void
    {
        $adapter = new Printful_Adapter();

        // Retail price override returns the retail price directly.
        $result = $adapter->calculate_price(['cost' => 50.00], 75.00);

        $this->assertSame(75.00, $result);
    }

    public function testCalculatePriceWithZeroCost(): void
    {
        $adapter = new Printful_Adapter();

        $result = $adapter->calculate_price(['cost' => 0]);

        $this->assertSame(0.0, $result);
    }

    public function testCalculatePriceWithFractionalCost(): void
    {
        $adapter = new Printful_Adapter();

        // 19.99 * 1.30 = 25.987 → rounds to 25.99
        $result = $adapter->calculate_price(['cost' => 19.99]);

        $this->assertEqualsWithDelta(25.99, $result, 0.01);
    }

    // -------------------------------------------------------------------------
    // get_tracking_url()
    // -------------------------------------------------------------------------

    public function testGetTrackingUrlReturnsString(): void
    {
        $adapter = new Printful_Adapter();

        $url = $adapter->get_tracking_url('940011189922331234567');

        $this->assertIsString($url);
        $this->assertStringContainsString('https://', $url);
    }

    public function testGetTrackingUrlContainsTrackingNumber(): void
    {
        $adapter = new Printful_Adapter();

        $tracking = 'TRACK123';
        $url = $adapter->get_tracking_url($tracking);

        $this->assertStringContainsString($tracking, $url);
    }

    // -------------------------------------------------------------------------
    // get_products() — mocked HTTP
    // -------------------------------------------------------------------------

    public function testGetProductsReturnsArray(): void
    {
        $adapter = new Printful_Adapter();
        $result = $adapter->get_products();

        // In mocked environment, wp_remote_get returns a valid response body
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // submit_order() — return type in mocked environment
    // -------------------------------------------------------------------------

    public function testSubmitOrderReturnsArray(): void
    {
        update_site_option('pod_aggregator_settings', ['printful_api_key' => 'TEST_KEY']);
        $adapter = new Printful_Adapter();

        $result = $adapter->submit_order([
            'items' => [
                ['variant_id' => 123, 'qty' => 1, 'price' => '15.00'],
            ],
            'shipping' => 'STANDARD',
            'woo_order_id' => 1,
            'shipping_address' => [
                'name'     => 'Test User',
                'address1' => '123 Main St',
                'city'     => 'Anytown',
                'state'    => 'CA',
                'country'  => 'US',
                'zip'      => '90210',
                'phone'    => '555-0100',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->assertIsArray($result);
    }
}
