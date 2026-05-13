# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Unit tests only (no WordPress needed)
composer test:unit

# Single test file
./vendor/bin/phpunit tests/phpunit/unit/class-printify-adapter-test.php

# Tests matching a name
./vendor/bin/phpunit --testsuite=unit --filter="testSanitize"

# Lint (PSR-12)
composer lint

# Auto-fix lint issues
composer lint:fix

# Validate without PHP (Python)
python3 tests/validate_tests.py

# Build release ZIP
./scripts/build.sh 1.0.0

# Check build without creating ZIP
./scripts/build.sh check

# Regenerate autoloader
composer dump-autoload
```

## Project Architecture

This is a WordPress plugin that bridges WooCommerce with multiple Print-on-Demand providers (Printful, Printify, Gelato). It's fully namespaced under `POD_Aggregator\` with PSR-4 autoloading.

### Key architectural patterns

**Provider interface pattern** — `POD_Aggregator\Provider_Interface` (`includes/class-pod-provider.php`) defines the contract all providers implement: `get_products()`, `submit_order()`, `get_order_status()`, `calculate_price()`, etc. Each provider adapter lives in `includes/providers/` and is registered via `pod_aggregator_get_provider()` in `includes/class-cpt-registrar.php`. Adding a new provider = implement the interface + add settings fields.

**Hook registry pattern** — `Loader` (`includes/class-loader.php`) centralizes all `add_action` / `add_filter` calls. It collects hooks via `add_action()`/`add_filter()` methods, then registers them all in `run()`. This is the single place to understand what hooks the plugin uses.

**Value objects** — `Design` and `Design_Element` (`includes/product-customizer/`) are immutable value objects implementing `JsonSerializable`, `Countable`, `IteratorAggregate`. They have no side effects and are fully testable without WordPress.

**Multi-provider order splitting** — `WooCommerce\Integration::forward_order_to_provider()` groups order items by provider slug and submits separate orders to each provider's API. Results are stored in WC order meta (`_pod_external_order_id_{provider}`).

### File layout

| Path | Role |
|------|------|
| `pod-aggregator.php` | Bootstrap — plugin header, activation/deactivation hooks, `require_once` ordering, DB table creation |
| `includes/class-loader.php` | Central hook registration — all `add_action`/`add_filter` calls |
| `includes/class-pod-provider.php` | `Provider_Interface` — contract for all provider adapters |
| `includes/class-cpt-registrar.php` | Registers `pod_product` + `pod_design` CPTs; `pod_aggregator_get_provider()` factory |
| `includes/providers/` | Provider API adapters — `Printful_Adapter`, `Printify_Adapter`, `Gelato_Adapter` |
| `includes/WooCommerce/class-integration.php` | Cart meta, checkout, order forwarding to providers, resend action |
| `includes/product-customizer/` | `Design`, `Design_Element` value objects; `Design_Storage` (CPT persistence); `Print_Generator` (300 DPI GD output); `REST_Controller` |
| `includes/REST/class-controller.php` | Webhook receiver with per-provider signature verification; `get_webhook_url()` helper |
| `includes/Crons/class-scheduler.php` | `wp_schedule_event` handlers for product sync and order status sync |
| `includes/CLI/` | `wp pod` command group — `syncProducts`, `syncOrders`, `testConnection` |
| `admin/` | Admin pages, Settings API tabs (per-provider), preset templates UI |
| `public/` | Shortcodes (`[pod_customizer]`, `[pod_catalog]`), customizer editor |
| `tests/` | Unit tests (mock WordPress functions in `bootstrap.php`), Python validation script |

### Database tables

- `{$prefix}_pod_aggregator_sync_log` — order sync event log (provider, event_type, status, payload)
- `{$prefix}_pod_aggregator_designs` — preset/stored design templates

### Request lifecycle — checkout

```
woocommerce_checkout_order_processed
  → Integration::forward_order_to_provider()
    → groups items by provider
    → each provider: provider->submit_order()
    → stores _pod_external_order_id_{provider} in order meta
```

### WP-CLI

```
wp pod syncProducts [--provider=X] [--limit=N] [--import-images]
wp pod syncOrders [--provider=X] [--days=N] [--limit=N]
wp pod testConnection [--provider=X]
```

### Webhooks

Webhook URL: `https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=printify`

Signature verification varies per provider:
- Printful/Printify: HMAC-SHA256 via `hash_hmac` + `hash_equals`
- Gelato: Bearer token in Authorization header

### Settings

Settings stored as network options (multisite). Constants in `wp-config.php` take precedence:
`POD_AGGREGATOR_PRINTFUL_API_KEY`, `POD_AGGREGATOR_PRINTIFY_API_TOKEN`, `POD_AGGREGATOR_GELATO_API_KEY`

### Tests

Unit tests in `tests/phpunit/unit/` mock all WordPress functions in `tests/bootstrap.php` using `if (!function_exists(...))` wrappers. The bootstrap defines mocks for `wp_remote_post`, `get_site_option`, `current_user_can`, `WP_Error`, etc. No WordPress installation required for unit tests.

Integration tests directory (`tests/phpunit/integration/`) exists but is empty — would need `wp scaffold package-tests .` for WordPress test library setup.

### Security patterns

- Settings sanitization clamps markup to 0-500%
- REST endpoints verify nonces + `current_user_can()`
- SQL queries use `$wpdb->prepare()` with `%d`/`%s` placeholders
- All dynamic output escaped with `esc_attr()`/`esc_html()`/`esc_url()`/`esc_textarea()`
