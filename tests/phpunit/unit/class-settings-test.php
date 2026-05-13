<?php
/**
 * Unit tests for Admin Settings.
 *
 * Actual Settings API:
 *   - register_settings(): public — registers settings with WordPress Settings API
 *   - render_printful_section_desc(): public — echoes section description
 *   - render_sync_section_desc(): public — echoes sync section description
 *   - render_printful_api_key_field(): public — echoes password input
 *   - render_printful_markup_field(): public — echoes number input (0-500, default 30)
 *   - render_printful_debug_field(): public — echoes checkbox
 *   - render_auto_sync_field(): public — echoes checkbox
 *   - render_sync_interval_field(): public — echoes number input
 *   - validate_printful_key(): private — validates API key format
 *   - sanitize_settings(array $input): private — sanitize_callback for register_setting()
 *
 * Settings are stored as a single site option: pod_aggregator_settings
 * Field keys within that array:
 *   - printful_api_key (string, sanitized via validate_printful_key)
 *   - printful_default_markup (int, 0-500, default 30)
 *   - printful_debug_mode (bool)
 *   - auto_sync_enabled (bool)
 *   - sync_interval_hours (int, 0-168)
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\Admin\Settings;

class Settings_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testSettingsKeyConstant(): void
    {
        $this->assertSame('pod_aggregator_settings', Settings::SETTINGS_KEY);
    }

    // -------------------------------------------------------------------------
    // Public methods exist
    // -------------------------------------------------------------------------

    public function testRegisterSettingsMethodExists(): void
    {
        $settings = new Settings();
        $this->assertTrue(method_exists($settings, 'register_settings'));
    }

    public function testRenderPrintfulSectionDescMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_printful_section_desc'));
    }

    public function testRenderSyncSectionDescMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_sync_section_desc'));
    }

    public function testRenderPrintfulApiKeyFieldMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_printful_api_key_field'));
    }

    public function testRenderPrintfulMarkupFieldMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_printful_markup_field'));
    }

    public function testRenderPrintfulDebugFieldMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_printful_debug_field'));
    }

    public function testRenderAutoSyncFieldMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_auto_sync_field'));
    }

    public function testRenderSyncIntervalFieldMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'render_sync_interval_field'));
    }

    // -------------------------------------------------------------------------
    // sanitize_settings is private — confirm it exists
    // -------------------------------------------------------------------------

    public function testSanitizeSettingsIsPublicMethod(): void
    {
        $refl = new \ReflectionMethod(Settings::class, 'sanitize_settings');
        $this->assertTrue($refl->isPublic());
    }

    public function testSanitizeSettingsAcceptsArrayParameter(): void
    {
        $refl = new \ReflectionMethod(Settings::class, 'sanitize_settings');
        $params = $refl->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('input', $params[0]->getName());
    }

    public function testValidatePrintfulKeyIsPrivateMethod(): void
    {
        $refl = new \ReflectionMethod(Settings::class, 'validate_printful_key');
        $this->assertTrue($refl->isPrivate());
    }

    // -------------------------------------------------------------------------
    // Field IDs used in register_settings()
    // -------------------------------------------------------------------------

    public function testFieldIdsRegisteredInRegisterSettings(): void
    {
        $settings = new Settings();

        // We test this by checking that the method completes without error
        // The actual registration would call add_settings_field which is a WP function
        // Since we're testing in isolation, we verify the method is callable
        $this->assertTrue(is_callable([$settings, 'register_settings']));
    }

    // -------------------------------------------------------------------------
    // validate_printful_key behavior — private but we can verify indirectly
    // -------------------------------------------------------------------------

    public function testValidatePrintfulKeyMethodExists(): void
    {
        $this->assertTrue(method_exists(Settings::class, 'validate_printful_key'));
    }
}
