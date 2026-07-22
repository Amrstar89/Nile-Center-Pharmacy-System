# Nile Center ERP v3.0 - REST API Documentation

> **Base URL:** `https://your-domain.com/api`  
> **Version:** v1  
> **Authentication:** JWT Bearer Token  
> **Content-Type:** `application/json`

---

## Quick Start for Mobile Developers

### 1. Authentication Flow

```
Step 1: POST /api/tenant/auth/login
  Body: { "username": "admin", "password": "admin123" }
  Response: { "access_token", "refresh_token", "expires_in", "user", "tenant" }

Step 2: Use access_token in all subsequent requests
  Header: Authorization: Bearer <access_token>

Step 3: When access_token expires (401), refresh it
  POST /api/tenant/auth/refresh
  Body: { "refresh_token": "<refresh_token>" }
  Response: { "access_token", "refresh_token", "expires_in" }

Step 4: Logout
  POST /api/tenant/auth/logout
  Header: Authorization: Bearer <access_token>
```

### 2. Tenant Resolution

The API automatically detects which pharmacy tenant to serve:

| Method | How |
|--------|-----|
| **Custom Domain** | `pharmacy.com` → resolves to that pharmacy |
| **Subdomain** | `pharmacy.nilecenter.com` → resolves via slug |
| **API Key** | Header: `X-API-Key: <key>` |
| **Tenant ID** | Header: `X-Tenant-ID: <id>` |

### 3. Testing with cURL

```bash
# Login
 curl -X POST https://your-domain.com/api/tenant/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Search products (use token from login)
 curl "https://your-domain.com/api/tenant/products/search?q=panadol&store_id=1" \
  -H "Authorization: Bearer <your_access_token>"

# Create sale invoice
 curl -X POST https://your-domain.com/api/tenant/sales/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <your_access_token>" \
  -d '{
    "customer_id": 1,
    "branch_id": 1,
    "store_id": 1,
    "payment_method": "cash",
    "paid_amount": 100,
    "items": [
      { "product_id": 1, "quantity": 2, "price": 25.5 }
    ]
  }'
```

---

## Authentication Endpoints

### `POST /api/tenant/auth/login`

Login with username/password to receive JWT tokens.

**Request Body:**
```json
{
  "username": "admin",
  "password": "admin123",
  "device_name": "iPhone 15 Pro"  // optional, for tracking
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "System Administrator",
    "username": "admin",
    "role": "admin",
    "branch_id": 1,
    "store_id": 1
  },
  "tenant": {
    "id": 1,
    "name": "صيدلية النيل",
    "slug": "nile-pharmacy",
    "status": "active",
    "is_trial": false,
    "features": {
      "pos": true,
      "sales": true,
      "api": true
    }
  }
}
```

---

### `POST /api/tenant/auth/refresh`

Refresh an expired access token.

**Request Body:**
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

---

### `GET /api/tenant/auth/profile`

Get current user profile and permissions.

**Headers:** `Authorization: Bearer <token>`

**Success Response (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "System Administrator",
    "username": "admin",
    "email": "admin@nilecenter.com",
    "phone": "01001234567",
    "role": "admin",
    "avatar": null,
    "branch_id": 1,
    "branch_name": "الفرع الرئيسي",
    "store_id": 1,
    "store_name": "المخزن الرئيسي"
  },
  "tenant": {
    "id": 1,
    "name": "صيدلية النيل",
    "features": {
      "pos": true,
      "sales": true,
      "inventory": true
    }
  },
  "permissions": ["*"]
}
```

---

### `POST /api/tenant/auth/logout`

Logout and revoke current token.

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "all_devices": false
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح"
}
```

---

## Product Endpoints

### `GET /api/tenant/products/search`

Search products with filtering.

**Headers:** `Authorization: Bearer <token>` or `X-API-Key: <key>`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `q` | string | No | Search keyword (name, code, barcode, scientific name) |
| `barcode` | string | No | Exact barcode match |
| `branch_id` | int | No | Filter by branch |
| `store_id` | int | No | Include stock for this store |
| `category_id` | int | No | Filter by category |
| `company_id` | int | No | Filter by company |
| `has_stock` | int | No | `1` = in stock only, `0` = out of stock only |
| `page` | int | No | Page number (default: 1) |
| `per_page` | int | No | Items per page (default: 25, max: 100) |

**Success Response (200):**
```json
{
  "success": true,
  "products": [
    {
      "id": 1,
      "code": "PRD-001",
      "barcode": "6221234567890",
      "barcode2": null,
      "name": "بنادول أزرق 500mg",
      "scientific_name": "Paracetamol",
      "category": { "id": 1, "name": "أدوية" },
      "company": { "id": 1, "name": "GSK" },
      "type": { "id": 1, "name": "أصلي" },
      "unit": { "id": 1, "name": "علبة" },
      "pricing": {
        "purchase_price": 15.00,
        "sale_price": 22.50,
        "wholesale_price": 20.00,
        "avg_purchase_price": 14.80,
        "min_price": 20.00
      },
      "stock": {
        "quantity": 150,
        "reorder_point": 20
      },
      "is_active": true
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "total_pages": 6,
    "has_next": true,
    "has_prev": false
  },
  "search_meta": {
    "query": "panadol",
    "results_count": 25,
    "total_count": 150
  }
}
```

---

## Customer Endpoints

### `GET /api/tenant/customers/list`

List customers with search and filters.

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `q` | string | No | Search by name, phone, code |
| `class_id` | int | No | Filter by customer class |
| `area_id` | int | No | Filter by area |
| `has_balance` | int | No | `1` = customers with outstanding balance |
| `page` | int | No | Page number |
| `per_page` | int | No | Items per page |

**Success Response (200):**
```json
{
  "success": true,
  "customers": [
    {
      "id": 1,
      "code": "CUST-001",
      "name": "أحمد محمد",
      "phone": "01001234567",
      "phone2": null,
      "whatsapp": "01001234567",
      "email": "ahmed@example.com",
      "address": "شارع النيل، القاهرة",
      "area": { "id": 1, "name": "المعادي" },
      "class": { "id": 1, "name": "عادي" },
      "financial": {
        "balance": 1250.00,
        "total_debit": 5000.00,
        "total_credit": 3750.00,
        "max_credit_limit": 5000.00,
        "max_credit_days": 30,
        "discount_percent": 0.00
      },
      "is_active": true
    }
  ],
  "pagination": { "current_page": 1, "per_page": 25, "total": 50, "total_pages": 2 }
}
```

---

## Sales Endpoints

### `POST /api/tenant/sales/create`

Create a new sales invoice.

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "customer_id": 1,
  "branch_id": 1,
  "store_id": 1,
  "payment_method": "cash",
  "paid_amount": 100.00,
  "discount": 5.00,
  "tax": 0,
  "notes": "عميل مميز",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "price": 25.50,
      "discount": 0,
      "batch_id": null
    },
    {
      "product_id": 3,
      "quantity": 1,
      "price": 50.00,
      "discount": 2.00
    }
  ]
}
```

**Validation Rules:**
- `customer_id`, `branch_id`, `store_id`: Required integers
- `items`: Required, non-empty array
- Each item: `product_id` > 0, `quantity` > 0, `price` >= 0
- Stock is automatically checked and deducted (FIFO)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Invoice created successfully",
  "invoice": {
    "id": 152,
    "invoice_number": "SI-2026000152",
    "customer_id": 1,
    "branch_id": 1,
    "store_id": 1,
    "payment_method": "cash",
    "subtotal": 101.00,
    "discount": 5.00,
    "tax": 0,
    "final_total": 96.00,
    "paid_amount": 100.00,
    "remaining": -4.00,
    "total_profit": 32.50,
    "status": "confirmed",
    "items_count": 2,
    "created_at": "2026-07-22 14:30:00"
  },
  "items": [
    {
      "product_id": 1,
      "product_name": "بنادول أزرق 500mg",
      "product_code": "PRD-001",
      "quantity": 2,
      "unit_price": 25.50,
      "discount": 0,
      "total": 51.00,
      "cost": 29.60,
      "profit": 21.40,
      "batch_id": null
    }
  ]
}
```

**Error Responses:**
- `400 INVALID_ITEMS` - Items array is empty
- `404 CUSTOMER_NOT_FOUND` - Customer doesn't exist
- `400 INSUFFICIENT_STOCK` - Not enough stock for item
- `404 PRODUCT_NOT_FOUND` - Product doesn't exist

---

### `GET /api/tenant/sales/list`

List sales invoices with filters.

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `customer_id` | int | Filter by customer |
| `branch_id` | int | Filter by branch |
| `store_id` | int | Filter by store |
| `from_date` | date | Start date (YYYY-MM-DD) |
| `to_date` | date | End date (YYYY-MM-DD) |
| `status` | string | Invoice status |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Success Response (200):**
```json
{
  "success": true,
  "invoices": [
    {
      "id": 152,
      "invoice_number": "SI-2026000152",
      "customer": { "id": 1, "name": "أحمد محمد" },
      "branch": { "id": 1, "name": "الفرع الرئيسي" },
      "store": { "id": 1, "name": "المخزن الرئيسي" },
      "user": { "id": 1, "name": "System Administrator" },
      "payment_method": "cash",
      "financial": {
        "subtotal": 101.00,
        "discount": 5.00,
        "tax": 0,
        "final_total": 96.00,
        "paid": 100.00,
        "remaining": -4.00,
        "profit": 32.50
      },
      "items_count": 2,
      "status": "confirmed",
      "created_at": "2026-07-22 14:30:00"
    }
  ],
  "pagination": { "current_page": 1, "per_page": 25, "total": 150, "total_pages": 6 }
}
```

---

## Inventory Endpoints

### `GET /api/tenant/inventory/stock`

Check stock levels and alerts.

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `store_id` | int | Filter by store |
| `branch_id` | int | Filter by branch |
| `product_id` | int | Get specific product |
| `category_id` | int | Filter by category |
| `low_stock` | int | `1` = only low stock items |
| `q` | string | Search by name/code/barcode |
| `page` | int | Page number |

**Success Response (200):**
```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "code": "PRD-001",
      "barcode": "6221234567890",
      "name": "بنادول أزرق 500mg",
      "scientific_name": "Paracetamol",
      "category": { "id": 1, "name": "أدوية" },
      "company": { "id": 1, "name": "GSK" },
      "pricing": {
        "purchase_price": 15.00,
        "sale_price": 22.50,
        "avg_cost": 14.80
      },
      "stock": {
        "quantity": 150,
        "reorder_point": 20,
        "status": "normal",
        "near_expiry": 10,
        "expired": 0,
        "nearest_expiry_date": "2027-01-15"
      }
    }
  ],
  "pagination": { "current_page": 1, "per_page": 25, "total": 500 },
  "filters": { "store_id": 1, "low_stock_only": false }
}
```

**Stock Status Values:**
- `normal` - Stock level is OK
- `low_stock` - Stock at or below reorder point
- `out_of_stock` - Zero quantity

---

## Reports Endpoints

### `GET /api/tenant/reports/dashboard`

Get dashboard KPIs, trends, and analytics.

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `branch_id` | int | null | Filter by branch |
| `store_id` | int | null | Filter by store |
| `from_date` | date | 1st of month | Start date |
| `to_date` | date | Today | End date |

**Success Response (200):**
```json
{
  "success": true,
  "period": { "from": "2026-07-01", "to": "2026-07-22" },
  "kpis": {
    "total_sales": {
      "amount": 125000.00,
      "count": 450,
      "profit": 35000.00
    },
    "today_sales": {
      "amount": 5200.00,
      "count": 18
    },
    "unpaid_amount": {
      "amount": 15000.00,
      "count": 32
    },
    "customers": 520,
    "products": 1500,
    "low_stock_items": 12,
    "inventory_value": {
      "cost": 250000.00,
      "retail": 375000.00,
      "potential_profit": 125000.00,
      "stocked_products": 1480
    }
  },
  "daily_trend": [
    { "date": "2026-07-01", "sales": 5800.00, "profit": 1650.00, "invoices": 22 },
    { "date": "2026-07-02", "sales": 6200.00, "profit": 1800.00, "invoices": 25 }
  ],
  "top_products": [
    { "id": 1, "name": "بنادول", "code": "PRD-001", "quantity_sold": 200, "revenue": 4500.00, "profit": 1600.00 }
  ],
  "top_customers": [
    { "id": 1, "name": "أحمد محمد", "purchases": 15, "amount": 3500.00 }
  ],
  "payment_methods": [
    { "method": "cash", "count": 350, "total": 98000.00 },
    { "method": "credit", "count": 100, "total": 27000.00 }
  ]
}
```

---

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": "ERROR_CODE",
  "message": "Human-readable description in Arabic/English"
}
```

### HTTP Status Codes

| Code | Meaning | When |
|------|---------|------|
| `200` | OK | Successful GET/POST |
| `201` | Created | Resource created (invoice, etc.) |
| `400` | Bad Request | Validation error, missing fields |
| `401` | Unauthorized | Invalid/missing token |
| `403` | Forbidden | Insufficient permissions, expired tenant |
| `404` | Not Found | Resource not found |
| `405` | Method Not Allowed | Wrong HTTP method |
| `500` | Server Error | Internal server error |

### Common Error Codes

| Code | Description | Resolution |
|------|-------------|------------|
| `UNAUTHORIZED` | Missing or invalid token | Include valid Bearer token |
| `TOKEN_EXPIRED` | Access token expired | Use refresh token |
| `TOKEN_REVOKED` | Token was invalidated | Login again |
| `TENANT_NOT_FOUND` | No tenant resolved | Check domain/API key |
| `TENANT_EXPIRED` | Subscription expired | Renew subscription |
| `MISSING_FIELDS` | Required fields missing | Check request body |
| `INVALID_CREDENTIALS` | Wrong username/password | Check credentials |
| `INSUFFICIENT_STOCK` | Not enough inventory | Reduce quantity or check stock first |
| `FEATURE_NOT_AVAILABLE` | Not in current plan | Upgrade subscription |

---

## Rate Limiting

- **Authenticated requests:** 1000 requests/hour per user
- **API Key requests:** 500 requests/hour per key
- **Login attempts:** 5 attempts per 15 minutes per IP

Rate limit headers (if supported):
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1690000000
```

---

## Arabic Language Support

All endpoints support Arabic responses. Set header:
```
Accept-Language: ar
```

Default messages are bilingual (Arabic primary, English secondary).

---

## Mobile App Integration Checklist

- [ ] **Authentication**: Implement login → store tokens → auto-refresh
- [ ] **Product Search**: Search with barcode scanning support
- [ ] **Cart Management**: Build cart locally, submit as invoice
- [ ] **Customer Selection**: Search and select customers before checkout
- [ ] **Offline Support**: Queue sales when offline, sync when connected
- [ ] **Dashboard**: Show KPI cards and daily sales chart
- [ ] **Stock Alerts**: Show notifications for low stock items
- [ ] **Multi-tenant**: Support multiple pharmacy logins

---

**Last Updated:** 2026-07-22  
**API Version:** v1.0  
**For Support:** support@nilecenter.com
