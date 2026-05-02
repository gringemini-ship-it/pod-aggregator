# POD Aggregator — Print-on-Demand WooCommerce Integration

> Connects your WordPress/WooCommerce store to Print-on-Demand providers. Currently supports **Printful**. Architecture is ready for Printify and Gelato.

---

## Table of Contents

1. [What This Plugin Does](#what-this-plugin-does)
2. [Feature Overview](#feature-overview)
3. [Screenshots](#screenshots)
4. [Requirements](#requirements)
5. [Installation — Developers](#installation--developers)
6. [Installation — Customers](#installation--customers)
7. [Setup Guide](#setup-guide)
   - [1. Connect Printful](#1-connect-printful)
   - [2. Import Products](#2-import-products)
   - [3. Configure Pricing Markup](#3-configure-pricing-markup)
   - [4. Enable Product Customizer](#4-enable-product-customizer)
8. [Using the Product Customizer](#using-the-product-customizer)
   - [Design Editor Layout](#design-editor-layout)
   - [Adding Text Elements](#adding-text-elements)
   - [Adding Image Elements](#adding-image-elements)
   - [Managing Elements](#managing-elements)
   - [Preview & Save](#preview--save)
9. [Cart & Checkout](#cart--checkout)
10. [Sync & Cron Jobs](#sync--cron-jobs)
    - [Manual Sync via WP-CLI](#manual-sync-via-wp-cli)
    - [Scheduled Auto-Sync](#scheduled-auto-sync)
    - [Order Status Sync](#order-status-sync)
11. [REST API](#rest-api)
    - [Authentication](#authentication)
    - [Save Design](#save-design)
    - [Load Design](#load-design)
    - [Delete Design](#delete-design)
    - [Generate Print File](#generate-print-file)
    - [Sync Products](#sync-products)
12. [Webhook Reference](#webhook-reference)
    - [Setting Up the Webhook in Printful](#setting-up-the-webhook-in-printful)
    - [Verifying the Webhook Signature](#verifying-the-webhook-signature)
    - [Webhook Events Reference Table](#webhook-events-reference-table)
13. [Multisite Support](#multisite-support)
    - [Network Activation](#network-activation)
    - [Per-Blog Product Import](#per-blog-product-import)
    - [Cron in Multisite](#cron-in-multisite)
14. [Uninstall](#uninstall)
15. [Folder Structure](#folder-structure)
16. [Hooks & Filters Reference](#hooks--filters-reference)
17. [Security](#security)
18. [Troubleshooting](#troubleshooting)
19. [Contributing](#contributing)

---

## What This Plugin Does

POD Aggregator bridges WooCommerce with Print-on-Demand providers so store owners can:

1. **Browse** the provider's catalog directly from WordPress admin
2. **Import** POD products as WooCommerce products with automatic pricing
3. **Let customers design** custom products (text, images, positioning) before adding to cart
4. **Auto-submit** orders to the provider when WooCommerce checkout completes
5. **Sync** inventory and order status on a schedule or on-demand
6. **Receive webhooks** from the provider and update WooCommerce order statuses automatically

---

## Feature Overview

| Feature | Description |
|---------|-------------|
| **Provider Catalog Import** | Browse and import Printful products via the admin panel |
| **Product Sync** | Scheduled hourly sync keeps WooCommerce prices/inventory aligned |
| **Visual Product Customizer** | Front-end design editor for text and image personalization |
| **Per-Product Pricing** | Configurable markup % per product (base cost + markup) |
| **Cart Meta** | Design ID, provider, print area stored on cart items |
| **Order Auto-Submission** | Orders forwarded to Printful on WooCommerce checkout completion |
| **Webhook Processing** | Receive and process Printful order status updates |
| **Multisite Ready** | Network-wide activation with per-blog product import |
| **Custom Post Type** | `pod_product` CPT stores design data independently of WooCommerce |
| **REST API** | Design save/load/delete via authenticated REST endpoints |
| **WP-CLI Commands** | `wp pod-aggregator sync` for manual and scripted syncs |
| **300 DPI Print File Generation** | Generated server-side at checkout using GD library |

---

## Screenshots

*(Screenshots would be placed in `assets/screenshots/` — see the `assets/` directory.)*

- **Admin Settings** — Network settings page under "My Sites → Network Admin → Settings"
- **Product Import** — Submenu page listing Printful catalog with import buttons
- **Preset Templates** — Admin page for managing pre-made design templates
- **Design Customizer** — Front-end block editor shown on the product page
- **Cart Preview** — Inline design summary below cart item name
- **Order Confirmation** — WooCommerce order details showing design metadata

---

## Requirements

| Requirement | Version / Detail |
|------------|-----------------|
| **WordPress** | 6.9 or higher |
| **PHP** | 7.4 or higher (8.x recommended) |
| **WooCommerce** | 8.x or higher |
| **PHP Extensions** | `gd` (for print file generation), `mbstring`, `curl` |
| **Printful account** | Free at [printful.com](https://www.printful.com) with API key |
| **WP-CLI** | Optional — for manual sync and cron management |

Check your PHP extensions:

```bash
php -m | grep -E "gd|mbstring|curl"
```

If `gd` is missing, install it:

```bash
# Ubuntu/Debian
sudo apt-get install php-gd php-mbstring php-curl

# CentOS/RHEL
sudo yum install php-gd php-mbstring php-curl

# Then restart PHP-FPM or Apache
sudo systemctl restart php-fpm
# or
sudo systemctl restart apache2
```

---

## For Developers

This section covers everything you need to develop, test, and contribute to POD Aggregator — from a fresh clone to a submitted pull request.

---

### Repository Overview

```
pod-aggregator/
├── pod-aggregator.php          # Bootstrap + plugin header (activation hooks here)
├── admin/                      # Admin-only classes (Settings API, admin menus)
├── includes/                   # Core plugin classes (loader, CPT, providers, WC integration)
│   ├── class-loader.php        # Registers all add_action / add_filter calls
│   ├── class-ajax.php          # AJAX handlers for the design customizer
│   ├── class-pod-provider.php  # Provider interface (contract all adapters must implement)
│   ├── class-cpt-registrar.php # Registers pod_product + pod_design CPTs
│   ├── class-product-importer.php  # Import Printful catalog → WooCommerce products
│   ├── providers/              # One class per POD provider
│   │   └── class-printful.php
│   ├── WooCommerce/             # WooCommerce integration hooks
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
│       └── integration/         # Live WP+WC tests (empty — see "Writing Integration Tests")
└── scripts/
    └── build.sh                # Release ZIP builder
```

---

### Quick Start (5 Minutes to Running Tests)

```bash
# 1. Clone the repository into your WordPress plugins directory
git clone https://github.com/gringemini-ship-it/pod-aggregator.git
cd pod-aggregator

# 2. Install PHP dev dependencies
composer install

# 3. Verify PHPUnit is installed
./vendor/bin/phpunit --version
# Should print: PHPUnit 9.6.x

# 4. Run the unit tests (no WordPress needed)
composer test:unit
```

That's it. If you see green dots, the test suite is working.

---

### Full Development Environment Setup

#### Step 1 — System Requirements

| Tool | Minimum Version | Install |
|------|----------------|---------|
| **PHP** | 7.4 (8.x recommended) | `php --version` |
| **Composer** | 2.x | `composer --version` |
| **Git** | any recent | `git --version` |
| **PHPUnit** | 9.6 (installed via Composer) | `./vendor/bin/phpunit --version` |
| **PHP extensions** | `gd`, `mbstring`, `curl`, `json`, `xml` | `php -m` |
| **WordPress** (for integration tests) | 6.9 | See below |
| **WP-CLI** (optional, for sync commands) | 2.x | `wp --version` |

Check your PHP version:

```bash
php --version
# Expected: PHP 8.2.x or 8.1.x or 8.0.x or 7.4.x
```

Check required extensions:

```bash
php -m | grep -E "gd|mbstring|curl|json|xml"
# All four should appear in the output
```

If `gd` is missing:

```bash
# Ubuntu / Debian
sudo apt-get install php-gd php-mbstring php-curl

# macOS (Homebrew)
brew install php
# gd is included in the homebrew PHP formula

# Restart your terminal or PHP-FPM after installing
php -m | grep gd
```

#### Step 2 — Clone and Install

```bash
git clone https://github.com/gringemini-ship-it/pod-aggregator.git
cd pod-aggregator
composer install
```

`composer install` does the following:
1. Reads `composer.json`
2. Downloads PHPUnit 9.6 and Yoast PHPUnit Polyfills into `vendor/`
3. Generates the PSR-4 autoloader (`vendor/autoload.php`)
4. Creates the `vendor/bin/phpunit` and `vendor/bin/phpcs` symlinks

#### Step 3 — Verify the Installation

```bash
# Check PHPUnit is installed and readable
./vendor/bin/phpunit --version
# PHPUnit 9.6.x #<hash> — supports annotation-based test generation

# Check PHPCS is installed
./vendor/bin/phpcs --version
# PHP_CodeSniffer 3.x.x

# Quick syntax check (runs without WP)
./vendor/bin/phpunit --testsuite=unit --dry-run 2>&1 | head -5
```

#### Step 4 — Connect to a Local WordPress (for integration tests)

Integration tests require a real WordPress installation with WooCommerce activated. The test framework uses `wp @wordpress/wp` (WP-CLI's internal test library) when available, or falls back to your local `vv` / `Local by Flywheel` / `Docker` site.

**Option A — Use the WP test library via WP-CLI:**

```bash
# Install WP-CLI if you don't have it
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Install the WordPress test library
wp scaffold package-tests .
# This creates tests/bootstrap.php and installs the WordPress test library
# in /tmp/wordpress-tests-lib/

# Run integration tests
./vendor/bin/phpunit --testsuite=integration
```

**Option B — Point to an existing local WordPress install:**

Edit `phpunit.xml.dist` and set the `WP_TESTS_DIR` environment variable:

```xml
<php>
    <env name="WP_TESTS_DIR" value="/path/to/your/site/htdocs/wp-content/plugins/pod-aggregator/tests"/>
    <env name="ABSPATH" value="/path/to/your/site/htdocs/"/>
    <env name="WP_PLUGIN_DIR" value="/path/to/your/site/htdocs/wp-content/plugins"/>
</php>
```

**Option C — Docker-based WordPress:**

```bash
# Start a WordPress + WooCommerce container
docker run -d \
  --name pod-wp-test \
  -e WORDPRESS_DB_HOST=mysql \
  -e WORDPRESS_DB_NAME=pod_test \
  -e WORDPRESS_DB_USER=root \
  -e WORDPRESS_DB_PASSWORD=root \
  wordpress:6.9-php8.2-apache

# Wait for WP to boot, then install WooCommerce via WP-CLI
docker exec pod-wp-test wp plugin install woocommerce --activate

# Run integration tests pointing at the container
./vendor/bin/phpunit --testsuite=integration
```

#### Step 5 — (Optional) Install PHPCS for Style Checking

PHPCS is installed automatically by `composer install`. To also install the WordPress ruleset:

```bash
# Install WordPress Coding Standards
composer global require wp-coding-standards/wpcs squizlabs/php_codesniffer:*

# Register the standards
phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs

# Verify
phpcs -i
# Should list "WordPress" among the installed standards

# Run linting
./vendor/bin/phpcs --standard=WordPress admin/ includes/ public/
```

Or use the included composer script (already configured in `composer.json`):

```bash
composer lint
```

---

### How to Run Tests

All test commands are available as `composer` scripts or direct `vendor/bin/` binaries. Both work identically.

#### Run All Tests (Unit + Integration)

```bash
# Short form (defined in composer.json "test" script)
composer test

# Direct PHPUnit call
./vendor/bin/phpunit
```

This runs the **unit** test suite first, then the **integration** suite. Both must pass for a green CI result.

#### Run Unit Tests Only

Unit tests use **mocks for every WordPress/WooCommerce function** — no database, no HTTP calls, no live server needed.

```bash
composer test:unit

# Direct
./vendor/bin/phpunit --testsuite=unit

# Run with verbose output (shows test names)
./vendor/bin/phpunit --testsuite=unit --testdox

# Run a specific test file
./vendor/bin/phpunit tests/phpunit/unit/class-design-test.php

# Run tests matching a name pattern (annotation filter)
./vendor/bin/phpunit --testsuite=unit --filter="testSanitize"
```

#### Run Integration Tests Only

Integration tests require a **live WordPress site with WooCommerce installed and activated**. They hit the database and make real HTTP requests to the Printful API (with API calls mocked via `wp_remote_get`/`wp_remote_post` mocks if configured).

```bash
composer test:integration

# Direct
./vendor/bin/phpunit --testsuite=integration

# With verbose output
./vendor/bin/phpunit --testsuite=integration --testdox
```

If WordPress is not set up, integration tests will be **skipped** (not failed) with a notice. Unit tests always run regardless.

#### Generate Code Coverage Report

Requires Xdebug or PCOV installed and enabled. Generates an HTML report in `coverage/`.

```bash
composer test:coverage

# Direct
./vendor/bin/phpunit --coverage-html coverage

# Open the report
open coverage/index.html   # macOS
xdg-open coverage/index.html  # Linux
```

The coverage report shows which lines, branches, and functions are covered by tests. A file is listed under "CPT" (Covered Per Test) if all its executable lines are hit.

#### Run Tests with Random Order (detect hidden dependencies)

```bash
# Randomize test order to catch hidden inter-test dependencies
./vendor/bin/phpunit --testsuite=unit --random-order

# Seed the randomizer for reproducible results
./vendor/bin/phpunit --testsuite=unit --random-order --random-order-seed=20240501
```

#### Run Tests with Stop-on-First-Failure

```bash
./vendor/bin/phpunit --testsuite=unit --stop-on-failure
```

Useful when you just broke something and don't want to wait for the full suite.

#### Run Tests Excluding One File

```bash
./vendor/bin/phpunit --testsuite=unit --exclude-group=slow
```

Groups are declared in test files with `@group` annotations (add `@group slow` to long-running tests).

---

### Test Architecture Explained

#### Unit Tests (`tests/phpunit/unit/`)

Each file tests one class in isolation. All WordPress/WooCommerce functions are **mocked** in `tests/bootstrap.php`.

**What is mocked:**
- All WordPress functions: `sanitize_text_field()`, `wp_verify_nonce()`, `current_user_can()`, `get_option()`, `wp_remote_post()`, etc.
- WooCommerce functions: `wc_get_product()`, `wc_get_order()`, `WC()->cart`, etc.
- The `WP_Error` class (stub implementation)
- HTTP responses (`wp_remote_post()` returns a mock Printful API response)
- File system (`wp_upload_dir()` returns `/tmp/pod-aggregator-test-uploads/`)

**What is NOT mocked:**
- Plain PHP: `json_encode()`, `file_put_contents()`, `sys_get_temp_dir()`, `imagecreatetruecolor()`, etc.
- The plugin's own classes — they are loaded via Composer's PSR-4 autoloader and tested for real logic.

**Mock example (from `tests/bootstrap.php`):**

```php
// Mock wp_remote_post to return a predictable Printful response
if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []) {
        return [
            'body'     => json_encode(['result' => ['id' => 'mock_order_123']]),
            'response' => ['code' => 200],
        ];
    }
}
```

#### Integration Tests (`tests/phpunit/integration/`)

These tests run against a **real WordPress database**. They:
- Create actual CPT posts (`wp_insert_post()` hits the DB)
- Make real HTTP calls to Printful (or mocked if no API key)
- Exercise the full stack: plugin → WordPress → WooCommerce → database

The `tests/bootstrap.php` file detects whether it's running against the real WP test library or the mock bootstrap. When the real WordPress test library is available (via `WP_TESTS_DIR`), integration tests use `WordPress\PHPUnit\Polyfills\IntegrationTestCase` as the base class.

**Current status:** The `tests/phpunit/integration/` directory is empty. To write integration tests, see "Writing Integration Tests" below.

#### The Bootstrap File (`tests/bootstrap.php`)

The bootstrap file is the entry point for every PHPUnit run. It loads in this order:

1. **Defines constants** (`ABSPATH`, `WPINC`, `WP_TESTS_MULTISITE`)
2. **Loads Composer's autoloader** (`vendor/autoload.php`) — this registers all plugin namespaces
3. **Defines WordPress function mocks** — `if (!function_exists('func_name'))` pattern ensures real WP functions take precedence when available

Key mocks to know:

| Mock | What it does |
|------|-------------|
| `wp_generate_uuid4()` | Returns a deterministic UUID string for tests |
| `wp_remote_post()` | Returns `['result' => ['id' => 'mock_order_123']]` |
| `wp_upload_dir()` | Returns `/tmp/pod-aggregator-test-uploads/` (no real filesystem writes) |
| `get_site_option()` / `update_site_option()` | In-memory static array — no DB |
| `current_user_can()` | Always returns `true` (bypasses capability checks in tests) |
| `WP_Error` class | Minimal stub implementing `get_error_code()`, `get_error_message()`, `get_error_data()`, `add()` |

---

### PHPUnit Configuration Deep Dive (`phpunit.xml.dist`)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         backupGlobals="false"
         beStrictAboutOutputDuringTests="true"
         colors="true"
         cacheResult="true"
         executionOrder="depends,defects"
         beStrictAboutTodoAnnotatedTests="true"
         failOnRisky="true"
         failOnWarning="true">
```

| Attribute | Value | What it means |
|-----------|-------|---------------|
| `bootstrap` | `tests/bootstrap.php` | Loaded before any tests — sets up mocks |
| `backupGlobals` | `false` | Don't backup/restore global variables between tests (safer for WP) |
| `beStrictAboutOutputDuringTests` | `true` | Any `echo`/`print` in a test fails it — keeps tests clean |
| `colors` | `true` | Colored output in terminal |
| `cacheResult` | `true` | Cache test results between runs (faster subsequent runs) |
| `executionOrder` | `depends,defects` | Run tests in dependency order; previously-failed tests run first |
| `failOnRisky` | `true` | Fail tests that produce side effects (e.g., echo without asserting) |
| `failOnWarning` | `true` | Treat PHP warnings as test failures |

```xml
    <testsuites>
        <testsuite name="unit">
            <directory suffix=".php">tests/phpunit/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix=".php">tests/phpunit/integration</directory>
        </testsuite>
    </testsuites>
```

Two separate test suites let you run unit tests without needing WordPress, and integration tests only when a WP environment is available.

```xml
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">includes</directory>
            <directory suffix=".php">admin</directory>
            <directory suffix=".php">public</directory>
        </include>
        <exclude>
            <!-- Exclude the Printful adapter — it makes live API calls -->
            <directory suffix=".php">includes/providers/class-printful.php</directory>
        </exclude>
    </coverage>
```

Coverage excludes `class-printful.php` because it makes live network calls to Printful's API. Tests for the Printful adapter should mock HTTP responses (done in the adapter test via `tests/bootstrap.php` mocks).

```xml
    <php>
        <env name="WP_TESTS_MULTISITE" value="1"/>
        <env name="WP_PLUGIN_DIR" value="/tmp/pod-aggregator-tests"/>
        <ini name="display_errors" value="1"/>
        <ini name="error_reporting" value="-1"/>
    </php>
```

Environment variables set for every test run. `WP_TESTS_MULTISITE=1` activates multisite mock mode. `error_reporting=-1` enables all PHP warnings as errors (strict mode).

---

### Writing New Unit Tests

#### Test File Naming Convention

```
tests/phpunit/unit/class-{slug}-test.php
```

Where `{slug}` is the kebab-case name of the class being tested:
- `class-print-generator.php` → `class-print-generator-test.php`
- `class-design-element.php` → `class-design-element-test.php`

#### Test Class Template

```php
<?php
/**
 * Unit tests for Class_Name.
 *
 * @package POD_Aggregator\Tests\Unit
 */

namespace POD_Aggregator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use POD_Aggregator\ProductCustomizer\Class_Name;

class Class_Name_Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Setup
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        // Optional: set up per-test fixtures
    }

    protected function tearDown(): void
    {
        // Optional: clean up per-test fixtures
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // 1. Method existence tests
    // -------------------------------------------------------------------------

    public function testMethodExists(): void
    {
        $this->assertTrue(method_exists(Class_Name::class, 'methodName'));
    }

    public function testStaticMethodExists(): void
    {
        $this->assertTrue(method_exists(Class_Name::class, 'staticMethod'));
    }

    public function testConstantIsDefined(): void
    {
        $this->assertSame('expected_value', Class_Name::CONSTANT_NAME);
    }

    // -------------------------------------------------------------------------
    // 2. Constructor / instantiation tests
    // -------------------------------------------------------------------------

    public function testConstructorAcceptsArray(): void
    {
        // Most POD Aggregator classes use array-based constructors
        $obj = new Class_Name(['key' => 'value', 'flag' => true]);

        $this->assertInstanceOf(Class_Name::class, $obj);
    }

    // -------------------------------------------------------------------------
    // 3. Happy-path tests (correct inputs → correct output)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // 4. Error/edge-case tests
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // 5. Private method tests (via ReflectionMethod)
    // -------------------------------------------------------------------------

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

#### Testing Private Methods

Use `\ReflectionMethod` to test private method logic without exposing it publicly:

```php
public function testPrivateSanitizeSettingsStripsInvalidMarkup(): void
{
    $refl = new \ReflectionMethod(Settings::class, 'sanitize_settings');
    $refl->setAccessible(true);

    $settings = new Settings();
    $result = $refl->invoke($settings, ['printful_default_markup' => '999']);

    // The method clamps markup to 500
    $this->assertLessThanOrEqual(500, $result['printful_default_markup']);
}
```

#### Testing With Mock Objects

For classes that depend on WordPress functions, use `\Mockery` or inline stubs:

```php
public function testDesignReturnsNullForMissingElement(): void
{
    $design = new Design(['product_id' => 1, 'elements' => []]);

    $element = $design->get_element_at(99);  // Out of range index

    $this->assertNull($element);
}
```

#### Common Assertions Reference

```php
// Value assertions
$this->assertSame($expected, $actual);       // === (type-sensitive)
$this->assertEquals($expected, $actual);     // == (type-converting)
$this->assertTrue($value);
$this->assertFalse($value);
$this->assertNull($value);
$this->assertContains($needle, $haystack);  // array/string contains value
$this->assertArrayHasKey($key, $array);
$this->assertCount($count, $array);
$this->assertInstanceOf(ClassName::class, $object);

// Numeric assertions
$this->assertGreaterThan($min, $actual);
$this->assertLessThanOrEqual($max, $actual);

// Exception assertions
$this->expectException(\InvalidArgumentException::class);
throw new \InvalidArgumentException('message');

// String assertions
$this->assertStringContainsString($needle, $actual);
$this->assertMatchesRegularExpression('/regex/', $actual);
```

---

### Writing Integration Tests

The `tests/phpunit/integration/` directory is currently empty. To add integration tests:

**Step 1 — Ensure the WordPress test library is installed:**

```bash
wp scaffold package-tests .
```

This installs `tests/bootstrap.php` that uses the real WordPress test library instead of the mock bootstrap.

**Step 2 — Create an integration test file:**

```php
<?php
/**
 * Integration tests for POD Aggregator plugin activation.
 *
 * Requires a live WordPress site with WooCommerce activated.
 * Extends the Yoast polyfills IntegrationTestCase for DB transaction rollback.
 *
 * @package POD_Aggregator\Tests\Integration
 */

namespace POD_Aggregator\Tests\Integration;

use Yoast\PHPUnitPolyfills\TestCases\IntegrationTestCase;

class Activation_Test extends IntegrationTestCase
{
    public function testCptIsRegisteredAfterActivation(): void
    {
        // Simulate plugin activation (calls the activation hook)
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

**Step 3 — Run integration tests:**

```bash
./vendor/bin/phpunit --testsuite=integration
```

---

### Code Style and Linting

#### Running the Linter

```bash
# Lint all plugin PHP files (PSR-12 standard)
composer lint

# Direct
./vendor/bin/phpcs --standard=PSR12 includes/ admin/ public/

# Show only errors (not warnings)
./vendor/bin/phpcs --standard=PSR12 --severity=error includes/

# Show the full file with all violations
./vendor/bin/phpcs --standard=PSR12 --report=full includes/class-loader.php
```

#### Auto-Fixing Style Issues

PHPCS can automatically fix many issues (whitespace, bracing, line length, etc.):

```bash
# Preview what would change (dry run)
./vendor/bin/phpbf --dry-run --standard=PSR12 includes/

# Actually fix them
./vendor/bin/phpbf --standard=PSR12 includes/

# Fix only a specific file
./vendor/bin/phpbf --standard=PSR12 includes/class-loader.php
```

Note: `phpbf` (PHP Code Beautifier) cannot fix everything — complex logic style issues must be fixed manually.

#### Configuring PHPCS Per-Project

Create a `phpcs.xml` in the plugin root to customise rules:

```xml
<?xml version="1.0"?>
<ruleset name="POD Aggregator">
    <description>Custom coding standards for POD Aggregator</description>

    <!-- Include WordPress rules -->
    <rule ref="WordPress"/>

    <!-- Exclude specific sniffs that are too strict -->
    <rule ref="WordPress.WhiteSpace.ControlStructureSpacing">
        <properties>
            <property name="blank_lines_check" value="0"/>
        </properties>
    </rule>

    <!-- Include our source directories -->
    <file>admin/</file>
    <file>includes/</file>
    <file>public/</file>

    <!-- Exclude test mocks and build artifacts -->
    <exclude-pattern>tests/</exclude-pattern>
    <exclude-pattern>vendor/</exclude-pattern>
</ruleset>
```

---

### Pre-Commit Hooks (Optional but Recommended)

Run this once after cloning to add a pre-commit hook that blocks commits if tests or lint fail:

```bash
# Install the hook
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
echo "Pre-commit hook installed"
```

Now every `git commit` automatically runs unit tests and linting. If either fails, the commit is blocked.

To bypass the hook temporarily (emergency commits):

```bash
git commit --no-verify -m "Emergency: pushing without tests"
```

---

### WP-CLI Development Commands

#### Sync Products

```bash
# Sync all POD products
wp pod-aggregator sync

# Sync a specific product
wp pod-aggregator sync --product_id=123

# Force full catalog refresh
wp pod-aggregator sync --refresh-catalog

# Dry run (shows what would sync)
wp pod-aggregator sync --dry-run

# Clear stuck sync lock
wp pod-aggregator sync --unlock
```

#### Manage Designs

```bash
# List all saved designs
wp pod-aggregator design list

# Delete a design by UUID
wp pod-aggregator design delete a1b2c3d4-e5f6-7890-abcd-ef1234567890

# Export a design as JSON
wp pod-aggregator design export a1b2c3d4-e5f6-7890-abcd-ef1234567890 > design.json
```

#### Cron Management

```bash
# List scheduled cron events
wp cron event list

# Run the sync event immediately
wp cron event run pod_aggregator_hourly_sync

# Run all due events
wp cron event run --all

# Delete all scheduled sync events
wp cron event delete pod_aggregator_hourly_sync
```

---

### Debugging Tests

#### Run a Single Test with Full Output

```bash
./vendor/bin/phpunit \
  --testsuite=unit \
  --filter="testSanitizeClampsMarkupTo500" \
  --testdox \
  -v
```

#### Inspect a Failing Test

```bash
# Run with stack traces
./vendor/bin/phpunit --testsuite=unit --filter="testMethod" --stop-on-failure -vvv
```

#### Add a Debug Print Inside a Test

```php
public function testSomething(): void
{
    $design = new Design(['product_id' => 1]);
    fwrite(STDERR, "DEBUG: " . print_r($design->to_array(), true) . "\n");
    // or
    $this->assertTrue(true, "DEBUG: custom debug output");
}
```

#### Check What Functions Are Mocked

Add this to the top of any test to see if a function is mocked or real:

```php
public function testCheckFunctionMock(): void
{
    // Check if wp_verify_nonce is mocked (returns 1) or real
    $result = wp_verify_nonce('test_nonce', 'test_action');
    $this->assertSame(1, $result);  // Mocked bootstrap always returns 1
}
```

---

### Troubleshooting Test Failures

#### "Class 'POD_Aggregator\...' not found"

**Cause:** The Composer autoloader wasn't generated.

**Fix:**
```bash
composer install
# or regenerate the autoloader:
composer dump-autoload
```

#### "PHP Fatal error: Class 'WP_Error' not found"

**Cause:** `tests/bootstrap.php` defines `WP_Error` only if WordPress doesn't already define it. If WordPress is loaded but `WP_Error` isn't available (old WP version), the mock should kick in.

**Fix:** Ensure `ABSPATH` is not set to a real WordPress installation when running unit tests. The mock bootstrap should be used.

#### "PHPUnit runs but all tests are skipped"

**Cause:** The `tests/bootstrap.php` might be detecting a real WordPress test environment and trying to use integration test infrastructure.

**Fix:** Check that `WP_TESTS_DIR` is not set in your environment:
```bash
echo $WP_TESTS_DIR  # Should be empty for unit tests
./vendor/bin/phpunit --testsuite=unit
```

#### "Code coverage report is empty"

**Cause:** Xdebug or PCOV is not enabled.

**Fix:**
```bash
# Check if Xdebug is loaded
php -m | grep -i xdebug

# If not, enable it in php.ini
# For Xdebug 3:
# zend_extension=xdebug
# xdebug.mode=coverage

# Or use PCOV (faster, lighter):
# pecl install pcov && echo "extension=pcov.so" >> php.ini
```

#### "Tests pass locally but fail in CI"

**Cause:** Different PHP versions or missing extensions.

**Fix:** Check the CI environment's PHP version:
```bash
php --version
php -m | grep -E "gd|mbstring|curl"
```

Also check that `composer install` was run with the correct PHP:
```bash
which php  # Should point to the right PHP version
composer install --no-interaction
```

---

### Understanding the Plugin's Architecture

#### Why This Architecture?

| Design Decision | Reason |
|----------------|--------|
| PSR-4 namespaced classes in `includes/` | Avoids global function/constant collisions |
| Single bootstrap file `pod-aggregator.php` | WordPress only loads one main plugin file — keeps activation hooks simple |
| Value objects (`Design`, `Design_Element`) | Immutable, easy to test, no hidden state |
| Provider interface (`class-pod-provider.php`) | Makes adding Printify/Gelato a matter of implementing one interface |
| CPT for designs (`pod_design`) | Keeps design data independent of WooCommerce order data |
| Settings API (not custom options page) | Leverages WordPress's built-in security (nonces, capabilities, sanitization) |

#### Request Lifecycle (Frontend)

```
HTTP Request
    ↓
wp-load.php (WordPress bootstrap)
    ↓
pod-aggregator.php (plugin loaded via autoloader)
    ↓
class-loader.php (registers all add_action / add_filter)
    ↓
Shortcode [pod_customizer] rendered by public/class-customizer-editor.php
    ↓
AJAX calls → class-ajax.php (nonce-verified, capability-checked)
    ↓
Design saved via REST API → product-customizer/class-rest-controller.php
    ↓
Design persisted to pod_design CPT → class-design-storage.php
```

#### Request Lifecycle (Checkout)

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

### Contributing Workflow

#### Step 1 — Fork and Clone

```bash
# Fork on GitHub, then:
git clone https://github.com/YOUR_USERNAME/pod-aggregator.git
cd pod-aggregator
git remote add upstream https://github.com/gringemini-ship-it/pod-aggregator.git
```

#### Step 2 — Create a Feature Branch

```bash
# Always branch from master
git checkout master
git pull upstream master
git checkout -b feature/add-printify-adapter
```

#### Step 3 — Make Changes

Write your code, update or add tests, run the linter.

#### Step 4 — Run All Checks Before Pushing

```bash
# 1. Unit tests (must pass)
composer test:unit

# 2. Linting (must pass)
composer lint

# 3. Coverage check (look for uncovered files)
composer test:coverage
# Open coverage/index.html and check your changed files

# 4. Dry-run the build script
./scripts/build.sh check
```

#### Step 5 — Commit with a Clear Message

```bash
git add .
git commit -m "Add Printify adapter (feature branch)

- Implement Printify_Adapter extending POD_Provider
- Add printify-api-key field to Settings page
- Add integration tests for variant sync
- Update README with Printify setup instructions
- Fix: Closes #XX
"
```

#### Step 6 — Push and Open a Pull Request

```bash
git push origin feature/add-printify-adapter
```

Then open a Pull Request on GitHub. Reference any issues with `Fixes #XX` or `Closes #XX`.

#### Pull Request Checklist

Before opening a PR, confirm:

- [ ] `composer test:unit` passes
- [ ] `composer lint` passes (or deviations documented and justified)
- [ ] New behavior has unit tests
- [ ] New behavior has integration tests (if applicable)
- [ ] README is updated if user-facing behavior changed
- [ ] No debug code (`var_dump`, `error_log`, `console.log`) left in source
- [ ] No new files in `vendor/` or build artifacts committed

#### Code Review Expectations

PRs are reviewed for:
- **Correctness** — Does it solve the stated problem?
- **Security** — Nonces, capabilities, sanitization, escaping?
- **Performance** — Any N+1 queries, unnecessary DB writes, or heavy operations in hot paths?
- **Test coverage** — Are the right things tested, not just the easy things?
- **Style** — Follows the existing patterns in the codebase?

---

### Release Process

#### Step 1 — Version Bump

```bash
# Check current version in pod-aggregator.php
grep "Version:" pod-aggregator.php
# Update it: 1.2.3 → 1.3.0
```

#### Step 2 — Run Full Test Suite

```bash
composer test          # All unit + integration tests
composer test:coverage # Review coverage
composer lint          # No violations
```

#### Step 3 — Build the Release ZIP

```bash
# Validate (syntax + PHPCS, no ZIP)
./scripts/build.sh check

# Build ZIP for version 1.3.0
./scripts/build.sh 1.3.0
```

Output: `pod-aggregator.zip` and `pod-aggregator/trunk/` staged directory.

#### Step 4 — Test the Built ZIP

```bash
# Install the ZIP locally (or on a staging site)
wp plugin install ./pod-aggregator.zip --activate-network

# Verify activation
wp plugin list --status=active | grep pod-aggregator

# Run a quick smoke test
wp pod-aggregator sync --dry-run
```

#### Step 5 — Tag and Release

```bash
# Tag the release in git
git tag -a v1.3.0 -m "Release v1.3.0 — Add Printify adapter"
git push upstream v1.3.0

# On GitHub: draft a new Release, attach pod-aggregator.zip
# On wordpress.org: upload pod-aggregator.zip to the SVN repository
```

---

### Useful Composer Scripts Reference

| Command | What it does |
|---------|-------------|
| `composer test` | Run PHPUnit (all suites) |
| `composer test:unit` | Run unit tests only |
| `composer test:integration` | Run integration tests only |
| `composer test:coverage` | Run with HTML coverage report |
| `composer lint` | Run PHPCS with PSR-12 |
| `composer dump-autoload` | Regenerate PSR-4 autoloader |

These are defined in `composer.json` under `"scripts"`. Edit `composer.json` to add new scripts.

---

## Installation — Customers

### Prerequisites Checklist

Before starting, make sure you have:

- [ ] A self-hosted WordPress site (WordPress.com Business+ also works)
- [ ] WooCommerce installed and configured (with payment gateway set up)
- [ ] A Printful account with API access — [sign up free at printful.com](https://www.printful.com)
- [ ] PHP 7.4+ with the `gd` extension enabled (ask your host if unsure)

### Step-by-Step Install

#### Step 1: Install the Plugin

**Option A — From WordPress Admin (Single Site):**

1. Download the `pod-aggregator.zip` from the release page.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Click **Choose File**, select the ZIP, click **Install Now**.
4. Click **Activate Plugin**.

**Option B — From WordPress Admin (Multisite / Network):**

1. Go to **My Sites → Network Admin → Plugins**.
2. Click **Add New** at the top.
3. Upload the ZIP or use SFTP to place the folder in `/wp-content/plugins/`.
4. Click **Network Activate**.

**Option C — WP-CLI:**

```bash
wp plugin install /path/to/pod-aggregator.zip --activate-network
```

#### Step 2: Get Your Printful API Key

1. Log in to Printful at [printful.com](https://www.printful.com).
2. Click your avatar (top-right) → **Stores**.
3. Click the store you want to connect → **API**.
4. Click **Get API token**.
5. Copy the entire token string — it looks like: `XXXXX_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

#### Step 3: Enter the API Key in WordPress

**Single-site:** Go to **Settings → POD Aggregator**.

**Multisite:** Go to **My Sites → Network Admin → Settings** and scroll to "POD Aggregator".

Fill in:

| Field | Value |
|-------|-------|
| **Printful API Key** | Paste the token from Step 2 |
| **Default Markup %** | e.g., `30` (leave at `0` for no markup) |
| **Sync Interval (hours)** | `0` for manual-only, `1` for hourly |

Click **Save Changes**.

#### Step 4: Verify the Connection

1. Go to **POD Aggregator → Import Products**.
2. You should see Printful's product catalog loading.
3. If you see an error like "Invalid API key", double-check the key in Settings.
4. If you see categories and products, the connection is working.

---

## Setup Guide

### 1. Connect Printful

Navigate to **Settings → POD Aggregator → Printful Settings** (or **Network Admin → Settings** on multisite).

| Field | Description | Default |
|-------|-------------|---------|
| **Printful API Key** | Your Printful private token from printful.com/dashboard?window=api | (empty) |
| **Default Markup %** | Applied to all products that don't have a per-product override | `0` |
| **Webhook Secret** | Shared secret for verifying Printful webhook signatures | (auto-generated) |
| **Sync Interval (hours)** | `0` = manual only; `1` = every hour; `24` = daily | `0` (manual) |
| **Debug Mode** | Enable to log all Printful API requests to the error log | Off |

To get your API key: Printful Dashboard → Stores → [your store] → API → Get API token.

### 2. Import Products

Go to **POD Aggregator → Import Products**.

**How to browse:**

1. The page loads Printful's full catalog, grouped by category (T-shirts, Hoodies, Mugs, etc.).
2. Use the **Search** box to filter by keyword (e.g., "t-shirt", "hoodie").
3. Use the **Category** dropdown to narrow the view.
4. Each product card shows: thumbnail, product name, base price, and variant count.

**How to import a product:**

1. Click **Import** on any product card.
2. A modal appears asking for:
   - **Product Name** (pre-filled from Printful — edit if desired)
   - **Short Description** (pre-filled)
   - **Images** (pulled from Printful — you can add more later)
   - **Regular Price** (Printful base cost + your markup)
3. Click **Import Product**.
4. The plugin creates a WooCommerce product under **WooCommerce → Products**.
5. A corresponding `pod_product` CPT entry is created to store design data.

**Import multiple products:**

- Browse the catalog and click **Import** on as many products as you want.
- They are queued and imported one by one.
- Refresh the page to see the growing list of imported products.

**What is the "pod_product" CPT?**

It's a hidden Custom Post Type (`show_ui=false`, `show_in_nav_menus=false`) that stores the POD-specific data — Printful variant ID, base cost, print areas, and design records — separately from the WooCommerce product post. This keeps WooCommerce's product data clean and compatible with other WC plugins.

### 3. Configure Pricing Markup

There are **three levels** of markup, applied in priority order (highest to lowest):

**Level 1 — Per-product (highest priority):**

1. Go to **WooCommerce → Products**.
2. Click to edit an imported POD product.
3. Scroll to the **POD Product** panel (tab).
4. Set **Markup Percentage** (e.g., `30` = 30% above Printful base cost).
5. Click **Update**.

**Level 2 — Network default (fallback):**

1. Go to **Network Admin → Settings → POD Aggregator**.
2. Set **Default Markup %**.
3. This applies to all POD products that don't have a per-product override.

**Level 3 — No markup:**

If neither Level 1 nor Level 2 is set, the product sells at Printful's base cost.

### 4. Enable Product Customizer

The product customizer appears automatically on any WooCommerce product that has POD data.

To verify a product is POD-enabled:

1. Edit the WooCommerce product.
2. In the **POD Product** panel, check **Enable POD Product**.
3. Click **Update**.

Now when customers visit that product's page, they see the **Customize** section below the Add to Cart button.

---

## Using the Product Customizer

### Design Editor Layout

The design editor appears as a block on the product detail page. It has four regions:

```
┌─────────────────────────────────────────────────────────┐
│  [Toolbar: Add Text] [Add Image] [Preview] [Save]      │
├───────────────────────────────┬───────────────────────┤
│                               │  Element List          │
│      Canvas                   │  ─────────────────     │
│  (shows printable area)      │  • Text: "Hello"   [✎] │
│                               │  • Image: logo.png [✎]│
│                               │                       │
├───────────────────────────────┴───────────────────────┤
│  [Property Panel — appears when element is selected]    │
│  Text: [Hello World      ]  Font: [Arial      ▼]       │
│  Size: [24]pt  Color: [#FF0000]  Bold [✓] Italic [ ]  │
└─────────────────────────────────────────────────────────┘
```

**Canvas** — Shows the printable area for the selected print area (front/back/side). The light-gray background represents the product; the white area is the printable zone.

**Toolbar** — Buttons for adding elements, generating a preview, and saving the design.

**Element List (right sidebar)** — Lists every element on the current print area. Click any element to select it. Click the pencil icon (✎) to edit properties. Click the trash icon to delete.

**Property Panel** — Appears below the canvas when an element is selected. Shows all editable properties for the selected element.

### Adding Text Elements

**Step 1 — Add the element:**

1. In the toolbar, click **Add Text**.
2. A new text element appears centered on the canvas with placeholder text: `"Your text here"`.
3. The element is automatically selected, so the Property Panel appears.

**Step 2 — Edit text content:**

1. Click directly on the text element on the canvas, or use the Property Panel.
2. Type your desired text in the **Text** field.
3. Press **Enter** or click outside the text field to apply.

**Step 3 — Style the text:**

In the Property Panel:

| Property | Options / Range | Notes |
|----------|----------------|-------|
| **Font Family** | Arial, Helvetica, Times New Roman, Georgia, Courier New | Click to select |
| **Font Size** | 8–72 pt | Type a number |
| **Font Color** | Hex color (e.g., `#FF0000`) | Click the color swatch to open picker |
| **Bold** | Toggle on/off | Keyboard: **Ctrl+B** |
| **Italic** | Toggle on/off | Keyboard: **Ctrl+I** |
| **Alignment** | Left, Center, Right, Justify | Icon buttons |
| **Print Area** | Front, Back, Left, Right | Changes which canvas view is shown |

**Step 4 — Position and resize on canvas:**

- **Move:** Click and drag the element anywhere on the canvas.
- **Resize:** Click a corner handle and drag. Hold **Shift** to maintain aspect ratio.
- **Rotate:** Enter a degree value in the **Rotation** field in the Property Panel (e.g., `15` for 15° clockwise).

**Step 5 — Repeat for other print areas:**

Use the **Print Area** dropdown in the Property Panel to switch between front/back/left/right. Each print area stores its own set of elements independently.

### Adding Image Elements

**Step 1 — Add the element:**

1. In the toolbar, click **Add Image**.
2. WordPress's media library picker opens.

**Step 2 — Select or upload an image:**

- Click **Media Library** to use an image already in your WordPress media library.
- Click **Upload Files** to upload a new image from your computer.
- Click the image to select it, then click **Choose**.

**Accepted formats:** JPEG, PNG, GIF, WebP. Maximum size: 5 MB.

**Step 3 — Position and resize:**

- Drag the image to position it within the printable area.
- Drag corner handles to resize.
- Hold **Shift** while resizing to lock the aspect ratio.

**Step 4 — Style in the Property Panel:**

| Property | Description |
|----------|-------------|
| **Print Area** | Front, Back, Left, Right |
| **Opacity** | 10%–100% (slider) |
| **Lock Aspect Ratio** | Toggle — prevents accidental distortion |

**Step 5 — Cropping notes:**

The editor does not have a built-in crop tool. Crop your image to the desired aspect ratio before uploading, or resize it using the corner handles while holding **Shift** to maintain proportions.

### Managing Elements

| Action | How |
|--------|-----|
| **Select element** | Click on it on the canvas, or click its entry in the Element List |
| **Move element** | Drag it on the canvas |
| **Resize element** | Drag a corner handle |
| **Delete element** | Select it, then click the trash icon in the Element List |
| **Duplicate element** | Select it, then click the copy icon (appears on hover) |
| **Change z-order** | Drag elements up/down in the Element List to change stacking order |
| **Deselect** | Click on an empty area of the canvas |

**Keyboard shortcuts:**

| Key | Action |
|-----|--------|
| **Delete / Backspace** | Delete selected element |
| **Ctrl+A** | Select all elements on current print area |
| **Ctrl+D** | Deselect |
| **Arrow keys** | Nudge selected element 1px |
| **Shift+Arrow** | Nudge selected element 10px |

### Preview & Save

**Generate a preview:**

1. Click **Preview** in the toolbar.
2. The plugin renders a PNG of the current print area at 72 DPI.
3. The preview image appears above the Add to Cart button.
4. This does NOT save the design — it only generates a preview image.

**Save the design:**

1. Click **Save** in the toolbar.
2. If the customer is logged in, the design is saved to their account.
3. If not logged in, they are prompted to log in or register first.
4. A design ID (UUID) is generated and stored in the cart item meta.

**Add to Cart without saving:**

Customers can click **Add to Cart** directly without saving. The design data is stored in the cart item meta and submitted with the order.

**Design persistence across sessions:**

- Saved designs are stored in the `pod_design` CPT linked to the customer user ID.
- Logged-in customers can revisit the product page and load their saved design to make changes before ordering again.
- Guest designs are stored in cart meta only and are lost after checkout.

---

## Cart & Checkout

### Cart Display

When a customer adds a POD product to the cart, the cart line item shows:

- **Product name** (unchanged)
- **Customization line** — e.g., `"Customization: Hello World"` or `"Customization: Image uploaded"`
- **Design thumbnail** (if a preview was generated) — a small 80×80px preview image

### Cart Item Meta

These meta keys are attached to each cart line item containing a POD product:

| Meta Key | Value | Example |
|----------|-------|---------|
| `_pod_enabled` | `"1"` if POD is enabled | `"1"` |
| `_pod_provider` | Provider slug | `"printful"` |
| `_pod_variant_id` | Provider's variant ID | `"12049-M"` |
| `_pod_design_data` | JSON-encoded design elements | `{"elements":[...]}` |
| `_pod_design_uuid` | Unique design UUID | `"a1b2c3d4-..."` |
| `_pod_design_thumb` | URL of design preview PNG | `"https://..."` |
| `_pod_design_name` | Human-readable design name | `"My T-shirt Design"` |
| `_pod_print_area` | Which areas are customized | `"front,back"` |

You can see these in WooCommerce by enabling **Screen Options → Order Data → Custom Fields**.

### Checkout Flow

1. Customer completes WooCommerce checkout.
2. WooCommerce fires `checkout_order_processed`.
3. For each cart line item with `_pod_enabled = "1"`, the plugin calls `submit_order_to_provider()`.
4. The order is sent to Printful via the Printful REST API with:
   - Customer shipping address
   - Line item with design data and print file URL
   - `print_area` for each customized area
5. Printful returns a Printful order ID (e.g., `"PF-123456"`).
6. The plugin stores this ID in WooCommerce order meta: `_pod_printful_order_id`.
7. A note is added to the WooCommerce order: `"Order submitted to Printful. Printful Order ID: PF-123456"`.

### Order Confirmation Page

After checkout, the WooCommerce order confirmation page shows the design customization summary (same as cart). The order email also includes the customization line items.

### Order Status Transitions

| WooCommerce Status | Triggered By |
|-------------------|-------------|
| `pending` | Order created, awaiting payment |
| `processing` | Printful confirms `order_approved` webhook |
| `completed` | Printful ships the order (via `order_shipped` webhook) |
| `refunded` | Printful sends `order_refund` or `order_partial_refund` webhook |
| `cancelled` | Printful sends `order_cancelled` webhook |

---

## Sync & Cron Jobs

### Manual Sync via WP-CLI

WP-CLI must be installed on the server. Most managed WordPress hosts (Flywheel, WP Engine, etc.) provide it automatically.

```bash
# Sync all imported POD products (refresh prices and stock from Printful)
wp pod-aggregator sync

# Sync a specific product only
wp pod-aggregator sync --product_id=1234

# Force a full catalog refresh (re-imports the entire Printful catalog — slow)
wp pod-aggregator sync --refresh-catalog

# Dry-run (shows what would be synced without making changes)
wp pod-aggregator sync --dry-run

# Clear the sync lock (if a previous sync was interrupted)
wp pod-aggregator sync --unlock
```

**Exit codes:** `0` = success, `1` = error. Useful for scripting:

```bash
wp pod-aggregator sync && echo "Sync succeeded" || echo "Sync failed"
```

### Scheduled Auto-Sync

When **Sync Interval** is set to a value greater than `0` in Settings, a WP-Cron job is scheduled automatically.

| Cron Schedule | Interval Setting | When It Runs |
|--------------|-----------------|-------------|
| `pod_aggregator_hourly_sync` | `1` hour | Every hour |
| `pod_aggregator_daily_sync` | `24` hours | Once per day |
| `pod_aggregator_twicedaily` | `12` hours | Twice per day |

**Manually trigger the cron job (without waiting):**

```bash
# Run the hourly sync immediately
wp cron event run pod_aggregator_hourly_sync

# Run all due cron events
wp cron event run --all
```

**Disable auto-sync (use only WP-CLI/manual):**

Set **Sync Interval** to `0` in Settings. This stops the WP-Cron schedule from being set.

### Order Status Sync

A second cron schedule (`pod_aggregator_process_webhooks`) runs every 15 minutes to process the Printful webhook queue:

1. The transient `pod_aggregator_webhook_queue` stores incoming webhook events that arrived while WordPress was busy.
2. The cron job processes each queued event in order.
3. Events update WooCommerce order statuses accordingly.

**Why use a queue?** Printful may send webhooks while your site is under heavy load and the PHP process is busy. The queue ensures no events are lost.

---

## REST API

### Authentication

All REST endpoints require authentication. Choose one method:

**Method A — WordPress Cookie Nonce (JavaScript clients / same-origin):**

```javascript
// In your JavaScript, get the nonce from WordPress
const nonce = await fetch('/wp-json/pod-aggregator/v1/nonce').then(r => r.json());
// or from a localized script variable if admin scripts enqueued it
```

Pass it as a header:

```javascript
headers: {
  'X-WP-Nonce': nonce,
  'Content-Type': 'application/json'
}
```

**Method B — Application Password (external clients / scripting):**

1. In WordPress admin, go to **Users → Profile → Application Passwords**.
2. Create a new application password for your integration (name it e.g., "POD API").
3. Use it in the Authorization header:

```bash
curl -u "username:application-password-here" \
  https://yoursite.com/wp-json/pod-aggregator/v1/design
```

**Method C — Cookie (browser, logged-in users only):**

For AJAX calls made from the browser when the user is logged in, WordPress handles authentication automatically.

### Save Design

Create or update a design for a product.

```
POST /wp-json/pod-aggregator/v1/design
```

**Request body (JSON):**

```json
{
  "product_id": 123,
  "design_id": "optional-existing-uuid-to-update",
  "elements": [
    {
      "type": "text",
      "text": "Hello World",
      "position_x": 100,
      "position_y": 200,
      "width": 300,
      "height": 100,
      "rotation": 0,
      "font_size": 24,
      "font_color": "#FF0000",
      "font_family": "Arial",
      "font_bold": true,
      "font_italic": false,
      "alignment": "center",
      "print_area": "front"
    },
    {
      "type": "image",
      "image_source": "https://example.com/logo.png",
      "position_x": 300,
      "position_y": 150,
      "width": 200,
      "height": 200,
      "rotation": 0,
      "opacity": 100,
      "print_area": "front"
    }
  ]
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "post_id": 456
}
```

**With curl:**

```bash
curl -X POST https://yoursite.com/wp-json/pod-aggregator/v1/design \
  -u "username:app-password" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 123,
    "elements": [{
      "type": "text",
      "text": "Hello World",
      "print_area": "front",
      "font_size": 24,
      "font_color": "#FF0000",
      "font_family": "Arial"
    }]
  }'
```

### Load Design

Retrieve a saved design by its UUID.

```
GET /wp-json/pod-aggregator/v1/design/<design_id>
```

**Response (200 OK):**

```json
{
  "product_id": 123,
  "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "status": "draft",
  "elements": [
    {
      "type": "text",
      "text": "Hello World",
      "position_x": 100,
      "position_y": 200,
      "print_area": "front",
      "font_size": 24
    }
  ]
}
```

**With curl:**

```bash
curl https://yoursite.com/wp-json/pod-aggregator/v1/design/a1b2c3d4-e5f6-7890-abcd-ef1234567890 \
  -u "username:app-password"
```

### Delete Design

Permanently delete a saved design.

```
DELETE /wp-json/pod-aggregator/v1/design/<design_id>
```

**Response (200 OK):**

```json
{
  "success": true,
  "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

**With curl:**

```bash
curl -X DELETE https://yoursite.com/wp-json/pod-aggregator/v1/design/a1b2c3d4-e5f6-7890-abcd-ef1234567890 \
  -u "username:app-password"
```

### Generate Print File

Generate a 300 DPI print-ready PNG for a specific print area of a design. Used by WooCommerce checkout to attach the production print file to the order.

```
POST /wp-json/pod-aggregator/v1/design/<design_id>/print
```

**Request body (JSON):**

```json
{
  "product_width": 12,
  "product_height": 12,
  "print_area": "front",
  "dpi": 300
}
```

**Response:** PNG image binary with `Content-Type: image/png`.

**With curl (saves to file):**

```bash
curl -X POST https://yoursite.com/wp-json/pod-aggregator/v1/design/a1b2c3d4-e5f6-7890-abcd-ef1234567890/print \
  -u "username:app-password" \
  -H "Content-Type: application/json" \
  -d '{"product_width": 12, "product_height": 12, "print_area": "front", "dpi": 300}' \
  --output print-file.png
```

### Sync Products

Manually trigger a sync of imported WooCommerce products from Printful.

```
POST /wp-json/pod-aggregator/v1/sync
```

**Request body (JSON):**

```json
{
  "product_id": 123,
  "force": true
}
```

- `product_id` (optional): Sync only this WooCommerce product ID. Omit to sync all.
- `force` (optional, default false): Bypass the sync lock and force a fresh fetch.

**Response (200 OK):**

```json
{
  "success": true,
  "synced": 5,
  "failed": 0,
  "duration_seconds": 12.4
}
```

**With curl:**

```bash
curl -X POST https://yoursite.com/wp-json/pod-aggregator/v1/sync \
  -u "username:app-password" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123}'
```

---

## Webhook Reference

### Setting Up the Webhook in Printful

Printful sends webhook events to your site when order statuses change. Here's how to configure it:

**Step 1 — Find your webhook URL:**

Your webhook endpoint is always:
```
https://yoursite.com/wp-json/pod-aggregator/v1/webhook
```
Copy this URL.

**Step 2 — Add the webhook in Printful:**

1. Log in to Printful at [printful.com](https://www.printful.com).
2. Click your avatar → **Stores** → select your store.
3. Go to **Settings → Webhooks**.
4. Click **Add webhook**.
5. Paste your webhook URL into the **Payload URL** field.
6. Select these events:
   - `order_draft`
   - `order_created`
   - `order_approved`
   - `order_files_approved`
   - `order_partial_refund`
   - `order_refund`
   - `order_cancelled`
7. Click **Save**.
8. Copy the **Webhook Secret** shown on the page.

**Step 3 — Enter the secret in WordPress:**

1. Go to **Settings → POD Aggregator → Printful Settings**.
2. Paste the secret into **Webhook Secret**.
3. Click **Save Changes**.

**Step 4 — Test the webhook:**

In the Printful Webhooks settings, click **Send test event**. You should see a `200 OK` logged in WooCommerce → Tools → WooCommerce logs.

### Verifying the Webhook Signature

Every Printful webhook includes an `X-Printful-Signature` HTTP header containing an HMAC-SHA256 signature of the request body.

**How the plugin verifies it:**

```php
$payload  = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
$actual   = $_SERVER['HTTP_X_PRINTFUL_SIGNATURE'] ?? '';

if (!hash_equals($expected, $actual)) {
    // Reject the request — return 401
    status_header(401);
    exit('Signature verification failed');
}
```

If verification fails, the plugin returns `401 Unauthorized` and the event is **not processed**. This prevents spoofed webhook calls.

**To test manually:**

```bash
# Generate a signature
PAYLOAD='{"event":"order_created","order_id":"PF-123"}'
SECRET='your_webhook_secret'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

# Send a test webhook
curl -X POST https://yoursite.com/wp-json/pod-aggregator/v1/webhook \
  -H "X-Printful-Signature: sha256=${SIGNATURE}" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"
```

### Webhook Events Reference Table

| Printful Event | WooCommerce Action | Notes |
|---------------|-------------------|-------|
| `order_draft` | Log note to order | No status change |
| `order_created` | Add order note with Printful order ID | No status change |
| `order_approved` | Set order status to `processing` | Payment confirmed by Printful |
| `order_files_approved` | Add order note | All print files approved |
| `order_partial_refund` | Add refund note to order | Partial refund from Printful |
| `order_refund` | Set order status to `refunded` | Full refund from Printful |
| `order_cancelled` | Set order status to `cancelled` | Order cancelled in Printful |
| `order_shipped` | Set order status to `completed` + add tracking note | Not currently handled (future feature) |

---

## Multisite Support

### Network Activation

On WordPress Multisite, **always** use **Network Activate** (from **My Sites → Network Admin → Plugins**). This is required because:

- The `pod_product` CPT registration must happen at the network level to be available to all blogs.
- The REST API routes are registered network-wide.
- The Settings API values are stored as network options so all blogs share the same Printful API key.

**What happens on network activation:**
1. `register_activation_hook()` fires.
2. `flush_rewrite_rules()` is called to register the CPT rewrite rules.
3. Network options (`pod_aggregator_schema_version`, etc.) are set.
4. The CPT is registered for all blogs.

### Per-Blog Product Import

Each blog in the network has its own independent product catalog:

- Blog A imports T-shirt products from Printful.
- Blog B imports Mug products from Printful.
- Both use the same Printful API key but maintain separate WooCommerce products.
- The `pod_product` CPT posts for each blog are stored with that blog's ID.

**Syncing products for all blogs:**

The WP-CLI sync command syncs products for the current blog only. To sync all blogs, run it per-site:

```bash
for blog in $(wp site list --field=blog_id); do
  wp --blog=$blog pod-aggregator sync
done
```

### Cron in Multisite

WP-Cron is site-specific. Each blog's `pod_aggregator_hourly_sync` event fires according to that site's schedule.

**If you want a network-wide cron** (one event that syncs all blogs), use a network admin cron plugin like **WP Crontrol** or a server-level cron:

```bash
# Server-level cron (crontab) to trigger all blog syncs every hour
0 * * * * for blog in $(wp site list --field=blog_id); do wp --blog=$blog cron event run pod_aggregator_hourly_sync 2>/dev/null; done
```

---

## Uninstall

POD Aggregator is designed to leave no trace when deleted.

### Standard Uninstall (WordPress Plugin Page)

1. **Deactivate** the plugin from the Plugins page.
2. **Delete** the plugin from the Plugins page.

This triggers `uninstall.php` which runs the cleanup routine.

### What `uninstall.php` Removes

| Data Type | What Is Deleted |
|-----------|----------------|
| CPT posts | All `pod_product` posts and their post meta |
| CPT posts | All `pod_design` posts and their post meta |
| Network options | `pod_aggregator_schema_version` |
| Network options | `pod_aggregator_printful_api_key` |
| Network options | `pod_aggregator_printful_webhook_secret` |
| Network options | `pod_aggregator_sync_interval_hours` |
| Network options | `pod_aggregator_default_markup_percent` |
| Network options | `pod_aggregator_default_label` |
| Network options | `pod_aggregator_product_name_prefix` |
| Network options | `pod_aggregator_multisite_enabled` |
| Transients | `pod_aggregator_webhook_queue` |
| Transients | `pod_aggregator_sync_lock` |

### What Is NOT Deleted

| Data Type | Why It Stays |
|-----------|------------|
| WooCommerce orders | They are WooCommerce's data, not the plugin's |
| WooCommerce products | They remain in WooCommerce (without POD meta) |
| WordPress users | User accounts are never touched |
| WordPress media | Images uploaded via the customizer stay in the media library |
| Printful account data | Log into Printful to manage your store there |

### Hard Reset (Developer Use)

To delete all plugin data and start fresh:

```bash
# Via WP-CLI — delete all POD products and designs
wp post delete $(wp post list --post_type=pod_product --format=ids) --force 2>/dev/null
wp post delete $(wp post list --post_type=pod_design --format=ids) --force 2>/dev/null

# Delete all network options
wp site option delete pod_aggregator_schema_version
wp site option delete pod_aggregator_printful_api_key
# ... etc.

# Delete transients
wp transient delete pod_aggregator_webhook_queue
wp transient delete pod_aggregator_sync_lock
```

---

## Folder Structure

```
pod-aggregator/
├── pod-aggregator.php              # Main bootstrap file (plugin header, activation hooks)
├── uninstall.php                   # Clean uninstall (removes all plugin data)
├── composer.json                   # Dev dependencies (PHPUnit, PHPCS)
├── phpunit.xml.dist                # PHPUnit 9.x configuration
├── assets/
│   ├── screenshots/                # Admin and storefront screenshots
│   ├── css/
│   │   ├── admin.css              # Admin settings page styles
│   │   └── public.css            # Customizer and frontend styles
│   └── js/
│       ├── admin.js               # Admin settings page interactions
│       └── customizer-editor.js   # Frontend design editor (canvas, tools, sidebar)
├── admin/
│   ├── class-admin.php             # Admin menu registration, enqueue, AJAX handlers
│   ├── class-settings.php         # Network settings page (Settings API + sanitization)
│   └── class-preset-templates.php # Admin UI for pre-made design templates
├── includes/
│   ├── class-loader.php            # Registers all add_action/add_filter calls
│   ├── class-ajax.php             # AJAX endpoints for the customizer (save, load, preview)
│   ├── class-pod-provider.php     # Provider interface (interface + abstract base)
│   ├── class-cpt-registrar.php    # Registers pod_product and pod_design CPTs
│   ├── class-product-importer.php # Imports Printful catalog → WooCommerce products
│   ├── providers/
│   │   └── class-printful.php     # Printful API adapter (catalog, sync, order submission)
│   ├── WooCommerce/
│   │   └── class-integration.php  # WC cart, checkout, order line item hooks
│   ├── product-customizer/
│   │   ├── class-design.php        # Design value object (elements collection)
│   │   ├── class-design-element.php # Design_Element value object (single element)
│   │   ├── class-design-storage.php # CPT persistence (save/load/delete designs)
│   │   ├── class-print-generator.php # GD-based PNG rendering at 72/300 DPI
│   │   ├── class-rest-controller.php # Design REST API (save/load/delete/print)
│   │   └── class-customizer-editor.php # Shortcode [pod_customizer] renderer
│   ├── REST/
│   │   └── class-controller.php   # Product sync REST endpoints
│   └── Crons/
│       └── class-scheduler.php    # WP-Cron schedules and event handlers
├── public/
│   └── class-customizer-editor.php # Shortcode renderer (public-facing)
├── tests/
│   ├── bootstrap.php              # WP test environment + Composer autoloader
│   └── phpunit/
│       ├── unit/                  # Unit tests (mocked WordPress/WooCommerce)
│       │   ├── class-design-element-test.php
│       │   ├── class-design-test.php
│       │   ├── class-print-generator-test.php
│       │   ├── class-design-storage-test.php
│       │   ├── class-woocommerce-integration-test.php
│       │   ├── class-printful-adapter-test.php
│       │   ├── class-rest-controller-test.php
│       │   ├── class-settings-test.php
│       │   └── class-settings-sanitization-test.php
│       └── integration/           # Integration tests (require live WP + WooCommerce DB)
├── references/                     # Skill reference documentation (not deployed)
└── scripts/
    ├── build.sh                   # Release artifact builder (trunk/ + ZIP)
    └── wp-cli.php                 # WP-CLI command implementation
```

---

## Hooks & Filters Reference

### Actions

| Hook | When It Fires | Typical Use |
|------|--------------|-------------|
| `pod_aggregator_activated` | After plugin activation | Initialize options, register CPT, flush rewrite rules |
| `pod_aggregator_deactivated` | After plugin deactivation | Clear scheduled cron jobs |
| `pod_aggregator_product_imported` | After a product is imported from Printful | Notify external services, add post-transition meta |
| `pod_aggregator_order_submitted` | After Printful order is successfully created | Log to order notes, update status |
| `pod_aggregator_design_saved` | After a design is saved via REST | Trigger analytics, notify integrations |
| `pod_aggregator_webhook_received` | After any webhook payload is received | Custom webhook handling, logging |
| `pod_aggregator_sync_completed` | After a sync run finishes | Notify admin of failures, update UI |

### Filters

| Filter | What It Controls | Default |
|--------|-----------------|---------|
| `pod_aggregator_default_markup` | Applied when no per-product markup is set | `0` |
| `pod_aggregator_sync_interval` | Cron sync interval in seconds | `3600` (1 hour) |
| `pod_aggregator_product_name_prefix` | Prefix added to WooCommerce product names | `POD - ` |
| `pod_aggregator_default_label` | Label shown on Add to Cart button | `Add to Cart` |
| `pod_aggregator_allowed_font_families` | Fonts available in the editor | `Arial, Helvetica, Times New Roman, Georgia, Courier New` |
| `pod_aggregator_max_image_upload_size` | Max image size in bytes | `5242880` (5 MB) |
| `pod_aggregator_preview_dpi` | DPI used for preview generation | `72` |
| `pod_aggregator_print_dpi` | DPI used for print file generation | `300` |
| `pod_aggregator_safe_area_inset` | Safe area padding in pixels at 300 DPI | `180` |
| `pod_aggregator_allowed_element_types` | Element types available in the editor | `['text', 'image']` |
| `pod_aggregator_print_areas` | Available print areas per product | `['front', 'back', 'left', 'right']` |

### Example: Change Default Markup

```php
// In your theme's functions.php or a must-use plugin (mu-plugins/)
add_filter('pod_aggregator_default_markup', function($markup) {
    return 20; // 20% default markup above Printful base cost
});
```

### Example: Add a Custom Font

```php
add_filter('pod_aggregator_allowed_font_families', function($fonts) {
    $fonts[] = 'Comic Sans MS';
    return $fonts;
});
```

### Example: Add a New Print Area

```php
// Add "sleeve left" and "sleeve right" print areas
add_filter('pod_aggregator_print_areas', function($areas) {
    $areas[] = 'sleeve_left';
    $areas[] = 'sleeve_right';
    return $areas;
});
```

### Example: Do Something When an Order Is Submitted

```php
add_action('pod_aggregator_order_submitted', function($order_id, $printful_response) {
    // $printful_response contains Printful's API response
    error_log("Order $order_id submitted to Printful: " . print_r($printful_response, true));
}, 10, 2);
```

---

## Security

POD Aggregator implements the following security measures:

| Practice | Where It Is Applied |
|----------|--------------------|
| **Nonce verification** | All AJAX endpoints verify `wp_verify_nonce()` before processing |
| **Capability checks** | Admin operations require `manage_woocommerce` capability |
| **Network capability** | Network settings require `manage_network_options` |
| **Input sanitization** | `sanitize_text_field`, `absint`, `sanitize_hex_color`, `sanitize_key` on all `$_POST`/`$_GET` inputs |
| **Input unpacking** | `wp_unslash()` applied before sanitization to strip PHP magic quotes |
| **Output escaping** | `esc_html`, `esc_attr`, `esc_url`, `esc_url_raw` on all echoed values |
| **SQL safety** | All `$wpdb` queries use `$wpdb->prepare()` with `%d` / `%s` / `%f` placeholders |
| **Image URL sanitization** | `esc_url_raw()` applied to all image source URLs |
| **Webhook signature verification** | HMAC-SHA256 check on every incoming Printful webhook |
| **JSON encoding** | `wp_json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP` flags for all REST API responses |
| **Autoload management** | Large option values use `autoload='no'` to avoid memory bloat |
| **Cookie security** | REST API supports `SameSite=Lax` cookie nonces |

**AJAX endpoint example (from `class-ajax.php`):**

```php
// Verify nonce AND capability before processing
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pod_aggregator_save_design')) {
    wp_send_json_error(['message' => 'Security check failed'], 403);
}

if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error(['message' => 'Insufficient permissions'], 403);
}

// Sanitize input early
$design_data = json_decode(wp_unslash($_POST['design_data']), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    wp_send_json_error(['message' => 'Invalid JSON'], 400);
}
```

---

## Troubleshooting

### "Activation hook not firing"

**Symptoms:** You activated the plugin but CPTs aren't registered, or the settings page doesn't appear.

**Causes and fixes:**

1. **Not using Network Activate on multisite** — The activation hook only fires for network-wide activation on multisite. Go to **My Sites → Network Admin → Plugins** and click **Network Activate**.

2. **Activating from the wrong file** — The activation hook must be in the main plugin file (`pod-aggregator.php`). If you move the plugin folder, re-activate.

3. **Plugin is already network-activated** — Activation hooks don't re-fire on re-activation. Deactivate first, then reactivate.

4. **Debug:** Add this to `wp-config.php` temporarily:
   ```php
   define('WP_DEBUG', true);
   ```
   Check `wp-content/debug.log` for fatal errors during activation.

---

### "Printful API returns 401"

**Symptoms:** Import Products page shows "Invalid API key" or "401 Unauthorized".

**Fixes:**

1. Go to **Settings → POD Aggregator** and verify the API key is saved.
2. Make sure you're using the **Private API token** (not the Public token). Get it from Printful → Stores → [your store] → API → Get API token.
3. On multisite, verify the key is saved in **Network Admin → Settings**, not a subsite's settings.
4. Check that your Printful account has at least one store configured.

---

### "Products not appearing in the import list"

**Fixes:**

1. **Rate limit:** Printful's API has rate limits. Wait 2 minutes and refresh.
2. **API key expired:** Generate a new API token in Printful and update Settings.
3. **PHP error:** Enable WP_DEBUG and check `wp-content/debug.log`.
4. **Memory limit:** Large catalogs may exhaust PHP memory. Add to `wp-config.php`:
   ```php
   define('WP_MAX_MEMORY_LIMIT', '512M');
   ```

---

### "Design preview not loading on product page"

**Fixes:**

1. **GD extension missing:**
   ```bash
   php -m | grep gd   # If empty, GD is not installed
   ```
   Ask your host to enable the `php-gd` extension.

2. **REST API not accessible:**
   ```bash
   curl -s -o /dev/null -w "%{http_code}" https://yoursite.com/wp-json/pod-aggregator/v1/design/preview
   # Should return 200, not 404
   ```

3. **JavaScript errors:** Open the browser DevTools (F12) → Console tab. Look for errors.

4. **Missing nonce:** The customizer requires a logged-in user. Log in and try again.

---

### "Orders not submitting to Printful"

**Fixes:**

1. **Missing billing in Printful:** Printful won't accept orders without billing. Log in to Printful → Billing and add a credit card.

2. **Invalid variant ID:** The WooCommerce product's `_pod_variant_id` meta must match a valid Printful variant. Edit the product and verify the variant ID.

3. **Check WooCommerce order notes:** Any submission errors are logged as order notes. Go to the order and scroll to Order Notes.

4. **Test the webhook endpoint manually:**
   ```bash
   curl -X POST https://yoursite.com/wp-json/pod-aggregator/v1/webhook \
     -H "Content-Type: application/json" \
     -d '{"event":"order_created","order_id":"PF-TEST"}'
   ```
   You should get a 200 response (even if no matching order is found locally).

---

### "Webhook events not processing"

**Fixes:**

1. **Wrong webhook URL in Printful:** Verify the URL in Printful → Settings → Webhooks matches `https://yoursite.com/wp-json/pod-aggregator/v1/webhook`.

2. **Webhook secret mismatch:** Copy the secret shown in Printful's webhook settings and paste it into WordPress → Settings → POD Aggregator → Webhook Secret.

3. **Events queued but not processed:** Check the `pod_aggregator_webhook_queue` transient:
   ```bash
   wp site transient get pod_aggregator_webhook_queue
   ```

4. **Run the webhook queue cron manually:**
   ```bash
   wp cron event run pod_aggregator_process_webhooks
   ```

---

### "PHPUnit tests fail with 'Class not found'"

```bash
# Make sure composer dependencies are installed
composer install

# Verify the autoloader was generated
ls vendor/autoload.php

# If not, regenerate
composer dump-autoload
```

---

### "Multisite: new blog can't import products"

1. Verify the plugin is **Network Activated** (check `Plugins → Network Admin`).
2. Go to the new blog's **POD Aggregator → Import Products** (not the Network Admin page).
3. The API key is shared across all blogs via network options — you don't need to re-enter it.

---

### "Sync is running but prices aren't updating"

1. Verify the WooCommerce product is linked to a Printful variant (check `_pod_variant_id` in the product meta).
2. Run with debug mode on: set **Debug Mode = On** in Settings, run the sync, then check `wp-content/debug.log`.
3. Check that Printful's catalog pricing hasn't changed (log in to Printful directly).

---

### "300 DPI print file looks blurry"

The print file is generated at 300 DPI for production. What you see on screen (72 DPI preview) is lower resolution by design. If the final print looks blurry:

1. Verify the original uploaded image is high resolution (at least 2× the print area size in inches × 300 DPI).
2. Example: For an 10"×12" print area, your image should be at least 3000×3600 pixels.
3. Ask your POD provider (Printful) to confirm the file meets their resolution requirements.

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Write or update tests for your change.
4. Run the test suite: `composer test`.
5. Run the linter: `composer lint`.
6. Commit your changes with clear messages.
7. Open a Pull Request against `master`.

All contributions must follow the WordPress Coding Standards (PHPCS) and include PHPUnit tests for new behavior.

---

*Maintained by the POD Aggregator team. For support, open an issue at [github.com/gringemini-ship-it/pod-aggregator](https://github.com/gringemini-ship-it/pod-aggregator).*
