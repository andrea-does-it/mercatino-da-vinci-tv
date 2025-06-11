# API Documentation

## Overview

The application provides several REST API endpoints for AJAX functionality and administrative operations. All APIs are located in the `/api/` directory and return JSON responses.

## Authentication

Most administrative APIs require authentication and role verification:
```php
global $loggedInUser;
if (!$loggedInUser || $loggedInUser->user_type != 'admin') {
    header('Content-type: application/json', false, 400);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
```

## Administrative APIs

### Categories API
**Endpoint**: `/api/admin/categories.php`

#### Get Subcategories
```http
GET /api/admin/categories.php?action=getSubcategories&parentId=123
```

**Response**:
```json
{
    "data": {
        "parent": {
            "id": 123,
            "name": "Mathematics",
            "url": "/shop/category/123-mathematics"
        },
        "children": [
            {
                "id": 124,
                "name": "Algebra",
                "is_selected": false,
                "url": "/shop/category/124-algebra"
            }
        ]
    }
}
```

**Error Response**:
```json
{
    "error": "true",
    "message": "invalid parentId"
}
```

### File Upload API
**Endpoint**: `/api/admin/upload.php`

#### Upload Product Images
```http
POST /api/admin/upload.php
Content-Type: multipart/form-data

productId: 123
file-0: [image file]
file-1: [image file]
tmpDir: unique_temp_directory (optional)
```

**Response**:
```json
{
    "images": [
        {
            "id": 456,
            "product_id": 123,
            "image_extension": "jpg",
            "title": "",
            "alt": "",
            "order_number": 0
        }
    ],
    "tmpDir": "unique_temp_directory"
}
```

#### Update Image Details
```http
POST /api/admin/upload.php

operation: "img-details"
id: 456
title: "Product front view"
alt: "Mathematics textbook cover"
order: 1
```

**Response**:
```json
{
    "result": "ok"
}
```

### File Deletion API
**Endpoint**: `/api/admin/delete.php`

#### Remove Image
```http
POST /api/admin/delete.php

imageId: 456
```

**Response**:
```json
{
    "result": "success"
}
```

#### Remove Temporary Images
```http
POST /api/admin/delete.php

action: "removeTempImages"
tmpDir: "unique_temp_directory"
```

**Response**:
```json
{
    "result": "success"
}
```

## Shopping APIs

### Cart Management API
**Endpoint**: `/api/shop/cart.php`

#### Set Shipping Method
```http
POST /api/shop/cart.php

action: "setShipmentMethod"
shipmentMethod: 2
```

**Response**: Status 200 (no content)

#### Update Cart Quantity
```http
POST /api/shop/cart.php

action: "incrementOrDecrement"
cart_id: 123
product_id: 456
plus: "QUALCOSA"  // or minus: "QUALCOSA"
```

**Response**:
```json
{
    "cartTotal": [
        {
            "cart_id": 123,
            "num_products": 3,
            "total": "45.50",
            "shipment_price": "5.00"
        }
    ],
    "cart_items": [
        {
            "cart_id": 123,
            "product_name": "Mathematics Textbook",
            "product_id": 456,
            "quantity": 2,
            "single_price": "20.00",
            "total_price": "40.00"
        }
    ]
}
```

### Product Search API
**Endpoint**: `/api/shop/search-products.php`

#### Search Products
```http
GET /api/shop/search-products.php?search=mathematics
```

**Response**:
```json
{
    "data": [
        {
            "id": 123,
            "name": "Advanced Mathematics",
            "price": "25.00",
            "category": "Mathematics",
            "url": "/shop/product/123-advanced-mathematics",
            "image_id": "456"
        }
    ]
}
```

### Quick Add to Cart API
**Endpoint**: `/api/shop/product-list.php`

#### Add Product to Cart
```http
POST /api/shop/product-list.php

id: 123
```

**Response**:
```json
{
    "result": "success",
    "message": "Aggiunto al carrello"
}
```

## Payment APIs

### Stripe Integration
**Endpoint**: `/shop/payments/stripe/stripe-key.php`

#### Get Publishable Key
```http
GET /shop/payments/stripe/stripe-key.php
```

**Response**:
```json
{
    "publishableKey": "pk_test_xxxxxxxxxx"
}
```

**Endpoint**: `/shop/payments/stripe/pay.php`

#### Process Payment
```http
POST /shop/payments/stripe/pay.php
Content-Type: application/json

{
    "paymentMethodId": "pm_xxxxxxxxxx"
}
```

**Success Response**:
```json
{
    "success": true,
    "clientSecret": "pi_xxxxxxxxxx_secret_xxxxxxxxxx",
    "orderId": 789
}
```

**Error Response**:
```json
{
    "success": false,
    "orderId": 789
}
```

### PayPal Integration
PayPal integration uses redirect-based flow through:
- `/shop/payments/paypal/checkout.php` - Initialize payment
- `/shop/payments/paypal/pay.php` - Handle callback

## Error Handling

### Standard Error Format
```json
{
    "error": "true",
    "message": "Descriptive error message"
}
```

### HTTP Status Codes
- `200` - Success
- `400` - Bad Request (validation errors, missing parameters)
- `403` - Forbidden (authentication/authorization failures)
- `500` - Internal Server Error

## Request/Response Headers

### Standard Headers
```http
Content-Type: application/json
```

### CORS Handling
APIs handle same-origin requests only. For cross-origin requests, appropriate CORS headers would need to be added.

## Rate Limiting

Currently, no rate limiting is implemented. For production use, consider implementing:
- Request throttling per IP/user
- API key authentication for external integrations
- Monitoring and logging of API usage

## Security Considerations

1. **Input Validation**: All input parameters are sanitized using `esc()` function
2. **SQL Injection Prevention**: Parameterized queries used throughout
3. **Authentication Required**: Most admin APIs require valid session
4. **File Upload Security**: Images validated for type and processed safely
5. **XSS Prevention**: Output encoding applied to all user data

## JavaScript Integration

### Frontend Usage Examples

```javascript
// Search products
$.getJSON(rootUrl + 'api/shop/search-products.php?search=' + searchTerm, 
    function(response) {
        console.log(response.data);
    });

// Update cart
$.post(rootUrl + 'api/shop/cart.php', {
    action: 'incrementOrDecrement',
    cart_id: cartId,
    product_id: productId,
    plus: "QUALCOSA"
}, function(data) {
    updateCartDisplay(data);
});

// Get subcategories
$.getJSON(rootUrl + 'api/admin/categories.php?action=getSubcategories&parentId=' + parentId,
    function(response) {
        populateSubcategories(response.data);
    });
```

### Error Handling
```javascript
$.post(apiUrl, data)
    .done(function(response) {
        // Handle success
    })
    .fail(function(xhr) {
        var error = JSON.parse(xhr.responseText);
        alert('Error: ' + error.message);
    });
```