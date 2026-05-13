<?php
/**
 * Unit tests for Admin Settings — sanitization behavior.
 *
 * The sanitize_settings() method is private; it is the sanitize_callback
 * passed to register_setting(). We test it via reflection or indirectly.
 *
 * Actual field names in the Settings API:
 *   - printful_api_key           (string, validated by validate_printful_key())
 *   - printful_default_markup    (int, 0-500, default 30)
 *   - printful_debug_mode       (bool, checkbox)
 *   - auto_sync_enabled         (bool, checkbox)
 *   - sync_interval_hours       (int, 0-168)
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\Admin\Settings;

class Settings_Sanitization_Test extends TestCase
{
    /**
     * Invoke the private sanitize_settings method via reflection.
     *
     * @param array $input Raw input array.
     * @return array Sanitized output.
     */
    private function sanitize(array $input): array
    {
        $refl = new \ReflectionMethod(Settings::class, 'sanitize_settings');
        $refl->setAccessible(true);
        $settings = new Settings();
        return $refl->invoke($settings, $input);
    }

    // -------------------------------------------------------------------------
    // Markup field — validated to 0-500 range
    // -------------------------------------------------------------------------

    public function testSanitizeClampsMarkupTo500(): void
    {
        $result = $this->sanitize(['printful_default_markup' => '999']);
        $this->assertLessThanOrEqual(500, $result['printful_default_markup']);
    }

    public function testSanitizeAcceptsValidMarkup(): void
    {
        $result = $this->sanitize(['printful_default_markup' => '30']);
        $this->assertSame(30, $result['printful_default_markup']);
    }

    public function testSanitizeMarkupStripsNegative(): void
    {
        // absint(-10) = 10, which is in valid range (0-500)
        $result = $this->sanitize(['printful_default_markup' => '-10']);
        $this->assertSame(10, $result['printful_default_markup']);
    }

    public function testSanitizeMarkupAllowsZero(): void
    {
        $result = $this->sanitize(['printful_default_markup' => '0']);
        $this->assertSame(0, $result['printful_default_markup']);
    }

    public function testSanitizeMarkupAllows500(): void
    {
        $result = $this->sanitize(['printful_default_markup' => '500']);
        $this->assertSame(500, $result['printful_default_markup']);
    }

    // -------------------------------------------------------------------------
    // Debug mode — bool
    // -------------------------------------------------------------------------

    public function testSanitizeDebugModeTrue(): void
    {
        $result = $this->sanitize(['printful_debug_mode' => '1']);
        $this->assertSame('1', $result['printful_debug_mode']);
    }

    public function testSanitizeDebugModeFalse(): void
    {
        $result = $this->sanitize(['printful_debug_mode' => '']);
        $this->assertSame('0', $result['printful_debug_mode']);
    }

    // -------------------------------------------------------------------------
    // Auto sync enabled — bool
    // -------------------------------------------------------------------------

    public function testSanitizeAutoSyncEnabledTrue(): void
    {
        $result = $this->sanitize(['auto_sync_enabled' => '1']);
        $this->assertSame('1', $result['auto_sync_enabled']);
    }

    public function testSanitizeAutoSyncEnabledFalse(): void
    {
        $result = $this->sanitize(['auto_sync_enabled' => '']);
        $this->assertSame('0', $result['auto_sync_enabled']);
    }

    // -------------------------------------------------------------------------
    // Sync interval — int, 0-168
    // -------------------------------------------------------------------------

    public function testSanitizeSyncIntervalClampedToMin1(): void
    {
        // 0 is below minimum of 1, gets clamped to 1
        $result = $this->sanitize(['sync_interval_hours' => '0']);
        $this->assertSame(1, $result['sync_interval_hours']);
    }

    public function testSanitizeSyncIntervalAllowsPositive(): void
    {
        $result = $this->sanitize(['sync_interval_hours' => '24']);
        $this->assertSame(24, $result['sync_interval_hours']);
    }

    public function testSanitizeSyncIntervalClampsOverflow(): void
    {
        $result = $this->sanitize(['sync_interval_hours' => '999']);
        $this->assertSame(168, $result['sync_interval_hours']);
    }

    public function testSanitizeSyncIntervalStripsNegative(): void
    {
        // absint(-5) = 5, which is in the valid 1-168 range
        $result = $this->sanitize(['sync_interval_hours' => '-5']);
        $this->assertSame(5, $result['sync_interval_hours']);
    }

    // -------------------------------------------------------------------------
    // API key — passthrough string (validate_printful_key strips whitespace)
    // -------------------------------------------------------------------------

    public function testSanitizeApiKeyTrimsWhitespace(): void
    {
        $result = $this->sanitize(['printful_api_key' => "  abc123  \n"]);
        $this->assertSame('abc123', $result['printful_api_key']);
    }

    public function testSanitizeApiKeyPassesValidKey(): void
    {
        $result = $this->sanitize(['printful_api_key' => 'abcd1234efgh5678']);
        $this->assertSame('abcd1234efgh5678', $result['printful_api_key']);
    }

    public function testSanitizeApiKeyAllowsEmpty(): void
    {
        $result = $this->sanitize(['printful_api_key' => '']);
        $this->assertSame('', $result['printful_api_key']);
    }

    // -------------------------------------------------------------------------
    // Preserves unknown keys
    // -------------------------------------------------------------------------

    public function testSanitizeDropsUnknownKeys(): void
    {
        // Sanitizer only returns known keys — unknown keys are dropped.
        $result = $this->sanitize(['unknown_key' => 'some_value']);
        $this->assertArrayNotHasKey('unknown_key', $result);
    }

    // -------------------------------------------------------------------------
    // No input returns empty/defaults
    // -------------------------------------------------------------------------

    public function testSanitizeEmptyArrayReturnsArray(): void
    {
        $result = $this->sanitize([]);
        $this->assertIsArray($result);
    }
}
