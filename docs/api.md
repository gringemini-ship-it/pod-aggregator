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
wp pod-aggregator sync
wp pod-aggregator design list
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

### Sync Products

Trigger a product catalog sync from Printful. Returns immediately; sync runs in the background.

```
POST /sync
```

**Request body (all fields optional):**

```json
{
  "product_ids": [123, 456],
  "full_refresh": false
}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `product_ids` | array of ints | Sync only these Printful product IDs. If omitted, sync all. |
| `full_refresh` | boolean | If `true`, refetches full catalog from Printful. Default `false` (incremental). |

**Response `202 Accepted`:**

```json
{
  "success": true,
  "message": "Sync started",
  "data": {
    "job_id": "sync_20250615_103000",
    "products_queued": 42
  }
}
```

To check sync status, use the WP-CLI command:

```bash
wp pod-aggregator sync --dry-run
```

---

## Webhook Reference

POD Aggregator receives webhook events from Printful to automatically update WooCommerce order statuses.

### Setting Up the Webhook in Printful

1. Log into Printful → **[Webhooks](https://www.printful.com/dashboard)**
2. Click **Add Webhook**
3. Enter your webhook URL:
   ```
   https://yoursite.com/wp-json/pod-aggregator/v1/webhook
   ```
4. Select the events you want to receive:
   - `order_created` — Order received by Printful
   - `order_processing` — Printful started production
   - `order_shipped` — Shipped (with tracking number)
   - `order_cancelled` — Order cancelled
   - `order_failed` — Production failed
5. Copy the **Webhook Secret** shown by Printful
6. In WordPress, go to **Settings → POD Aggregator → Webhook Secret** and paste it
7. Click **Save Changes**

### Verifying the Webhook Signature

Printful signs every webhook payload with HMAC-SHA256 using your webhook secret. POD Aggregator verifies this signature on every incoming webhook request.

The signature is sent in the `Printful-Signature` header:

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

### Webhook Events Reference

| Event | WooCommerce Action | Description |
|-------|-------------------|-------------|
| `order_created` | (no change) | Order received by Printful |
| `order_processing` | Set status `processing` | Printful accepted and started production |
| `order_shipped` | Set status `completed` | Shipped — WooCommerce order marked complete; tracking note added |
| `order_cancelled` | Set status `cancelled` | Printful cancelled the order |
| `order_failed` | Set status `failed` | Production failed (e.g., image quality issue) |

**Tracking information** — When `order_shipped` is received, Printful includes tracking data:

```json
{
  "order_id": 12345,
  "tracking_number": "1Z999AA10123456784",
  "tracking_url": "https://www.ups.com/track?tracknum=1Z999AA10123456784",
  "carrier": "UPS"
}
```

This is added as a note to the WooCommerce order.

### Order ID Mapping

Printful webhook `order_id` is the Printful order ID. POD Aggregator stores the Printful order ID in WooCommerce order meta as `_pod_printful_order_id`. The plugin uses this to match incoming webhooks to the correct WooCommerce order.

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
| `401` | `AUTH_FAILED` | Missing or invalid authentication |
| `403` | `FORBIDDEN` | Authenticated user lacks required capability |
| `404` | `NOT_FOUND` | Resource (design, product) does not exist |
| `409` | `CONFLICT` | Design conflict (e.g., duplicate) |
| `422` | `VALIDATION_ERROR` | Request is valid JSON but fails business logic validation |
| `500` | `SERVER_ERROR` | Unexpected server error |
| `503` | `SERVICE_UNAVAILABLE` | Printful API is unreachable |

Rate limiting: if too many requests are made in a short period, the API returns `429 Too Many Requests`.
