<?php
/**
 * POD Provider interface — all adapters must implement this.
 *
 * @package POD_Aggregator
 */

namespace POD_Aggregator;

/**
 * Interface that every POD provider adapter must implement.
 *
 * Implementations: Printful, Printify, Gelato, etc.
 *
 * @since 1.0.0
 */
interface Provider_Interface
{
    // --- Configuration ---

    /**
     * Get provider unique slug/ID.
     *
     * @return string
     */
    public function get_slug(): string;

    /**
     * Get human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if the provider is configured (has valid API credentials).
     *
     * @return bool
     */
    public function is_configured(): bool;

    // --- Catalog ---

    /**
     * Fetch all products from the provider.
     * Returns array of product objects.
     *
     * @return array
     */
    public function get_products(): array;

    /**
     * Fetch a single product by provider product ID.
     *
     * @param string $product_id Provider's product ID.
     * @return array|\WP_Error
     */
    public function get_product(string $product_id);

    /**
     * Get product variants for a given product.
     *
     * @param string $product_id Provider's product ID.
     * @return array
     */
    public function get_product_variants(string $product_id): array;

    // --- Pricing ---

    /**
     * Calculate price for a variant with optional markup.
     *
     * @param array $variant      Variant data from provider.
     * @param float $retail_price Optional retail price override.
     * @return float
     */
    public function calculate_price(array $variant, float $retail_price = 0.0): float;

    // --- Mockups ---

    /**
     * Get mockup images for a product.
     *
     * @param string $product_id Provider product ID.
     * @param array $options     Optional customization options (print area, etc.).
     * @return array Array of mockup image URLs.
     */
    public function get_mockups(string $product_id, array $options = []): array;

    // --- Orders ---

    /**
     * Submit an order to the provider.
     *
     * @param array $order_data {
     *   int      $woo_order_id       WooCommerce order ID.
     *   array    $items              Line items with qty, variant_id, design_data.
     *   array    $shipping_address   Customer shipping address.
     *   string   $provider_product_id
     *   string   $variant_id
     *   array    $design_data        JSON design layers/positions.
     * }
     * @return array|\WP_Error Order result from provider.
     */
    public function submit_order(array $order_data);

    /**
     * Get order status from provider.
     *
     * @param string $external_order_id Order ID from the provider.
     * @return array Status data (status, tracking_number, tracking_url, etc.).
     */
    public function get_order_status(string $external_order_id): array;

    /**
     * Get the provider's tracking URL pattern.
     *
     * @param string $tracking_number
     * @return string
     */
    public function get_tracking_url(string $tracking_number): string;
}
