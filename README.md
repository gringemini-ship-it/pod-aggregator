# POD Aggregator — Print-on-Demand WooCommerce Integration

> Connects your WordPress/WooCommerce store to Print-on-Demand providers. Supports **Printful**, **Printify**, and **Gelato** — add one or all three. Architecture is fully extensible for additional providers.

---

## What This Plugin Does

POD Aggregator bridges WooCommerce with Print-on-Demand providers so store owners can:

1. **Browse** provider catalogs directly from WordPress admin (Printful, Printify, Gelato)
2. **Import** POD products as WooCommerce products with automatic pricing
3. **Let customers design** custom products (text, images, positioning) before adding to cart
4. **Auto-submit** orders to the provider(s) when WooCommerce checkout completes
5. **Sync** inventory and order status on a schedule or on-demand
6. **Receive webhooks** from providers and update WooCommerce order statuses automatically

---

## Feature Overview

| Feature | Description |
|---------|-------------|
| **Multi-Provider Support** | Connect Printful, Printify, and Gelato simultaneously |
| **Provider Catalog Import** | Sync provider catalogs, then browse and import products as WooCommerce variable products with one click |
| **Product Sync** | Scheduled sync (configurable interval) keeps WooCommerce prices/inventory aligned per provider |
| **Visual Product Customizer** | Front-end design editor for text and image personalisation |
| **Per-Product Pricing** | Configurable markup % per product, per provider (base cost + markup) |
| **Cart Meta** | Design ID, provider, print area stored on cart items |
| **Order Auto-Submission** | Orders forwarded to the correct provider on WooCommerce checkout completion; multi-provider orders are split automatically |
| **Webhook Processing** | Receive and process order status updates from all connected providers |
| **Manual Sync (UI)** | AJAX-powered "Sync Now" button in the admin dashboard for any provider |
| **WP-CLI Commands** | `wp pod syncProducts`, `wp pod syncOrders`, `wp pod testConnection` |
| **300 DPI Print File Generation** | Generated server-side at checkout using GD library |
| **Single-Site & Multisite** | Works on both single-site and multisite WordPress installations |
| **Custom Post Types** | `pod_product` CPT (synced catalog data), `pod_design` CPT (saved designs) |
| **REST API** | Design save/load/delete, print file generation, sync triggers via authenticated REST endpoints |

---

## Requirements

| Requirement | Version / Detail |
|-------------|-----------------|
| **WordPress** | 6.9 or higher |
| **PHP** | 7.4 or higher (8.x recommended) |
| **WooCommerce** | 8.x or higher |
| **PHP Extensions** | `gd` (for print file generation), `mbstring`, `curl` |
| **Provider accounts** | At least one of: Printful, Printify, or Gelato |
| **WP-CLI** | Optional — for manual sync and cron management |

### Provider Account Setup

| Provider | Sign Up | API Key Location | Additional Setup |
|----------|---------|------------------|------------------|
| **Printful** | [printful.com](https://www.printful.com) | Account → API → Create API key | Create a "Manual Order / API" store in Printful Dashboard → Stores |
| **Printify** | [printify.com](https://printify.com) | My Profile → API → Generate token | Shop ID from Printify → My Store |
| **Gelato** | [gelato.com](https://gelato.com) | Settings → API → Create API key | — |

Check your PHP extensions:

```bash
php -m | grep -E "gd|mbstring|curl"
```

---

## Documentation

All documentation is in the `docs/` directory:

| File | Description |
|------|-------------|
| **[docs/customers.md](docs/customers.md)** | Installation, setup (all 3 providers), product customiser, cart & checkout, uninstall |
| **[docs/api.md](docs/api.md)** | REST API endpoints (designs, print files, sync) + webhooks (Printful, Printify, Gelato) |
| **[docs/developers.md](docs/developers.md)** | Dev environment, running/writing tests, linting, WP-CLI, architecture, contributing, release |
| **[docs/reference.md](docs/reference.md)** | Hooks & filters, folder structure, security, troubleshooting, multisite |

---

## Supported Providers

Each provider has its own tab in **Settings → POD Aggregator**. All three can be connected simultaneously. Products from different providers are tracked independently; orders are automatically routed to the correct provider.

| Provider | Products | Orders | Webhooks |
|----------|----------|--------|----------|
| Printful | ✅ Catalog sync (full catalog via categories) | ✅ Submit + track | ✅ HMAC-SHA256 signature |
| Printify | ✅ Catalog sync | ✅ Submit + track | ✅ HMAC-SHA256 signature |
| Gelato | ✅ Catalog sync | ✅ Submit + track | ✅ Bearer token auth |

---

## Screenshots

*(Screenshots would be placed in `assets/screenshots/` — see the `assets/` directory.)*

- **Admin Dashboard** — Multi-provider status panel with per-provider "Sync Now" buttons
- **Admin Settings** — Tabbed settings page (Printful / Printify / Gelato) under "My Sites → Network Admin → Settings"
- **Product Import** — Submenu page listing each provider's catalog with import buttons
- **Preset Templates** — Admin page for managing pre-made design templates
- **Design Customizer** — Front-end block editor shown on the product page
- **Cart Preview** — Inline design summary below cart item name
- **Order Confirmation** — WooCommerce order details showing design metadata and provider
