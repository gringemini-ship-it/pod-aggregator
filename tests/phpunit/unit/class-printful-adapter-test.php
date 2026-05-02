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
 *   - calculate_price(float $base_cost, float $markup_percent): float
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
        update_site_option('pod_aggregator_printful_api_key', 'TEST_KEY_123');
        $adapter = new Printful_Adapter();

        $this->assertTrue($adapter->is_configured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        update_site_option('pod_aggregator_printful_api_key', '');
        $adapter = new Printful_Adapter();

        $this->assertFalse($adapter->is_configured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyNotSet(): void
    {
        delete_site_option('pod_aggregator_printful_api_key');
        $adapter = new Printful_Adapter();

        $this->assertFalse($adapter->is_configured());
    }

    // -------------------------------------------------------------------------
    // calculate_price()
    // -------------------------------------------------------------------------

    public function testCalculatePriceAppliesMarkup(): void
    {
        $adapter = new Printful_Adapter();

        $result = $adapter->calculate_price(100.00, 20.0);

        $this->assertSame(120.00, $result);
    }

    public function testCalculatePriceWithZeroMarkup(): void
    {
        $adapter = new Printful_Adapter();

        $result = $adapter->calculate_price(50.00, 0.0);

        $this->assertSame(50.00, $result);
    }

    public function testCalculatePriceWithHighMarkup(): void
    {
        $adapter = new Printful_Adapter();

        $result = $adapter->calculate_price(100.00, 50.0);

        $this->assertSame(150.00, $result);
    }

    public function testCalculatePriceWithFractionalBaseCost(): void
    {
        $adapter = new Printful_Adapter();

        $result = $adapter->calculate_price(19.99, 15.0);

        // 19.99 * 1.15 = 22.9885 → rounds to 22.99
        $this->assertEqualsWithDelta(22.99, $result, 0.01);
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
        update_site_option('pod_aggregator_printful_api_key', 'TEST_KEY');
        $adapter = new Printful_Adapter();

        $result = $adapter->submit_order(1, [
            'items' => [
                ['variant_id' => 123, 'quantity' => 1, 'price' => '15.00'],
            ],
            'shipping' => 'STANDARD',
        ]);

        $this->assertIsArray($result);
    }
}
