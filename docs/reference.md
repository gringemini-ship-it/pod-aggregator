# Reference

Hooks, filters, folder structure, security model, troubleshooting, and multisite behaviour.

---

## Folder Structure

```
pod-aggregator/
├── pod-aggregator.php              # Plugin bootstrap — plugin header, activation hooks
├── uninstall.php                   # Triggered when plugin is deleted from Plugins page
├── admin/
│   ├── class-settings.php         # Settings API — network settings page
│   ├── class-product-import.php   # Product import UI (Printful catalog browser)
│   └── class-admin-menus.php      # Network admin menu registration
├── includes/
│   ├── class-loader.php            # Registers all add_action / add_filter calls
│   ├── class-ajax.php              # AJAX handlers (save design, etc.)
│   ├── class-pod-provider.php      # Provider interface (POD_Provider contract)
│   ├── class-cpt-registrar.php     # Registers pod_product + pod_design CPTs
│   ├── class-product-importer.php   # Import Printful catalog → WooCommerce products
│   ├── class-webhook-handler.php   # Verifies + processes Printful webhook events
│   ├── providers/
│   │   └── class-printful.php     # Printful API adapter
│   ├── WooCommerce/
│   │   └── class-integration.php   # Cart, checkout, order submission hooks
│   ├── product-customizer/
│   │   ├── class-design.php        # Design value object (immutable)
│   │   ├── class-design-element.php # Design_Element value object
│   │   ├── class-design-storage.php # Persists designs to pod_design CPT
│   │   ├── class-print-generator.php # Generates 300 DPI print files via GD
│   │   └── class-rest-controller.php # REST endpoints for design CRUD
│   ├── REST/
│   │   └── class-controller.php     # Sync REST endpoints
│   └── Crons/
│       └── class-scheduler.php     # wp_schedule_event handlers, WP-CLI commands
├── public/
│   └── class-customizer-editor.php # [pod_customizer] shortcode renderer
└── tests/
    ├── bootstrap.php               # WordPress function mocks
    └── phpunit/
        ├── unit/                   # 9 unit test files
        └── integration/            # Empty — add integration tests here
```

### Key Classes

| Class | Namespace | Responsibility |
|-------|-----------|---------------|
| `Pod_aggregator` | (global) | Bootstrap — registers activation/deactivation hooks, includes files |
| `Loader` | `POD_Aggregator` | Registers all `add_action` / `add_filter` calls |
| `Settings` | `POD_Aggregator\Admin` | Network settings page via Settings API |
| `POD_Provider` | `POD_Aggregator` | Interface — all providers implement this |
| `Printful` | `POD_Aggregator\Provider` | Printful REST API adapter |
| `CPT_Registrar` | `POD_Aggregator` | Registers `pod_product` and `pod_design` CPTs |
| `Design` | `POD_Aggregator\ProductCustomizer` | Immutable value object representing a design |
| `Design_Element` | `POD_Aggregator\ProductCustomizer` | Immutable value object for one design element |
| `Design_Storage` | `POD_Aggregator\ProductCustomizer` | Persists/retrieves designs to/from `pod_design` CPT |
| `Print_Generator` | `POD_Aggregator\ProductCustomizer` | GD-based 300 DPI print file generation |
| `REST_Controller` | `POD_Aggregator\ProductCustomizer` | REST endpoints for design CRUD |
| `Integration` | `POD_Aggregator\WooCommerce` | Cart, checkout, order submission hooks |
| `Webhook_Handler` | `POD_Aggregator` | Signature verification + event processing |

---

## Hooks & Filters Reference

### Actions

| Hook | When It Fires | Use |
|------|--------------|-----|
| `pod_aggregator_activated` | After plugin is network-activated | Register CPTs, flush rewrite rules, set default options |
| `pod_aggregator_deactivated` | After plugin is network-deactivated | Clear scheduled cron events |
| `pod_aggregator_product_imported` | After a product is imported from Printful | Notify external systems, log analytics |
| `pod_aggregator_order_submitted` | After order successfully submitted to Printful | Triggerfulfilment webhooks, log |
| `pod_aggregator_order_failed` | After order submission to Printful fails | Alert admin, log error |
| `pod_aggregator_sync_completed` | After a catalog sync finishes | Notify, update last-sync timestamp |
| `pod_aggregator_design_saved` | After a design is saved via REST | Analytics, third-party integrations |

### Filters

| Filter | Arguments | Use |
|--------|-----------|-----|
| `pod_aggregator_selling_price` | `$price`, `$product_id`, `$base_cost` | Override selling price calculation |
| `pod_aggregator_default_markup` | `$markup_percent` | Change global default markup % |
| `pod_aggregator_enabled_providers` | `$providers` | Add/remove/reorder POD providers |
| `pod_aggregator_print_file_dpi` | `$dpi` | Change target DPI for print file generation |
| `pod_aggregator_sync_interval` | `$interval_in_seconds` | Change scheduled sync frequency |
| `pod_aggregator_allowed_element_types` | `$types` | Add custom element types (e.g. `qr_code`) |

---

## Code Examples

### Change Default Markup

```php
// In a mu-plugin or theme's functions.php:
add_filter('pod_aggregator_default_markup', function ($markup) {
    return 35; // 35% instead of the configured default
});
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

### Do Something When an Order Is Submitted

```php
add_action('pod_aggregator_order_submitted', function ($order_id, $printful_order_id) {
    // $order_id — WooCommerce order ID
    // $printful_order_id — Printful's order ID
    // Example: notify a Slack channel
    wp_remote_post('https://slack.com/api/chat.postMessage', [
        'headers' => ['Authorization' => 'Bearer ' . get_option('slack_token')],
        'body'    => [
            'channel' => '#orders',
            'text'    => "Order #$order_id submitted to Printful: $printful_order_id",
        ],
    ]);
}, 10, 2);
```

---

## Security

### Nonce Verification

All AJAX and REST endpoints that mutate data verify a WordPress nonce first:

```php
// AJAX handler (from class-ajax.php)
check_ajax_referer('pod_aggregator_nonce', 'nonce');

// REST endpoint (from class-rest-controller.php)
$nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field($_SERVER['HTTP_X_WP_NONCE']) : '';
if (!wp_verify_nonce($nonce, 'pod_aggregator_rest_nonce')) {
    return new WP_Error('forbidden', 'Invalid nonce', ['status' => 403]);
}
```

Nonces are generated in the admin pages and passed to the frontend via `wp_localize_script`:

```php
wp_localize_script('pod-aggregator-front', 'POD_AGG', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('pod_aggregator_nonce'),
    'rest_base' => rest_url('pod-aggregator/v1/'),
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
$clean['default_markup']      = absint($data['default_markup']);
$clean['sync_interval']       = absint($data['sync_interval']);
$clean['webhook_secret']     = sanitize_text_field(trim($data['webhook_secret']));
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

### Printful API Returns 401

**Cause:** Invalid or expired API key.

**Fix:**
1. Go to **Settings → POD Aggregator**
2. Re-copy the API key from [Printful Dashboard → API](https://www.printful.com/dashboard)
3. Paste and save
4. Test with: `wp pod-aggregator sync --dry-run`

Also check that the API key has the correct permissions (must have Read+Write for order submission).

### Products Not Appearing in the Import List

**Causes:**
- Printful API key is not configured — set it in **Settings → POD Aggregator**
- Printful API is returning an error — check WP Admin → **Tools → Site Health → Logs** or PHP error log
- Network issue — the site server must be able to reach `https://api.printful.com`

Fix: Test connectivity from the server:
```bash
curl -I https://api.printful.com/
```

### Design Preview Not Loading on Product Page

**Cause:** The customiser JS/CSS not enqueued.

**Fix:**
1. Confirm the product has **Enable POD Customiser** checked in the product edit page
2. Check browser console for JavaScript errors
3. Confirm the theme does not have a JS error preventing execution
4. Try with a default WordPress theme (e.g. Twenty Twenty-Four) to rule out theme conflicts

### Orders Not Submitting to Printful

**Diagnostic steps:**

```bash
# 1. Check WooCommerce order meta contains design data
wp eval 'var_dump(get_post_meta(WC()->cart->get_cart()[0]["product_id"]));'

# 2. Check last webhook processing log
wp pod-aggregator sync --dry-run

# 3. Check PHP error log for API errors
tail -f /var/log/php-error.log
```

**Common causes:**
- Printful API key is wrong or revoked
- Product has no design associated (cart item missing `_pod_design_id` meta)
- Printful account has insufficient credits — orders can't be submitted

### Webhook Events Not Processing

**Check:**
1. Webhook URL is publicly accessible (not blocked by `.htaccess` or a security plugin)
2. Webhook secret in **Settings → POD Aggregator** matches what's in Printful dashboard
3. The `Printful-Signature` header is being received — check with:
   ```php
   add_action('rest_api_init', function () {
       add_filter('rest_pre_dispatch', function ($result, $rest_server, $request) {
           error_log('POD webhook headers: ' . print_r($request->get_headers(), true));
           return $result;
       }, 10, 3);
   });
   ```
4. The webhook secret was updated in Printful but not saved in WordPress

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

Each sub-site can import Printful products independently. Products are imported into the sub-site's WooCommerce instance.

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
