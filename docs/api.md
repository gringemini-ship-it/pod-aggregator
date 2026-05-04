# REST API & Webhooks Reference

Programmatic access to POD Aggregator for headless frontends, mobile apps, or third-party integrations.

**Base URL:** `https://yoursite.com/wp-json/pod-aggregator/v1/`

All endpoints require authentication unless noted.

---

## Authentication

### WooCommerce REST API (Recommended)

Use your WooCommerce REST API consumer credentials. All endpoints that affect orders or designs require a WooCommerce REST API key with **Read/Write** permissions.

```
Authorization: Basic BASE64(consumer_key : consumer_secret)
```

Or pass as query parameters:

```
?consumer_key=ck_xxxx&consumer_secret=cs_xxxx
```

### Application Passwords (WordPress Native)

WordPress Application Passwords work for design save/load/delete endpoints.

```
Authorization: Basic BASE64(username : application_password)
```

### WP-CLI (No Auth Required)

WP-CLI commands bypass authentication entirely — useful for automated scripts and cron triggers:

```bash
wp pod syncProducts --provider=printify
wp pod syncOrders --provider=gelato
wp pod testConnection --provider=printful
```

---

## Endpoints

### Save Design

Create or update a design. Returns a design UUID.

```
POST /designs
```

**Request body:**

```json
{
  "product_id": 123,
  "print_area": "front",
  "elements": [
    {
      "type": "text",
      "text": "Hello World",
      "font_family": "Arial",
      "font_size": 24,
      "color": "#000000",
      "position_x": 100,
      "position_y": 150,
      "width": 200,
      "height": 50,
      "z_index": 1
    },
    {
      "type": "image",
      "src": "https://example.com/uploads/my-logo.png",
      "position_x": 50,
      "position_y": 200,
      "width": 150,
      "height": 150,
      "z_index": 2
    }
  ]
}
```

**Response `201 Created`:**

```json
{
  "success": true,
  "data": {
    "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "product_id": 123,
    "print_area": "front",
    "created_at": "2025-06-15T10:30:00Z"
  }
}
```

**Update existing design:**

```
PUT /designs/{design_id}
```

Same body as POST. Returns `200 OK` if updated, `404 Not Found` if the design ID does not exist.

---

### Load Design

Retrieve a saved design by its UUID.

```
GET /designs/{design_id}
```

**Response `200 OK`:**

```json
{
  "success": true,
  "data": {
    "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "product_id": 123,
    "print_area": "front",
    "elements": [...],
    "created_at": "2025-06-15T10:30:00Z",
    "updated_at": "2025-06-15T14:22:00Z"
  }
}
```

**Response `404 Not Found`:**

```json
{
  "success": false,
  "message": "Design not found"
}
```

---

### Delete Design

Permanently delete a saved design.

```
DELETE /designs/{design_id}
```

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "Design deleted"
}
```

**Response `404 Not Found`:**

```json
{
  "success": false,
  "message": "Design not found"
}
```

---

### Generate Print File

Generate a 300 DPI print-ready image file for a design. Returns a URL to the generated file.

```
POST /print-files
```

**Request body:**

```json
{
  "design_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "format": "png"
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `design_id` | string (UUID) | Yes | Design to generate print file for |
| `format` | string | No | `png` (default) or `jpg` |

**Response `200 OK`:**

```json
{
  "success": true,
  "data": {
    "print_file_url": "https://yoursite.com/wp-content/uploads/pod-aggregator/print-files/a1b2c3d4.png",
    "dpi": 300,
    "width_px": 4500,
    "height_px": 6000,
    "format": "png"
  }
}
```

> The print file is generated using the GD library. For best results, upload images at 300 DPI when creating the design. The server-side generation scales the design to 300 DPI regardless of input resolution, but upscaling a low-resolution image will result in blurry prints.

---

## Webhook Reference

POD Aggregator receives webhook events from Printful, Printify, and Gelato to automatically update WooCommerce order statuses. Each provider has its own webhook URL, shown in **Settings → POD Aggregator** under the respective provider tab.

### Webhook URLs

Copy the URL for each provider from **Settings → POD Aggregator** and register it in your provider's dashboard:

| Provider | Webhook URL | Auth Method |
|----------|-------------|-------------|
| Printful | `https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=printful` | HMAC-SHA256 signature |
| Printify | `https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=printify` | HMAC-SHA256 signature |
| Gelato | `https://yoursite.com/wp-json/pod-aggregator/v1/webhook?provider=gelato` | Bearer token |

---

### Printful Webhooks

#### Setting Up in Printful

1. Log into Printful → **[Webhooks](https://www.printful.com/dashboard)**
2. Click **Add Webhook**
3. Enter your Printful webhook URL (from Settings)
4. Select the events you want to receive:
   - `order_created` — Order received by Printful
   - `order_processing` — Printful started production
   - `order_shipped` — Shipped (with tracking number)
   - `order_cancelled` — Order cancelled
   - `order_failed` — Production failed
5. Copy the **Webhook Secret** shown by Printful
6. In WordPress, go to **Settings → POD Aggregator → Printful → Webhook Secret** and paste it
7. Click **Save Changes**

#### Verifying the Signature

Printful signs every webhook payload with HMAC-SHA256 using your webhook secret. The signature is in the `Printful-Signature` header:

```
Printful-Signature: sha256=abc123def456...
```

**How verification works:**

1. Raw request body is read
2. HMAC-SHA256 is computed using the stored webhook secret
3. The computed signature is compared to the header value (timing-safe comparison)
4. If they don't match, the request is rejected with `401 Unauthorized`

**If verification fails**, the plugin returns:

```json
{
  "success": false,
  "message": "Invalid webhook signature"
}
```

HTTP status: `401 Unauthorized`

#### Printful Webhook Events

| Event | WooCommerce Action | Description |
|-------|-------------------|-------------|
| `order_created` | (no change) | Order received by Printful |
| `order_processing` | Set status `processing` | Printful accepted and started production |
| `order_shipped` | Set status `completed` | Shipped — WooCommerce order marked complete; tracking note added |
| `order_cancelled` | Set status `cancelled` | Printful cancelled the order |
| `order_failed` | Set status `failed` | Production failed (e.g., image quality issue) |

**Tracking information** — When `order_shipped` is received:

```json
{
  "order_id": 12345,
  "tracking_number": "1Z999AA10123456784",
  "tracking_url": "https://www.ups.com/track?tracknum=1Z999AA10123456784",
  "carrier": "UPS"
}
```

This is added as a note to the WooCommerce order.

**Order ID mapping:** Printful webhook `order_id` is stored as `_pod_printful_order_id` on the WooCommerce order.

---

### Printify Webhooks

#### Setting Up in Printify

1. Log into Printify → **My Profile → Webhooks**
2. Click **Add Webhook**
3. Enter your Printify webhook URL (from Settings)
4. Select events for order updates
5. Copy the **Webhook Secret** and paste it into **Settings → POD Aggregator → Printify → Webhook Secret**

#### Verifying the Signature

Printify signs webhook payloads with HMAC-SHA256. The signature is in the `Printify-Signature` header:

```
Printify-Signature: sha256=abc123def456...
```

Verification follows the same pattern as Printful — timing-safe HMAC-SHA256 comparison against the stored secret. Invalid signatures return `401 Unauthorized`.

#### Printify Webhook Events

| Event | WooCommerce Action | Description |
|-------|-------------------|-------------|
| `created` | (no change) | Order received by Printify |
| `processing` | Set status `processing` | Printify accepted and started production |
| `shipped` | Set status `completed` | Shipped with tracking; note added to order |
| `cancelled` | Set status `cancelled` | Printify cancelled the order |
| `failed` | Set status `failed` | Production failed |

**Order ID mapping:** Printify order ID is stored as `_pod_printify_order_id` on the WooCommerce order.

---

### Gelato Webhooks

#### Setting Up in Gelato

1. Log into Gelato → **Settings → Webhooks**
2. Add a webhook pointing to your Gelato webhook URL (from Settings)
3. Gelato does not require a separate webhook secret — authentication is done via the **Bearer token** in the `Authorization` header of every webhook request

#### Verifying the Request

Gelato sends a Bearer token in the `Authorization` header:

```
Authorization: Bearer YOUR_GELATO_API_KEY
```

The plugin verifies this token against the Gelato API key stored in **Settings → POD Aggregator → Gelato**. If the token does not match, the request is rejected with `401 Unauthorized`.

#### Gelato Webhook Events

| Event | WooCommerce Action | Description |
|-------|-------------------|-------------|
| `created` | (no change) | Order received by Gelato |
| `processing` | Set status `processing` | Gelato accepted and started production |
| `shipped` | Set status `completed` | Shipped with tracking; note added to order |
| `cancelled` | Set status `cancelled` | Gelato cancelled the order |
| `failed` | Set status `failed` | Production failed |

**Order ID mapping:** Gelato order ID is stored as `_pod_gelato_order_id` on the WooCommerce order.

---

## Error Responses

All endpoints return a consistent error format:

```json
{
  "success": false,
  "message": "Human-readable error description",
  "code": "ERROR_CODE"
}
```

| HTTP Status | `code` | Meaning |
|-------------|--------|---------|
| `400` | `INVALID_REQUEST` | Malformed JSON or missing required field |
| `401` | `AUTH_FAILED` | Missing or invalid authentication / webhook signature |
| `403` | `FORBIDDEN` | Authenticated user lacks required capability |
| `404` | `NOT_FOUND` | Resource (design, product) does not exist |
| `409` | `CONFLICT` | Design conflict (e.g., duplicate) |
| `422` | `VALIDATION_ERROR` | Request is valid JSON but fails business logic validation |
| `429` | `RATE_LIMITED` | Too many requests — back off and retry |
| `500` | `SERVER_ERROR` | Unexpected server error |
| `503` | `SERVICE_UNAVAILABLE` | Provider API is unreachable |

Rate limiting: if too many requests are made in a short period, the API returns `429 Too Many Requests`.
