# POD Aggregator — Print-on-Demand WooCommerce Integration

> Connects your WordPress/WooCommerce store to Print-on-Demand providers. Currently supports **Printful**. Architecture is ready for Printify and Gelato.

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
| **Visual Product Customizer** | Front-end design editor for text and image personalisation |
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

## Requirements

| Requirement | Version / Detail |
|-------------|-----------------|
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

---

## Documentation

All documentation is in the `docs/` directory:

| File | Description |
|------|-------------|
| **[docs/customers.md](docs/customers.md)** | Installation, setup, product customiser, cart & checkout, uninstall |
| **[docs/api.md](docs/api.md)** | REST API endpoints (save/load/delete design, generate print file, sync) + webhooks |
| **[docs/developers.md](docs/developers.md)** | Dev environment, running/writing tests, linting, WP-CLI, architecture, contributing, release |
| **[docs/reference.md](docs/reference.md)** | Hooks & filters, folder structure, security, troubleshooting, multisite |

---

## Screenshots

*(Screenshots would be placed in `assets/screenshots/` — see the `assets/` directory.)*

- **Admin Settings** — Network settings page under "My Sites → Network Admin → Settings"
- **Product Import** — Submenu page listing Printful catalog with import buttons
- **Preset Templates** — Admin page for managing pre-made design templates
- **Design Customizer** — Front-end block editor shown on the product page
- **Cart Preview** — Inline design summary below cart item name
- **Order Confirmation** — WooCommerce order details showing design metadata
