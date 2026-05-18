# Customer Guide

Everything end-users (store owners) need to install, configure, and use POD Aggregator.

**Assumes:** WordPress 6.9+, PHP 7.4+, WooCommerce 8+, at least one of Printful / Printify / Gelato.

---

## Installation

### Prerequisites Checklist

Before installing, confirm your environment meets the requirements:

- [ ] WordPress 6.9 or higher — check in **Dashboard → Updates**
- [ ] WooCommerce 8.x installed and activated — **Plugins → Installed Plugins**
- [ ] PHP 7.4+ with `gd`, `mbstring`, `curl` extensions — ask your host if unsure
- [ ] At least one POD provider account (Printful, Printify, or Gelato)
- [ ] HTTPS enabled on your site (required for provider APIs and webhooks)

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

### Step 2 — Activate the Plugin

**Single-site:** Go to **Plugins → Installed Plugins** and click **Activate** below "POD Aggregator".

**Multisite:** Go to **My Sites → Network Admin → Plugins**, find "POD Aggregator", and click **Network Activate**. Do NOT activate on individual sub-sites — CPTs and REST routes will not register correctly.

### Step 3 — Verify the Plugin Loaded

After activation, you should see a new top-level admin menu **POD Aggregator** with these submenus:

- **Dashboard** — Provider status with "Sync Now" buttons and sync log
- **Settings** — Provider API keys, Store IDs, markup percentages, sync intervals
- **Import Products** — Browse synced provider catalogs and import to WooCommerce
- **Preset Templates** — Manage pre-made design templates for the customizer

On multisite, settings are managed under **Network Admin → Settings → POD Aggregator**.

---

## Setup Guide

### 1. Connect Your Providers

Go to **Settings → POD Aggregator**. You will see three tabs: **Printful**, **Printify**, and **Gelato**. Connect one or more providers.

#### Printful

1. Go to the **Printful** tab
2. Log into Printful → **[Account → API](https://www.printful.com/dashboard)**
3. Copy your API key and paste it into the **Printful API Key** field
4. Enter your **Printful Store ID** — find this in Printful Dashboard → **Stores**. You must create a store of type **"Manual Order / API"** (not "WooCommerce"). The store ID is the numeric ID shown next to the store name.
5. The plugin verifies the key automatically on save — a green indicator confirms the connection
6. Copy the **Webhook URL** shown in the section description (you'll paste it into Printful's dashboard in Step 2)

> **Store ID note:** The Printful Store ID is required for order fulfillment. Without it, orders cannot be submitted to Printful. Only "Manual Order / API" type stores can be used — WooCommerce-type stores do not support the API endpoints this plugin uses.
>
> **Security note:** Your API key is stored as a WordPress site option (or network option on multisite). You can also set it via a constant in `wp-config.php`:
> ```php
> define('POD_AGGREGATOR_PRINTFUL_API_KEY', 'your_key_here');
> ```
> The constant takes precedence over the database value.

#### Printify

1. Go to the **Printify** tab
2. Log into Printify → **My Profile → API**
3. Generate or copy your API token
4. Enter your **Printify API Token** and **Shop ID** in the corresponding fields
5. Optionally set a **Default Markup %** (default: 10%) to apply to all imported Printify products
6. The plugin verifies the connection automatically on save
7. Copy the **Webhook URL** shown in the section description

> The Printify API token can also be set via constant:
> ```php
> define('POD_AGGREGATOR_PRINTIFY_API_TOKEN', 'your_token_here');
> ```

#### Gelato

1. Go to the **Gelato** tab
2. Log into Gelato → **Settings → API**
3. Create an API key and paste it into the **Gelato API Key** field
4. Optionally set product, address, and webhook secrets if you use Gelato's webhook signature verification
5. The plugin verifies the connection automatically on save
6. Copy the **Webhook URL** shown in the section description

> The Gelato API key can also be set via constant:
> ```php
> define('POD_AGGREGATOR_GELATO_API_KEY', 'your_key_here');
> ```

### 2. Set Up Webhooks (Required for Order Status Updates)

Webhooks keep WooCommerce order statuses in sync with your provider. Without webhooks, you must manually check order status.

#### Printful

1. Log into Printful → **[Webhooks](https://www.printful.com/dashboard)**
2. Click **Add Webhook**
3. Enter the webhook URL shown in **Settings → POD Aggregator → Printful**
4. Select these events:
   - `order_created` — Order received by Printful
   - `order_processing` — Printful started production
   - `order_shipped` — Shipped (with tracking number)
   - `order_cancelled` — Order cancelled
   - `order_failed` — Production failed
5. Copy the **Webhook Secret** shown by Printful
6. Back in WordPress, paste the secret into **Settings → POD Aggregator → Printful → Webhook Secret**
7. Click **Save Changes**

#### Printify

1. Log into Printify → **My Profile → Webhooks**
2. Click **Add Webhook**
3. Enter the webhook URL shown in **Settings → POD Aggregator → Printify**
4. Select events for order updates
5. Copy the **Webhook Secret** and paste it into **Settings → POD Aggregator → Printify → Webhook Secret**

#### Gelato

1. Log into Gelato → **Settings → Webhooks**
2. Add a webhook pointing to the URL shown in **Settings → POD Aggregator → Gelato**
3. Gelato uses Bearer token authentication — no secret to copy; the plugin validates the Bearer token in the `Authorization` header against your configured API key

---

### 3. Sync and Import Products

The product import workflow has two stages:

**Stage 1 — Sync provider catalog:**

1. Go to **POD Aggregator → Dashboard** in the admin menu
2. Click the **Sync Now** button next to your configured provider
3. The plugin fetches the provider's product catalog and stores it as `pod_product` CPT entries
4. You can also use WP-CLI: `wp pod syncProducts --provider=printful`
5. Synced products appear under **POD Products** in the admin menu

> **Printful catalog size:** The Printful `/products` endpoint returns ~98 featured products when called without a category filter. The plugin automatically iterates through all product categories to assemble the full catalog (300+ products). A full sync may take 30–60 seconds due to the number of API calls required.

**Stage 2 — Import to WooCommerce:**

1. Go to **Import Products** under the POD Aggregator menu
2. Filter by provider (Printful / Printify / Gelato) to see synced products
3. Browse the catalog and click **Import to Store** on any product
4. The product is created as a WooCommerce variable product with:
   - Product name, description, and thumbnail from the provider
   - All variants (size/color combinations) with individual prices
   - Your configured markup applied to each variant's cost
   - Product images downloaded from the provider's CDN
5. Imported products appear in **WooCommerce → Products** and are immediately available in your store

> **Image import:** Provider CDN URLs sometimes lack file extensions (particularly Printful). The plugin detects the image MIME type from file contents so images import correctly regardless of URL format. If images fail to import, ensure your server has the `finfo` or `mime_content_type` PHP extension enabled.

**After import**, you can edit the WooCommerce product normally — change prices, descriptions, categories, etc. The plugin tracks the link between the `pod_product` and the WooCommerce product via post meta, so you won't accidentally import the same product twice.

---

### 4. Configure Pricing Markup

The plugin calculates selling price as:

```
selling_price = provider_base_cost × (1 + markup_percentage / 100)
```

**Global default markup** — Set in **Settings → POD Aggregator** (per-provider tab).

**Per-product override:**

1. Edit the WooCommerce product
2. Look for the **POD Aggregator** metabox
3. Enter a custom markup % (leave blank to use the global default for that provider)

**Example:** Base cost $20.00 + 40% markup = $28.00 selling price.

---

### 5. Enable Product Customizer

The visual design editor lets customers personalise products before adding to cart.

**Enable per product:**

1. Edit a WooCommerce product
2. Check **Enable POD Customizer** in the POD Aggregator metabox
3. Save

**Customiser placement:**

The customiser appears on the product page automatically for enabled products. It replaces or augments the featured image area depending on your theme.

**Print areas** are determined by the provider's product configuration. Each print area can accept:
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
Provider: Printify
[View Design]  [Edit Design]
```

Clicking **View Design** opens the design preview in a modal.
Clicking **Edit Design** returns the customer to the product page with the customiser pre-loaded with their saved design.

### Cart Item Meta

The following data is stored as WooCommerce cart item meta:

| Key | Description |
|-----|-------------|
| `_pod_design_id` | UUID of the saved design |
| `_pod_provider` | Provider slug (`printful`, `printify`, or `gelato`) |
| `_pod_print_area` | Print area name (`front`, `back`, etc.) |
| `_pod_product_id` | Provider variant ID |
| `_pod_print_file_url` | URL of the generated 300 DPI print file |

### Checkout Flow

1. Customer completes WooCommerce checkout as normal
2. On `woocommerce_checkout_order_processed`, the plugin:
   - Retrieves the cart item design metadata
   - Generates (or retrieves cached) 300 DPI print file
   - Submits the order to the correct provider via their API
   - Stores the provider order ID in WooCommerce order meta (e.g. `_pod_printify_order_id`)
3. A note is added to the WooCommerce order: `"Order submitted to [Provider]. [Provider] ID: [ID]"`
4. If submission fails, the plugin retries up to 3 times with exponential backoff. If all retries fail, a note is added: `"Warning: Order submission to [Provider] failed. Reason: [error]"` and the order continues processing normally

**Multi-provider orders:** If a single order contains items from different providers (e.g. a Printify shirt and a Gelato mug), the plugin splits the order and submits each provider's items separately.

### Order Confirmation Page

The order confirmation page shows the same design summary as the cart for each customised item, plus the provider order ID if successfully submitted.

### Order Status Transitions

Providers send webhook events when order status changes. The plugin processes these and updates the WooCommerce order status:

#### Printful

| Printful Status | WooCommerce Status |
|----------------|-------------------|
| `created` | (new — no change) |
| `processing` | `processing` |
| `shipped` | `completed` (with tracking note) |
| `cancelled` | `cancelled` |
| `failed` | `failed` |

#### Printify

| Printify Status | WooCommerce Status |
|----------------|-------------------|
| `created` | (new — no change) |
| `processing` | `processing` |
| `shipped` | `completed` (with tracking note) |
| `cancelled` | `cancelled` |
| `failed` | `failed` |

#### Gelato

| Gelato Status | WooCommerce Status |
|-------------|-------------------|
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
wp pod hard-reset --confirm

# Or manually via WP-CLI:
wp post delete $(wp post list --post_type=pod_design --format=ids) --force
wp post delete $(wp post list --post_type=pod_product --format=ids) --force
wp site option delete pod_aggregator_printful_api_key
wp site option delete pod_aggregator_printify_api_token
wp site option delete pod_aggregator_gelato_api_key
wp site option delete pod_aggregator_settings
```

This does NOT remove the plugin itself — it just wipes all data.
