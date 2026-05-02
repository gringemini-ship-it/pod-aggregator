# Developer Guide

Everything you need to develop, test, and contribute to POD Aggregator.

**Assumes:** WordPress 6.9+, PHP 7.4+, WooCommerce 8+ (for integration tests)

---

## Repository Overview

```
pod-aggregator/
├── pod-aggregator.php          # Bootstrap + plugin header (activation hooks here)
├── admin/                      # Admin-only classes (Settings API, admin menus)
├── includes/                   # Core plugin classes
│   ├── class-loader.php        # Registers all add_action / add_filter calls
│   ├── class-ajax.php          # AJAX handlers for the design customizer
│   ├── class-pod-provider.php  # Provider interface (contract all adapters implement)
│   ├── class-cpt-registrar.php # Registers pod_product + pod_design CPTs
│   ├── class-product-importer.php  # Import Printful catalog → WooCommerce products
│   ├── providers/              # One class per POD provider
│   │   └── class-printful.php
│   ├── WooCommerce/            # WooCommerce integration hooks
│   │   └── class-integration.php
│   ├── product-customizer/     # Design editing value objects + REST controller
│   │   ├── class-design.php
│   │   ├── class-design-element.php
│   │   ├── class-design-storage.php
│   │   ├── class-print-generator.php
│   │   └── class-rest-controller.php
│   ├── REST/                   # Sync REST endpoints
│   │   └── class-controller.php
│   └── Crons/                  # Cron scheduling and handlers
│       └── class-scheduler.php
├── public/                     # Shortcode renderer (frontend)
├── tests/
│   ├── bootstrap.php           # WordPress function mocks + Composer autoload
│   └── phpunit/
│       ├── unit/               # 9 test files — run without WordPress
│       └── integration/        # Live WP+WC tests (empty — see "Writing Integration Tests")
└── scripts/
    └── build.sh                # Release ZIP builder
```

---

## Quick Start

```bash
git clone https://github.com/gringemini-ship-it/pod-aggregator.git
cd pod-aggregator
composer install
composer test:unit
```

Green dots = working test suite.

---

## Development Environment Setup

### System Requirements

| Tool | Minimum | Install |
|------|---------|---------|
| PHP | 7.4 (8.x recommended) | `php --version` |
| Composer | 2.x | `composer --version` |
| Git | any recent | `git --version` |
| PHPUnit | 9.6 (via Composer) | `./vendor/bin/phpunit --version` |
| PHP extensions | `gd`, `mbstring`, `curl`, `json`, `xml` | `php -m \| grep -E "gd\|mbstring\|curl"` |
| WordPress (integration tests) | 6.9 | See below |
| WP-CLI (optional) | 2.x | `wp --version` |

### Install Dependencies

```bash
composer install
```

`composer install` downloads PHPUnit 9.6 + Yoast Polyfills into `vendor/`, generates the PSR-4 autoloader, and creates the `vendor/bin/phpunit` + `vendor/bin/phpcs` binaries.

### Local WordPress for Integration Tests

Integration tests require a live WordPress + WooCommerce install. Three options:

**Option A — WP-CLI scaffold (recommended):**

```bash
# Install WP-CLI if needed
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp

# Install WordPress test library
wp scaffold package-tests .

# Run integration tests
./vendor/bin/phpunit --testsuite=integration
```

**Option B — Point to existing site:**

Edit `phpunit.xml.dist`:

```xml
<php>
    <env name="WP_TESTS_DIR" value="/path/to/site/htdocs/wp-content/plugins/pod-aggregator/tests"/>
    <env name="ABSPATH" value="/path/to/site/htdocs/"/>
    <env name="WP_PLUGIN_DIR" value="/path/to/site/htdocs/wp-content/plugins"/>
</php>
```

**Option C — Docker:**

```bash
docker run -d --name pod-wp-test \
  -e WORDPRESS_DB_HOST=mysql \
  -e WORDPRESS_DB_NAME=pod_test \
  wordpress:6.9-php8.2-apache

docker exec pod-wp-test wp plugin install woocommerce --activate
./vendor/bin/phpunit --testsuite=integration
```

### (Optional) PHPCS WordPress Ruleset

```bash
composer global require wp-coding-standards/wpcs squizlabs/php_codesniffer:*
phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs
```

---

## How to Run Tests

```bash
# All tests (unit + integration)
composer test

# Unit tests only (no WordPress needed)
composer test:unit

# Integration tests only (requires live WP + WC)
composer test:integration

# Coverage HTML report (requires Xdebug or PCOV)
composer test:coverage
open coverage/index.html

# Single test file
./vendor/bin/phpunit tests/phpunit/unit/class-design-test.php

# Tests matching a name
./vendor/bin/phpunit --testsuite=unit --filter="testSanitize"

# Test names + descriptions
./vendor/bin/phpunit --testsuite=unit --testdox

# Stop on first failure
./vendor/bin/phpunit --testsuite=unit --stop-on-failure

# Random order (catches hidden dependencies)
./vendor/bin/phpunit --testsuite=unit --random-order
```

---

## Test Architecture

### Unit Tests (`tests/phpunit/unit/`)

Each file tests one class in isolation. **All WordPress/WooCommerce functions are mocked** in `tests/bootstrap.php`.

**Mocked:** `sanitize_text_field()`, `wp_verify_nonce()`, `current_user_can()`, `get_option()`, `wp_remote_post()`, `wc_get_product()`, `WP_Error`, HTTP responses, `wp_upload_dir()`

**Not mocked:** Plain PHP — `json_encode()`, `file_put_contents()`, `imagecreatetruecolor()`, etc.

```php
// wp_remote_post mocked to return a predictable Printful response
function wp_remote_post(string $url, array $args = []) {
    return [
        'body'     => json_encode(['result' => ['id' => 'mock_order_123']]),
        'response' => ['code' => 200],
    ];
}
```

### Integration Tests (`tests/phpunit/integration/`)

These hit the **real WordPress database** — actual CPT posts, real HTTP calls to Printful. The `tests/phpunit/integration/` directory is currently empty. To add tests:

```bash
wp scaffold package-tests .
```

Then create a test file extending `Yoast\PHPUnitPolyfills\TestCases\IntegrationTestCase`. See "Writing Integration Tests" below.

### Bootstrap File (`tests/bootstrap.php`)

Loaded before every PHPUnit run. Load order:

1. Defines constants: `ABSPATH`, `WPINC`, `WP_TESTS_MULTISITE`
2. Loads Composer's autoloader (`vendor/autoload.php`)
3. Defines WordPress function mocks — `if (!function_exists(...))` pattern means real WP functions take precedence when available

Key mocks:

| Mock | Behaviour |
|------|-----------|
| `wp_generate_uuid4()` | Returns deterministic UUID string |
| `wp_remote_post()` | Returns `['result' => ['id' => 'mock_order_123']]` |
| `wp_upload_dir()` | Returns `/tmp/pod-aggregator-test-uploads/` |
| `get_site_option()` / `update_site_option()` | In-memory static array (no DB) |
| `current_user_can()` | Always returns `true` |
| `WP_Error` class | Stub implementing `get_error_code/message/data()`, `add()` |

---

## PHPUnit Configuration (`phpunit.xml.dist`)

Key attributes:

| Attribute | Value | Effect |
|-----------|-------|--------|
| `bootstrap` | `tests/bootstrap.php` | Loads mocks before any test |
| `backupGlobals` | `false` | Don't backup/restore `$GLOBALS` between tests |
| `beStrictAboutOutputDuringTests` | `true` | Any `echo`/`print` in a test fails it |
| `failOnRisky` | `true` | Fail tests that produce unchecked side effects |
| `failOnWarning` | `true` | Treat PHP warnings as failures |
| `executionOrder` | `depends,defects` | Run tests in dependency order; failed tests first |

Coverage excludes `includes/providers/class-printful.php` (live API calls). The `WP_TESTS_MULTISITE=1` env var activates multisite mock mode.

---

## Writing New Unit Tests

### Naming Convention

```
tests/phpunit/unit/class-{slug}-test.php
```

- `class-print-generator.php` → `class-print-generator-test.php`
- `class-design-element.php` → `class-design-element-test.php`

### Test Class Template

```php
<?php
/**
 * Unit tests for Class_Name.
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\Class_Name;

class Class_Name_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── Method existence ────────────────────────────────────────────────────

    public function testMethodExists(): void
    {
        $this->assertTrue(method_exists(Class_Name::class, 'methodName'));
    }

    public function testConstantIsDefined(): void
    {
        $this->assertSame('expected_value', Class_Name::CONSTANT_NAME);
    }

    // ── Constructor ────────────────────────────────────────────────────────

    public function testConstructorAcceptsArray(): void
    {
        $obj = new Class_Name(['key' => 'value']);
        $this->assertInstanceOf(Class_Name::class, $obj);
    }

    // ── Happy path ─────────────────────────────────────────────────────────

    public function testMethodReturnsExpectedType(): void
    {
        $obj = new Class_Name([...]);
        $result = $obj->methodName($input);
        $this->assertSame('expected', $result);
    }

    public function testMethodReturnsArrayWithCorrectKeys(): void
    {
        $obj = new Class_Name([...]);
        $result = $obj->getData();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('expected_key', $result);
    }

    // ── Error / edge cases ─────────────────────────────────────────────────

    public function testMethodReturnsWpErrorOnInvalidInput(): void
    {
        $obj = new Class_Name([...]);
        $result = $obj->method('invalid');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('error_code', $result->get_error_code());
    }

    public function testMethodClampsOutOfRangeValue(): void
    {
        $obj = new Class_Name(['markup' => 999]);
        $result = $obj->getMarkup();
        $this->assertLessThanOrEqual(500, $result);
    }

    // ── Private methods (via ReflectionMethod) ──────────────────────────────

    public function testPrivateSanitizeMethodTrimsWhitespace(): void
    {
        $refl = new \ReflectionMethod(Class_Name::class, 'sanitizeInput');
        $refl->setAccessible(true);

        $obj = new Class_Name([...]);
        $result = $refl->invoke($obj, "  value  \n");
        $this->assertSame('value', $result);
    }
}
```

### Common Assertions

```php
$this->assertSame($expected, $actual);       // === (type-sensitive)
$this->assertEquals($expected, $actual);     // == (type-converting)
$this->assertTrue($value);
$this->assertFalse($value);
$this->assertNull($value);
$this->assertContains($needle, $haystack);
$this->assertArrayHasKey($key, $array);
$this->assertCount($count, $array);
$this->assertInstanceOf(ClassName::class, $object);
$this->assertGreaterThan($min, $actual);
$this->assertLessThanOrEqual($max, $actual);
$this->expectException(\InvalidArgumentException::class);
$this->assertStringContainsString($needle, $actual);
$this->assertMatchesRegularExpression('/regex/', $actual);
```

---

## Writing Integration Tests

The `tests/phpunit/integration/` directory is empty. To add tests:

**Step 1 — Install the WordPress test library:**

```bash
wp scaffold package-tests .
```

This replaces `tests/bootstrap.php` with one that uses the real WordPress test library instead of mocks.

**Step 2 — Create an integration test file:**

```php
<?php
/**
 * Integration tests for POD Aggregator plugin activation.
 * @package POD_Aggregator\Tests\Integration
 */

namespace POD_Aggregator\Tests\Integration;

use Yoast\PHPUnitPolyfills\TestCases\IntegrationTestCase;

class Activation_Test extends IntegrationTestCase
{
    public function testCptIsRegisteredAfterActivation(): void
    {
        do_action('pod_aggregator_activated');
        global $wp_post_types;
        $this->assertArrayHasKey('pod_product', $wp_post_types);
    }

    public function testImportProductCreatesWooCommerceProduct(): void
    {
        $product_id = $this->factory->post->create(['post_type' => 'product']);
        $this->assertSame('product', get_post_type($product_id));
    }
}
```

**Step 3 — Run:**

```bash
./vendor/bin/phpunit --testsuite=integration
```

---

## Code Style and Linting

```bash
# Lint all plugin files (PSR-12)
composer lint

# Show only errors
./vendor/bin/phpcs --standard=PSR12 --severity=error includes/ admin/ public/

# Auto-fix where possible (whitespace, bracing, etc.)
composer lint:fix

# Fix a specific file
./vendor/bin/phpcbf --standard=PSR12 includes/class-loader.php
```

Note: `phpcbf` cannot fix complex logic style issues — those must be fixed manually.

---

## Pre-Commit Hooks

Run once to install a hook that blocks `git commit` if tests or lint fail:

```bash
cat > .git/hooks/pre-commit << 'EOF'
#!/usr/bin/env bash
set -e
echo "[pre-commit] Running unit tests..."
./vendor/bin/phpunit --testsuite=unit --stop-on-failure
echo "[pre-commit] Running linter..."
./vendor/bin/phpcs --standard=PSR12 --severity=error admin/ includes/ public/
echo "[pre-commit] All checks passed"
EOF
chmod +x .git/hooks/pre-commit
```

To bypass temporarily:

```bash
git commit --no-verify -m "Emergency: pushing without tests"
```

---

## WP-CLI Commands

### Sync Products

```bash
wp pod-aggregator sync                      # Sync all products
wp pod-aggregator sync --product_id=123     # Sync specific product
wp pod-aggregator sync --refresh-catalog    # Force full catalog refresh
wp pod-aggregator sync --dry-run            # Show what would sync
wp pod-aggregator sync --unlock             # Clear stuck sync lock
```

### Manage Designs

```bash
wp pod-aggregator design list                          # List all saved designs
wp pod-aggregator design delete a1b2c3d4-...          # Delete by UUID
wp pod-aggregator design export a1b2c3d4-... > d.json  # Export as JSON
```

### Cron

```bash
wp cron event list
wp cron event run pod_aggregator_hourly_sync           # Run sync immediately
wp cron event run --all
wp cron event delete pod_aggregator_hourly_sync        # Delete scheduled sync events
```

---

## Debugging Tests

### Run a Single Test with Full Output

```bash
./vendor/bin/phpunit \
  --testsuite=unit \
  --filter="testSanitizeClampsMarkupTo500" \
  --testdox -v
```

### Inspect a Failing Test

```bash
./vendor/bin/phpunit --testsuite=unit --filter="testMethod" --stop-on-failure -vvv
```

### Add Debug Output

```php
public function testSomething(): void
{
    $design = new Design(['product_id' => 1]);
    fwrite(STDERR, "DEBUG: " . print_r($design->to_array(), true) . "\n");
}
```

### Check Whether a Function Is Mocked

```php
public function testCheckNonceIsMocked(): void
{
    $result = wp_verify_nonce('test_nonce', 'test_action');
    $this->assertSame(1, $result);  // Mocked bootstrap always returns 1
}
```

---

## Troubleshooting Test Failures

### "Class 'POD_Aggregator\...' not found"

Autoloader not generated. Fix:

```bash
composer install
# or regenerate:
composer dump-autoload
```

### "PHP Fatal error: Class 'WP_Error' not found"

`ABSPATH` may be pointing to a real WordPress install with an old WP version. For unit tests, use the mock bootstrap (the default).

### "PHPUnit runs but all tests are skipped"

Check `WP_TESTS_DIR` is not set in your environment:

```bash
echo $WP_TESTS_DIR  # Should be empty for unit tests
./vendor/bin/phpunit --testsuite=unit
```

### "Code coverage report is empty"

Xdebug or PCOV is not enabled:

```bash
php -m | grep -i xdebug  # Check Xdebug

# Enable in php.ini (Xdebug 3):
# zend_extension=xdebug
# xdebug.mode=coverage

# Or use PCOV (faster):
# pecl install pcov && echo "extension=pcov.so" >> php.ini
```

### "Tests pass locally but fail in CI"

Check CI environment's PHP version and extensions:

```bash
php --version
php -m | grep -E "gd|mbstring|curl"
which php
composer install --no-interaction
```

---

## Architecture

### Why This Architecture?

| Decision | Reason |
|----------|--------|
| PSR-4 namespaced classes in `includes/` | Avoids global function/constant collisions |
| Single bootstrap file `pod-aggregator.php` | WordPress loads one main plugin file — keeps activation hooks simple |
| Value objects (`Design`, `Design_Element`) | Immutable, easy to test, no hidden state |
| Provider interface (`class-pod-provider.php`) | Adding Printify/Gelato = implement one interface |
| CPT for designs (`pod_design`) | Design data independent of WooCommerce order data |
| Settings API (not custom options page) | Leverages WordPress's built-in security (nonces, capabilities, sanitization) |

### Request Lifecycle — Frontend

```
HTTP Request
    ↓
wp-load.php (WordPress bootstrap)
    ↓
pod-aggregator.php (plugin loaded via autoloader)
    ↓
class-loader.php (registers all add_action / add_filter)
    ↓
Shortcode [pod_customizer] → public/class-customizer-editor.php
    ↓
AJAX calls → class-ajax.php (nonce-verified, capability-checked)
    ↓
Design saved via REST API → product-customizer/class-rest-controller.php
    ↓
Design persisted to pod_design CPT → class-design-storage.php
```

### Request Lifecycle — Checkout

```
WooCommerce Checkout completed
    ↓
hook: woocommerce_checkout_order_processed
    ↓
WooCommerce\Integration::submit_order_to_provider()
    ↓
Printful Adapter: POST to Printful API
    ↓
Printful response stored in WC order meta
    ↓
WooCommerce order note added
```

---

## Contributing

### Fork and Clone

```bash
git clone https://github.com/YOUR_USERNAME/pod-aggregator.git
cd pod-aggregator
git remote add upstream https://github.com/gringemini-ship-it/pod-aggregator.git
```

### Create a Feature Branch

```bash
git checkout master
git pull upstream master
git checkout -b feature/add-printify-adapter
```

### Before Pushing — Run All Checks

```bash
# 1. Unit tests
composer test:unit

# 2. Linting
composer lint

# 3. Coverage (open coverage/index.html and check your changed files)
composer test:coverage

# 4. Build dry-run
./scripts/build.sh check
```

### Commit Message Format

```
Add Printify adapter

- Implement Printify_Adapter extending POD_Provider
- Add printify-api-key field to Settings page
- Add integration tests for variant sync
- Update README with Printify setup instructions
Fixes: Closes #XX
```

### Pull Request Checklist

Before opening a PR, confirm:

- [ ] `composer test:unit` passes
- [ ] `composer lint` passes (or deviations documented and justified)
- [ ] New behaviour has unit tests
- [ ] New behaviour has integration tests (if applicable)
- [ ] README is updated if user-facing behaviour changed
- [ ] No debug code (`var_dump`, `error_log`, `console.log`) left in source
- [ ] No new files in `vendor/` or build artifacts committed

### Code Review Criteria

PRs are reviewed for:
- **Correctness** — Does it solve the stated problem?
- **Security** — Nonces, capabilities, sanitisation, escaping?
- **Performance** — N+1 queries, unnecessary DB writes, heavy operations in hot paths?
- **Test coverage** — Right things tested, not just easy things?
- **Style** — Follows existing patterns in the codebase?

---

## Release Process

### Step 1 — Version Bump

```bash
grep "Version:" pod-aggregator.php
# Update: 1.2.3 → 1.3.0
```

### Step 2 — Run Full Test Suite

```bash
composer test
composer test:coverage  # review coverage
composer lint
```

### Step 3 — Build the Release ZIP

```bash
# Validate (syntax + PHPCS, no ZIP)
./scripts/build.sh check

# Build ZIP for version 1.3.0
./scripts/build.sh 1.3.0
```

Output: `pod-aggregator.zip` and `pod-aggregator/trunk/` staged directory.

### Step 4 — Test the Built ZIP

```bash
wp plugin install ./pod-aggregator.zip --activate-network
wp plugin list --status=active | grep pod-aggregator
wp pod-aggregator sync --dry-run
```

### Step 5 — Tag and Release

```bash
git tag -a v1.3.0 -m "Release v1.3.0 — Add Printify adapter"
git push upstream v1.3.0

# On GitHub: draft a new Release, attach pod-aggregator.zip
# On wordpress.org: upload pod-aggregator.zip to SVN
```

---

## Composer Scripts Reference

| Command | What it does |
|---------|-------------|
| `composer test` | Run all PHPUnit suites |
| `composer test:unit` | Run unit tests only |
| `composer test:integration` | Run integration tests only |
| `composer test:coverage` | Run with HTML coverage report |
| `composer lint` | PHPCS with PSR-12 |
| `composer lint:fix` | PHPCBF auto-fix |
| `composer dump-autoload` | Regenerate PSR-4 autoloader |
