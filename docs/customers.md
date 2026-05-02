# Customer Guide

Everything end-users (store owners) need to install, configure, and use POD Aggregator.

**Assumes:** WordPress 6.9+, PHP 7.4+, WooCommerce 8+, a Printful account

---

## Installation

### Prerequisites Checklist

Before installing, confirm your environment meets the requirements:

- [ ] WordPress 6.9 or higher — check in **Dashboard → Updates**
- [ ] WooCommerce 8.x installed and activated — **Plugins → Installed Plugins**
- [ ] PHP 7.4+ with `gd`, `mbstring`, `curl` extensions — ask your host if unsure
- [ ] Printful account with API key ready — get one free at [printful.com](https://www.printful.com)
- [ ] HTTPS enabled on your site (required for Printful API and webhooks)

To check your PHP extensions, ask your host or add this to a PHP file and open it in your browser:

```php
<?php
phpinfo();
```

### Step 1 — Install the Plugin

**Option A — WordPress Admin Upload:**

1. Download the `pod-aggregator.zip` from the release page
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

**Option B — FTP / SFTP Upload:**

1. Unzip `pod-aggregator.zip`
2. Upload the `pod-aggregator/` folder to `/wp-content/plugins/`
3. Go to **Plugins → Installed Plugins** and click **Activate** below "POD Aggregator"

**Option C — WP-CLI:**

```bash
wp plugin install /path/to/pod-aggregator.zip --activate-network
```

### Step 2 — Network Activate (Multisite Only)

If running WordPress Multisite, you **must** use **Network Activate**:

1. Go to **My Sites → Network Admin → Plugins**
2. Find "POD Aggregator" and click **Network Activate**

Do NOT activate it on individual sub-sites — CPTs and REST routes will not register correctly.

### Step 3 — Verify the Plugin Loaded

After activation, you should see a new menu under **My Sites → Network Admin → Settings** (or **Settings** on single-site):

- **POD Aggregator** — Provider settings (Printful API key, default markup, sync frequency)

And under the **My Sites** menu (multisite) or top-level admin menu:

- **POD Products** — Browse and import Printful catalog
- **POD Designs** — Manage saved design templates

---

## Setup Guide

### 1. Connect Printful

1. Go to **Settings → POD Aggregator**
2. Find the **Printful API Key** field
3. Log into Printful → **[Account → API](https://www.printful.com/dashboard)**
4. Copy your API key and paste it into the field
5. Click **Save Changes**

The plugin will immediately verify the key by making a test call to Printful. If you see a green confirmation, the connection is working.

> **Security note:** Your API key is stored as a WordPress site option (or network option on multisite). Consider also setting it via a constant in `wp-config.php` for enhanced security:
> ```php
> define('POD_AGGREGATOR_PRINTFUL_API_KEY', 'your_key_here');
> ```
> The constant takes precedence over the database value.

### 2. Import Products

1. Go to **POD Products → Import**
2. You will see the Printful product catalog — browse by category or search
3. Click **Import** next to any product you want to add to your store
4. The product is created as a WooCommerce product with:
   - Product name and description from Printful
   - Base cost from Printful
   - Your configured default markup added to the price
5. Imported products appear in **WooCommerce → Products**

To set a per-product markup before importing:

1. On the import screen, adjust the **Markup %** column for each product
2. Then click **Import Selected**

### 3. Configure Pricing Markup

The plugin calculates selling price as:

```
selling_price = printful_base_cost × (1 + markup_percentage / 100)
```

**Global default markup** — Set in **Settings → POD Aggregator → Default Markup %**.

**Per-product override:**

1. Edit the WooCommerce product
2. Look for the **POD Aggregator** metabox
3. Enter a custom markup % (leave blank to use the global default)

**Example:** Base cost $20.00 + 40% markup = $28.00 selling price.

### 4. Enable Product Customizer

The visual design editor lets customers personalise products before adding to cart.

**Enable per product:**

1. Edit a WooCommerce product
2. Check **Enable POD Customizer** in the POD Aggregator metabox
3. Save

**Customiser placement:**

The customiser appears on the product page automatically for enabled products. It replaces or augments the featured image area depending on your theme.

**Print areas** are determined by the Printful product configuration. Each print area can accept:
- **Text** — fonts, sizes, colours, alignment
- **Images** — uploaded photos, logos (PNG/JPG, min 300 DPI recommended)

---

## Using the Product Customiser

The customiser is shown on the product detail page for any WooCommerce product where it is enabled.

### Design Editor Layout

The editor shows a live preview of the product with drag-and-drop zones for each print area.

```
┌──────────────────────────────────────────────────────┐
│  [Undo]  [Redo]  [Reset]            [Save Design]  │
├──────────────────────────────────────────────────────┤
│                                                      │
│        ┌────────────────────────────┐               │
│        │                            │               │
│        │     PRODUCT PREVIEW        │               │
│        │                            │               │
│        │   [Text Zone 1]            │               │
│        │   [Image Zone 2]           │               │
│        │                            │               │
│        └────────────────────────────┘               │
│                                                      │
│  ┌──────────────────────────────────────────────┐   │
│  │  Print Area: Front  ▼                       │   │
│  │  [ Text ]  [ Image ]                        │   │
│  └──────────────────────────────────────────────┘   │
│                                                      │
│  [Add to Cart]              Price: $28.00          │
└──────────────────────────────────────────────────────┘
```

### Adding Text Elements

1. Select a print area from the **Print Area** dropdown
2. Click the **Text** tab
3. Click anywhere on the preview to place a text block
4. Type your text — use the toolbar to change:
   - Font family (from your enabled font list)
   - Font size (px)
   - Text colour (colour picker)
   - Bold / Italic / Underline
   - Text alignment
5. Drag the text block to position it within the print area
6. Resize using the corner handles

### Adding Image Elements

1. Select a print area from the **Print Area** dropdown
2. Click the **Image** tab
3. Click **Upload Image** or drag-and-drop an image file onto the zone
4. Supported formats: PNG, JPG — 300 DPI recommended for best print quality
5. Use the toolbar to:
   - Adjust opacity
   - Scale the image
   - Position it within the print area
6. The plugin checks image resolution and warns if it may appear blurry when printed

### Managing Elements

- **Select** — Click any element on the preview to select it (shows resize handles)
- **Move** — Drag selected element to reposition
- **Delete** — Press `Delete` or `Backspace` with element selected, or click the trash icon
- **Layer order** — Use the **Layers** panel (if shown) to bring elements forward/back
- **Undo / Redo** — Use the toolbar buttons or `Ctrl+Z` / `Ctrl+Y`
- **Reset** — Clears all elements in the current print area (confirms before clearing)

### Preview & Save

**Save Design (registered users):**

Clicking **Save Design** stores the current design to your account. A design ID is stored on the cart item so the exact configuration can be reloaded.

**Save Design (guests):**

Guests can save a design — it is associated with the cart session. The design is retrievable for the duration of the checkout process.

**Add to Cart:**

Clicking **Add to Cart**:
1. Generates a 300 DPI print file server-side (GD library)
2. Stores the design data (elements, positions, print area IDs) on the cart item
3. Adds the WooCommerce product to the cart with design metadata visible under the item name

---

## Cart & Checkout

### Cart Display

When a customer adds a customised product to cart, the cart shows:

```
Women's T-Shirt (Custom Design)
SKU: POD-PTF-001
─────────────────────────────
Design ID: a1b2c3d4-e5f6-7890-abcd-ef1234567890
Print Area: Front
Provider: Printful
[View Design]  [Edit Design]
```

Clicking **View Design** opens the design preview in a modal.
Clicking **Edit Design** returns the customer to the product page with the customiser pre-loaded with their saved design.

### Cart Item Meta

The following data is stored as WooCommerce cart item meta:

| Key | Description |
|-----|-------------|
| `_pod_design_id` | UUID of the saved design |
| `_pod_provider` | Provider slug (`printful`) |
| `_pod_print_area` | Print area name (`front`, `back`, etc.) |
| `_pod_product_id` | Printful variant ID |
| `_pod_print_file_url` | URL of the generated 300 DPI print file |

### Checkout Flow

1. Customer completes WooCommerce checkout as normal
2. On `woocommerce_checkout_order_processed`, the plugin:
   - Retrieves the cart item design metadata
   - Generates (or retrieves cached) 300 DPI print file
   - Submits the order to Printful via the Printful API
   - Stores the Printful order ID in WooCommerce order meta: `_pod_printful_order_id`
3. A note is added to the WooCommerce order: `"Order submitted to Printful. Printful ID: [ID]"`
4. If submission fails, a note is added: `"Warning: Order submission to Printful failed. Reason: [error]"` and the order continues processing normally

### Order Confirmation Page

The order confirmation page shows the same design summary as the cart for each customised item, plus the Printful order ID if successfully submitted.

### Order Status Transitions

Printful sends webhook events when order status changes. The plugin processes these and updates the WooCommerce order status:

| Printful Status | WooCommerce Status |
|----------------|-------------------|
| `created` | (new — no change) |
| `processing` | `processing` |
| `shipped` | `completed` (with tracking note) |
| `cancelled` | `cancelled` |
| `failed` | `failed` |

---

## Uninstall

### Standard Uninstall (WordPress Plugin Page)

When you delete the plugin via **Plugins → Installed Plugins → Delete**:

1. `uninstall.php` is executed automatically by WordPress
2. It removes:
   - All site options (`pod_aggregator_*`)
   - All network options (multisite)
   - The `pod_design` CPT posts (all designs permanently deleted)
   - The `pod_product` CPT posts
   - All plugin-related post meta on WooCommerce orders/products
   - Uploaded print files in `wp-content/uploads/pod-aggregator/`

**WooCommerce order history is preserved** — order records remain in WooCommerce; only POD-related meta is removed.

### What Is NOT Deleted

- WooCommerce products (they remain in your product catalogue)
- WooCommerce orders
- WordPress users and roles
- Any other plugins or themes

### Hard Reset (Developer Use)

To reset all plugin data without uninstalling:

```bash
# WP-CLI — delete all POD designs and synced products
wp pod-aggregator hard-reset --confirm

# Or manually via WP-CLI:
wp post delete $(wp post list --post_type=pod_design --format=ids) --force
wp post delete $(wp post list --post_type=pod_product --format=ids) --force
wp site option delete pod_aggregator_printful_api_key
wp site option delete pod_aggregator_settings
```

This does NOT remove the plugin itself — it just wipes all data.
