<?php
/**
 * Unit tests for Print_Generator.
 *
 * Actual public API:
 *   - generate(Design $design): array|WP_Error — saves PNG to uploads/pod-prints/
 *   - generate_preview(Design $design, string $area, int $dpi = 72): array|WP_Error
 *
 * Constants:
 *   - UPLOAD_SUBDIR = 'pod-prints'
 *   - ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp']
 *   - MAX_UPLOAD_SIZE = 5242880 (5MB)
 *
 * The protected/private methods (parse_color, get_safe_area_inset, gd_from_file,
 * draw_element, etc.) are implementation details tested implicitly through
 * the public generate() and generate_preview() methods.
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\Print_Generator;
use POD_Aggregator\ProductCustomizer\Design;
use POD_Aggregator\ProductCustomizer\DesignElement;

class Print_Generator_Test extends TestCase
{
    private Print_Generator $generator;

    protected function setUp(): void
    {
        $this->generator = new Print_Generator();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testUploadSubdirConstant(): void
    {
        $this->assertSame('pod-prints', Print_Generator::UPLOAD_SUBDIR);
    }

    public function testAllowedMimesConstant(): void
    {
        $mimes = Print_Generator::ALLOWED_MIMES;
        $this->assertContains('image/png', $mimes);
        $this->assertContains('image/jpeg', $mimes);
        $this->assertContains('image/gif', $mimes);
        $this->assertContains('image/webp', $mimes);
    }

    public function testMaxUploadSizeConstant(): void
    {
        $this->assertSame(5 * 1024 * 1024, Print_Generator::MAX_UPLOAD_SIZE);
    }

    // -------------------------------------------------------------------------
    // generate() validation — passes through Design::validate()
    // -------------------------------------------------------------------------

    public function testGenerateReturnsWpErrorForInvalidDesign(): void
    {
        // Design with product_id=0 fails Design::validate()
        $design = new Design([]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $this->generator->generate($design);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function testGenerateReturnsWpErrorForDesignWithEmptyElements(): void
    {
        $design = new Design(['product_id' => 1]);
        // No elements added

        $result = $this->generator->generate($design);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pod_design_invalid', $result->get_error_code());
    }

    // -------------------------------------------------------------------------
    // generate_preview() validation
    // -------------------------------------------------------------------------

    public function testGeneratePreviewReturnsWpErrorForInvalidDesign(): void
    {
        $design = new Design([]); // no product_id
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $this->generator->generate_preview($design, 'front');

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function testGeneratePreviewReturnsWpErrorForEmptyElements(): void
    {
        $design = new Design(['product_id' => 1, 'area' => 'front']);

        $result = $this->generator->generate_preview($design, 'front');

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // -------------------------------------------------------------------------
    // generate() return shape on success (mock environment)
    // Note: In a true unit test without GD, we can only verify WP_Error paths.
    // For the success path, see integration tests.
    // -------------------------------------------------------------------------

    public function testGenerateReturnsArrayOnSuccessWithMockedGd(): void
    {
        // Skip if GD is not available in the test environment
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD not available in this environment');
        }

        $design = new Design([
            'id'         => 'gd-test-uuid',
            'product_id' => 1,
            'area'       => 'front',
        ]);
        $design->add_element(DesignElement::text('GD Test', 0, 0));

        $result = $this->generator->generate($design);

        // In mocked bootstrap, imagecreatetruecolor exists but GD is not truly
        // functional. In a real environment this would return an array.
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // Integration of Design validation passes through generate()
    // -------------------------------------------------------------------------

    public function testGeneratePassesThroughDesignValidateError(): void
    {
        // Design with invalid area
        $design = new Design([
            'id'         => 'test-uuid',
            'product_id' => 1,
            'area'       => 'nonexistent_area',
        ]);
        $design->add_element(DesignElement::text('Hello', 0, 0));

        $result = $this->generator->generate($design);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pod_design_invalid', $result->get_error_code());
    }
}
