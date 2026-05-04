# Reference

Hooks, filters, folder structure, security model, troubleshooting, and multisite behaviour.

---

## Folder Structure

```
pod-aggregator/
├── pod-aggregator.php              # Plugin bootstrap — plugin header, activation hooks
├── uninstall.php                   # Triggered when plugin is deleted from Plugins page
├── admin/
│   ├── class-admin.php           # Admin pages, AJAX handlers, manual sync endpoint
│   ├── class-settings.php        # Settings API — Printful/Printify/Gelato tabs
│   └── class-product-import.php  # Product import UI (catalog browser)
├── includes/
│   ├── class-loader.php          # Registers all add_action / add_filter calls
│   ├── class-ajax.php            # AJAX handlers (save design, etc.)
│   ├── class-pod-provider.php   # Provider interface (POD_Provider contract)
│   ├── class-cpt-registrar.php  # Registers pod_product + pod_design CPTs
│   ├── class-product-importer.php # Import provider catalog → WooCommerce products
│   ├── providers/
│   │   ├── class-printful.php   # Printful REST API adapter
│   │   ├── class-printify.php   # Printify REST API adapter
│   │   └── class-gelato.php     # Gelato REST API adapter
│   ├── WooCommerce/
│   │   └── class-integration.php # Cart, checkout, multi-provider order submission
│   ├── REST/
│   │   └── class-controller.php  # Webhook receiver + get_webhook_url()
│   ├── Crons/
│   │   └── class-scheduler.php  # wp_schedule_event handlers (multi-provider)
│   └── CLI/
│       ├── class-cli.php         # Registers `wp pod` WP-CLI group
│       └── Commands/
│           ├── class-sync-products.php    # wp pod syncProducts
│           ├── class-sync-orders.php     # wp pod syncOrders
│           └── class-test-connection.php # wp pod testConnection
├── public/
│   └── class-customizer-editor.php # [pod_customizer] shortcode renderer
└── tests/
    ├── bootstrap.php             # WordPress function mocks + Composer autoload
    ├── validate_tests.py         # Python static analysis (no PHP required)
    └── phpunit/
        └── unit/                # 18 unit test files
```

### Key Classes

| Class | Namespace | File | Responsibility |
|-------|-----------|------|---------------|
| `Pod_aggregator` | (global) | `pod-aggregator.php` | Bootstrap — activation hooks, includes all provider files |
| `Loader` | `POD_Aggregator` | `includes/class-loader.php` | Registers all `add_action` / `add_filter` calls, `cron_schedules` filter |
| `Settings` | `POD_Aggregator\Admin` | `admin/class-settings.php` | Settings API — Printful / Printify / Gelato tabs, per-provider webhook URLs |
| `POD_Provider` | `POD_Aggregator` | `includes/class-pod-provider.php` | Interface — all providers implement `get_slug()`, `get_name()`, `is_configured()`, `get_products()`, `calculate_price()`, `submit_order()`, etc. |
| `Printful` | `POD_Aggregator\Providers` | `includes/providers/class-printful.php` | Printful REST API adapter |
| `Printify` | `POD_Aggregator\Providers` | `includes/providers/class-printify.php` | Printify REST API adapter |
| `Gelato` | `POD_Aggregator\Providers` | `includes/providers/class-gelato.php` | Gelato REST API adapter |
| `CPT_Registrar` | `POD_Aggregator` | `includes/class-cpt-registrar.php` | Registers `pod_product` and `pod_design` CPTs; `pod_aggregator_get_provider()` registry |
| `Integration` | `POD_Aggregator\WooCommerce` | `includes/WooCommerce/class-integration.php` | Cart, checkout, `forward_order_to_provider()` with multi-provider splitting and exponential backoff retry |
| `REST_Controller` | `POD_Aggregator\REST` | `includes/REST/class-controller.php` | Webhook receiver with signature verification; `get_webhook_url($provider)` helper |
| `Scheduler` | `POD_Aggregator\Crons` | `includes/Crons/class-scheduler.php` | `sync_products()` iterates all providers; configurable sync interval; `sync_order_status()` every 15 min |
| `CLI` | `POD_Aggregator\CLI` | `includes/CLI/class-cli.php` | Registers `wp pod` command group on `plugins_loaded` priority 5 |
| `Sync_Products` | `POD_Aggregator\CLI\Commands` | `includes/CLI/Commands/class-sync-products.php` | `wp pod syncProducts [--provider=X] [--limit=N] [--import-images]` |
| `Sync_Orders` | `POD_Aggregator\CLI\Commands` | `includes/CLI/Commands/class-sync-orders.php` | `wp pod syncOrders [--provider=X] [--days=N] [--limit=N]` |
| `Test_Connection` | `POD_Aggregator\CLI\Commands` | `includes/CLI/Commands/class-test-connection.php` | `wp pod testConnection [--provider=X]` |

---

## Hooks & Filters Reference

### Actions

| Hook | When It Fires | Use |
|------|--------------|-----|
| `pod_aggregator_activated` | After plugin is network-activated | Register CPTs, flush rewrite rules, set default options |
| `pod_aggregator_deactivated` | After plugin is network-deactivated | Clear all scheduled cron events |
| `pod_aggregator_product_imported` | After a product is imported from any provider | Notify external systems, log analytics |
| `pod_aggregator_order_submitted` | After order successfully submitted to a provider | Trigger fulfilment webhooks, log |
| `pod_aggregator_order_failed` | After order submission to a provider fails | Alert admin, log error |
| `pod_aggregator_sync_completed` | After a catalog sync finishes | Notify, update last-sync timestamp |
| `pod_aggregator_design_saved` | After a design is saved via REST | Analytics, third-party integrations |

### Filters

| Filter | Arguments | Use |
|--------|-----------|-----|
| `pod_aggregator_selling_price` | `$price`, `$product_id`, `$base_cost` | Override selling price calculation |
| `pod_aggregator_default_markup` | `$markup_percent`, `$provider` | Change global default markup % per provider |
| `pod_aggregator_enabled_providers` | `$providers` | Add/remove/reorder POD providers |
| `pod_aggregator_print_file_dpi` | `$dpi` | Change target DPI for print file generation |
| `pod_aggregator_sync_interval` | `$interval_in_seconds` | Change scheduled sync frequency |
| `pod_aggregator_allowed_element_types` | `$types` | Add custom element types (e.g. `qr_code`) |
| `pod_aggregator_provider_map` | `$map`, `$order_items` | Override which provider handles which cart items (for multi-provider orders) |

---

## Code Examples

### Change Default Markup Per Provider

```php
// In a mu-plugin or theme's functions.php:
add_filter('pod_aggregator_default_markup', function ($markup, $provider) {
    if ($provider === 'printify') {
        return 15; // 15% for Printify, 25% for everyone else
    }
    return $markup;
}, 10, 2);
```

### Get a Provider's Webhook URL Programmatically

```php
$webhook_url = \POD_Aggregator\REST\Controller::get_webhook_url('printify');
// Returns: https://example.com/wp-json/pod-aggregator/v1/webhook?provider=printify
```

### Add a Custom Font to the Customiser

```php
add_filter('pod_aggregator_customiser_fonts', function ($fonts) {
    $fonts[] = [
        'family' => 'Pacifico',
        'url'    => 'https://fonts.googleapis.com/css?family=Pacifico',
    ];
    return $fonts;
});
```

### Add a New Print Area Programmatically

```php
// Register a custom print area for a specific product
add_filter('pod_aggregator_print_areas', function ($areas, $product_id) {
    if ($product_id === 123) {
        $areas[] = [
            'id'          => 'sleeve_left',
            'label'       => 'Left Sleeve',
            'width_mm'    => 50,
            'height_mm'   => 80,
            'dpi'         => 300,
        ];
    }
    return $areas;
}, 10, 2);
```

### Do Something When an Order Is Submitted to Any Provider

```php
add_action('pod_aggregator_order_submitted', function ($order_id, $provider_slug, $provider_order_id) {
    // $order_id — WooCommerce order ID
    // $provider_slug — 'printful', 'printify', or 'gelato'
    // $provider_order_id — the provider's order ID
    // Example: notify a Slack channel
    wp_remote_post('https://slack.com/api/chat.postMessage', [
        'headers' => ['Authorization' => 'Bearer ' . get_option('slack_token')],
        'body'    => [
            'channel' => '#orders',
            'text'    => "Order #$order_id submitted to $provider_slug: $provider_order_id",
        ],
    ]);
}, 10, 3);
```

### Register a New Provider Adapter

To add a new POD provider (e.g. "CustomPOD"):

1. Create `includes/providers/class-custompod.php` implementing `POD_Provider`
2. Add settings fields in `admin/class-settings.php`
3. Register in `pod_aggregator_get_provider()` in `includes/class-cpt-registrar.php`
4. Add `require_once` in `pod-aggregator.php`
5. Add webhook URL display in settings section description renderer

---

## Security

### Nonce Verification

All AJAX and REST endpoints that mutate data verify a WordPress nonce first:

```php
// AJAX handler (from class-admin.php)
check_ajax_referer('pod_manual_sync', 'nonce');
if (!current_user_can('manage_network')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
}

// REST endpoint (from class-controller.php)
$nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field($_SERVER['HTTP_X_WP_NONCE']) : '';
if (!wp_verify_nonce($nonce, 'pod_aggregator_rest_nonce')) {
    return new WP_Error('forbidden', 'Invalid nonce', ['status' => 403]);
}
```

Nonces are generated in the admin pages and passed to the frontend via `wp_localize_script`:

```php
wp_localize_script('pod-aggregator-front', 'POD_AGG', [
    'ajax_url'   => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce('pod_aggregator_nonce'),
    'rest_base'  => rest_url('pod-aggregator/v1/'),
    'rest_nonce' => wp_create_nonce('wp_rest'),
]);
```

### Capability Checks

Every admin action and REST mutation checks `current_user_can()`:

```php
// Admin menu pages require 'manage_network_options' (multisite) or 'manage_options' (single site)
if (!current_user_can('manage_network_options') && !current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
}
```

### Input Sanitisation

All `$_POST` / `$_GET` input is sanitised early using WordPress functions:

```php
// From class-settings.php sanitize callback:
$data = wp_unslash($_POST['pod_aggregator']);
$clean['printful_api_key']   = sanitize_key($data['printful_api_key']);
$clean['printify_api_token'] = sanitize_text_field(trim($data['printify_api_token']));
$clean['gelato_api_key']     = sanitize_text_field(trim($data['gelato_api_key']));
$clean['default_markup']     = absint($data['default_markup']);
$clean['sync_interval_hours'] = absint($data['sync_interval_hours']);
// Markup clamped to 0–500%
$clean['default_markup'] = min(500, max(0, $clean['default_markup']));
```

### Output Escaping

All dynamic output is escaped at render time:

```php
// From admin settings page:
<input type="text"
       name="pod_aggregator[printful_api_key]"
       value="<?php echo esc_attr($settings['printful_api_key'] ?? ''); ?>"
>

// From REST controller:
return rest_ensure_response([
    'success' => true,
    'data'    => [
        'design_id' => sanitize_key($design_id),
    ],
]);
```

### SQL Safety

All database queries use `$wpdb->prepare()` with placeholder syntax:

```php
// WRONG — never do this:
$results = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = $id");

// CORRECT — use prepare():
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
        $id
    )
);

// With multiple placeholders:
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
        'pod_design',
        'publish'
    )
);
```

### Webhook Signature Verification

Each provider uses a different signature scheme:

**Printful / Printify** — HMAC-SHA256:

```php
// Timing-safe HMAC comparison
$expected = hash_hmac('sha256', $raw_body, $webhook_secret);
if (!hash_equals($expected, $signature_from_header)) {
    return new WP_Error('unauthorized', 'Invalid signature', ['status' => 401]);
}
```

**Gelato** — Bearer token in `Authorization` header:

```php
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? sanitize_text_field($_SERVER['HTTP_AUTHORIZATION']) : '';
if (!str_starts_with($auth, 'Bearer ')) {
    return new WP_Error('unauthorized', 'Missing Bearer token', ['status' => 401]);
}
$token = substr($auth, 7);
if (!hash_equals($stored_api_key, $token)) {
    return new WP_Error('unauthorized', 'Invalid Bearer token', ['status' => 401]);
}
```

---

## Troubleshooting

### Activation Hook Not Firing

**Symptoms:** CPT not registered, settings not initialised.

**Causes and fixes:**

1. **Plugin is network-activated but you're looking at a sub-site.** CPTs are registered network-wide. Look in **Network Admin → Plugins** and **Network Admin → Settings**, not individual site dashboards.

2. **Activation hook registered inside another hook** instead of at top-level of main plugin file:
   ```php
   // WRONG — inside a function:
   function my_init() {
       register_activation_hook(__FILE__, 'my_activation');
   }

   // CORRECT — at top-level of main plugin file:
   register_activation_hook(__FILE__, 'pod_aggregator_activate');
   ```

3. **Wrong file path** passed to `register_activation_hook`. Use `__FILE__` (the main plugin file), not `__DIR__`.

### Provider API Returns 401 / Invalid Credentials

| Provider | Possible Causes | Fix |
|----------|----------------|-----|
| Printful | API key wrong, key revoked, insufficient permissions | Re-copy key from Printful Dashboard → API |
| Printify | API token expired or wrong | Regenerate token at Printify → My Profile → API |
| Gelato | API key wrong or workspace suspended | Check Gelato → Settings → API |

Verify with WP-CLI:
```bash
wp pod testConnection --provider=printify
```

### Products Not Appearing in the Import List

**Causes:**
- Provider API key is not configured — set it in **Settings → POD Aggregator** → respective tab
- Provider API is returning an error — check PHP error log
- Network issue — the site server must be able to reach the provider's API endpoint

Test connectivity from the server:
```bash
# Printify
curl -I https://api.printify.com/v1/

# Gelato
curl -I https://api.gelato.com/v2/

# Printful
curl -I https://api.printful.com/
```

### Design Preview Not Loading on Product Page

**Cause:** The customiser JS/CSS not enqueued.

**Fix:**
1. Confirm the product has **Enable POD Customiser** checked in the product edit page
2. Check browser console for JavaScript errors
3. Confirm the theme does not have a JS error preventing execution
4. Try with a default WordPress theme (e.g. Twenty Twenty-Four) to rule out theme conflicts

### Orders Not Submitting to Provider

**Diagnostic steps:**

```bash
# 1. Check WooCommerce order meta contains design data
wp post meta get <order_id> _pod_design_id

# 2. Check provider order ID was stored after submission
wp post meta get <order_id> _pod_printify_order_id

# 3. Test provider connection
wp pod testConnection --provider=printify

# 4. Check PHP error log for API errors
tail -f /var/log/php-error.log
```

**Common causes:**
- Provider API key is wrong or revoked
- Product has no design associated (cart item missing `_pod_design_id` meta)
- Provider account has insufficient credits — orders can't be submitted
- Multi-provider order: items from different providers are split correctly, but one provider's items fail

### Webhook Events Not Processing

**Check:**
1. Webhook URL is publicly accessible (not blocked by `.htaccess` or a security plugin)
2. Webhook secret in **Settings → POD Aggregator** matches the provider dashboard
3. The correct signature header is being received — check with:
   ```php
   add_action('rest_api_init', function () {
       add_filter('rest_pre_dispatch', function ($result, $rest_server, $request) {
           error_log('POD webhook headers: ' . print_r($request->get_headers(), true));
           return $result;
       }, 10, 3);
   });
   ```
4. Gelato: ensure the `Authorization: Bearer <api_key>` header is being passed by Gelato's webhook delivery

### Webhook URL Is Not Reachable

The webhook URL is `https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=printify` (or `gelato`).

Test from your local machine:
```bash
curl -I "https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=printify"
```

If blocked, check:
- WordPress permalinks are enabled (REST routes require pretty permalinks)
- No security plugin blocking `/wp-json/` requests
- `.htaccess` not blocking POST requests to `/wp-json/`

### Cron Not Running

Check scheduled events:
```bash
wp cron event list | grep pod_aggregator
```

If events are not scheduled, the sync interval may be set to 0 in settings, or `wp_schedule_event` failed. Check `WP_DEBUG_LOG`:
```php
// In wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `wp-content/debug.log` for `pod_aggregator` entries.

Manually trigger a sync:
```bash
wp cron event run pod_aggregator_sync_products
```

### PHPUnit Tests Fail with "Class Not Found"

```bash
# Autoloader not generated
composer install
composer dump-autoload
```

### Multisite: New Blog Can't Import Products

**Cause:** `manage_network_options` capability required for product import on a sub-site.

**Fix:** Users must have network admin access, or you must grant the sub-site admin the `pod_aggregator_import` capability:
```php
$user = get_user_by('email', 'user@example.com');
$user->add_cap('pod_aggregator_import');
```

### Sync Is Running But Prices Aren't Updating

**Cause:** Sync only updates WooCommerce products created by the import. Manually created WooCommerce products are not touched.

**Fix:** Use the "Re-import" option from **POD Products** admin page to re-sync a specific product.

### 300 DPI Print File Looks Blurry

The 300 DPI generation scales the design to 300 DPI server-side. If the original uploaded image is low resolution, upscaling causes blur. The plugin warns users when an image is below recommended resolution.

**Recommendations:**
- Upload images at least 150 DPI in the customiser (the server will upscale to 300 DPI)
- Minimum recommended: 450×600 px for a standard print area
- For best results: 900×1200 px or higher

---

## Multisite Support

### Network Activation

**Required.** Activate the plugin from **My Sites → Network Admin → Plugins → Network Activate**. Individual blog activation does not register CPTs or REST routes for the network.

### Per-Blog Product Import

Each sub-site can import products from any provider independently. Products are imported into the sub-site's WooCommerce instance.

To restrict which blogs can import:
```php
// In a mu-plugin or theme:
add_filter('pod_aggregator_can_import', function ($can, $blog_id) {
    // Only blog IDs 1 and 2 can import
    return in_array($blog_id, [1, 2], true);
}, 10, 2);
```

### Cron in Multisite

On multisite, `wp_schedule_event` uses `wp_schedule_single_event` internally with blog scoping. The sync cron event is scheduled network-wide when the plugin is network-activated, but runs per-blog if the option is set to blog-scoped.

Force network-wide sync cron:
```php
add_filter('pod_aggregator_sync_cron_network_wide', '__return_true');
```

### Settings Storage

Settings are stored as **network options** (not per-blog options) when the plugin is network-activated. The Settings API page is shown under **Network Admin → Settings**.

Each provider's settings (API keys, webhook secrets) are stored as network options:
- `pod_aggregator_printful_api_key`
- `pod_aggregator_printify_api_token`
- `pod_aggregator_gelato_api_key`
- `pod_aggregator_settings` (serialized array of all settings)

Or set via constants in `wp-config.php` (constants take precedence):
- `POD_AGGREGATOR_PRINTFUL_API_KEY`
- `POD_AGGREGATOR_PRINTIFY_API_TOKEN`
- `POD_AGGREGATOR_GELATO_API_KEY`
