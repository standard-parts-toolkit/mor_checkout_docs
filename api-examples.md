# Tax & Checkout API Examples

## Authentication

All API endpoints require HMAC-SHA256 signature authentication using the `X-SPT-MOR-Signature` header:

1. Take the entire request body as a JSON string
2. Create an HMAC-SHA256 hash of this string using your provided signing key
3. Include this hash in the `X-SPT-MOR-Signature` header

```bash
X-SPT-MOR-Signature: HMAC_SHA256_SIGNATURE_HERE
```

## Calculate Tax Estimate

### Example Request

```bash
# First, generate the signature (example using openssl)
REQUEST_BODY='{
  "cartInformation": {
    "lineItems": [
      {
        "sku": "PROD-001",
        "price": 29.99,
        "quantity": 2,
        "description": "Premium Widget",
        "discounts": [
          {
            "discountId": "DISC-001",
            "description": "10% off",
            "type": "percentage",
            "value": 10.0
          }
        ]
      }
    ]
  },
  "orderDiscounts": [
    {
      "discountId": "ORDER-DISC-001",
      "description": "Order discount",
      "type": "fixed",
      "value": 5.0
    }
  ],
  "shippingAddress": {
    "firstName": "John",
    "lastName": "Doe",
    "addressLine1": "123 Main St",
    "addressLine2": "Apt 4B",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US",
    "phone": "+1-555-123-4567"
  },
  "billingAddress": {
    "sameAsShipping": false,
    "firstName": "John",
    "lastName": "Doe",
    "addressLine1": "456 Oak Ave",
    "addressLine2": "Suite 100",
    "city": "New York",
    "state": "NY",
    "postalCode": "10002",
    "country": "US",
    "phone": "+1-555-987-6543"
  },
  "email": "john.doe@example.com",
  "renewal": {
    "originalPurchaseDate": "2023-01-15",
    "originalTransactionId": "TXN-12345"
  },
  "existingClientId": "CLIENT-789",
  "configuration": {
    "successReturnUrl": "https://example.com/success",
    "failureReturnUrl": "https://example.com/failure"
  }
}'

SIGNATURE=$(echo -n "$REQUEST_BODY" | openssl dgst -sha256 -hmac "YOUR_SIGNING_KEY" -binary | base64)

curl -X POST "http://localhost:8000/api/v1/calculate-tax-estimate" \
  -H "X-SPT-MOR-Signature: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$REQUEST_BODY"
```

### Example Response

```json
{
  "financials": {
    "totalTaxCharged": 8.75,
    "lineItemTotals": [
      {
        "sku": "PROD-001",
        "subtotal": 59.98,
        "tax": 5.40,
        "discount": 6.00,
        "total": 59.38
      }
    ]
  }
}
```

## Checkout

### Example Request

```bash
# Generate signature for checkout request
REQUEST_BODY='{
  "cartInformation": {
    "lineItems": [
      {
        "sku": "PROD-001",
        "price": 29.99,
        "quantity": 2,
        "description": "Premium Widget",
        "discounts": [
          {
            "discountId": "DISC-001",
            "description": "10% off",
            "type": "percentage",
            "value": 10.0
          }
        ]
      }
    ]
  },
  "orderDiscounts": [
    {
      "discountId": "ORDER-DISC-001",
      "description": "Order discount",
      "type": "fixed",
      "value": 5.0
    }
  ],
  "shippingAddress": {
    "firstName": "John",
    "lastName": "Doe",
    "addressLine1": "123 Main St",
    "addressLine2": "Apt 4B",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US",
    "phone": "+1-555-123-4567"
  },
  "billingAddress": {
    "sameAsShipping": false,
    "firstName": "John",
    "lastName": "Doe",
    "addressLine1": "456 Oak Ave",
    "addressLine2": "Suite 100",
    "city": "New York",
    "state": "NY",
    "postalCode": "10002",
    "country": "US",
    "phone": "+1-555-987-6543"
  },
  "email": "john.doe@example.com",
  "renewal": {
    "originalPurchaseDate": "2023-01-15",
    "originalTransactionId": "TXN-12345"
  },
  "existingClientId": "CLIENT-789",
  "configuration": {
    "successReturnUrl": "https://example.com/success",
    "failureReturnUrl": "https://example.com/failure",
    "allowUserDiscountCodes": true
  }
}'

SIGNATURE=$(echo -n "$REQUEST_BODY" | openssl dgst -sha256 -hmac "YOUR_SIGNING_KEY" -binary | base64)

curl -X POST "http://localhost:8000/api/v1/checkout" \
  -H "X-SPT-MOR-Signature: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$REQUEST_BODY"
```

### Example Response

```json
{
  "status": {
    "code": "PAYMENT_SUCCEEDED",
    "message": "Payment was processed successfully"
  },
  "merchantOfRecord": {
    "customerId": "MOR-10042857",
    "transactionId": "TXN-98765432",
    "paymentId": "PAY-2023-03-17-001"
  },
  "financials": {
    "totalTaxCharged": 8.75,
    "lineItemTotals": [
      {
        "sku": "PROD-001",
        "subtotal": 59.98,
        "discount": 6.00,
        "total": 53.98
      }
    ]
  },
  "payment": {
    "method": "CREDIT_CARD",
    "cardType": "VISA",
    "lastFourDigits": "4242",
    "expiryDate": "05/26",
    "billingZip": "10002"
  }
}
```

## Error Responses

All error responses follow a standardized structure with status codes, descriptive messages, and unique request IDs for tracking.

### Authentication Errors

Missing signature:
```json
{
  "status": {
    "code": "UNAUTHORIZED",
    "message": "Missing X-SPT-MOR-Signature header"
  },
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

Invalid signature:
```json
{
  "status": {
    "code": "UNAUTHORIZED",
    "message": "Invalid signature"
  },
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

### Validation Errors

When request validation fails:
```json
{
  "status": {
    "code": "INVALID_REQUEST",
    "message": "The request contains validation errors."
  },
  "errors": [
    {
      "field": "email",
      "code": "REQUIRED_FIELD",
      "message": "The email field is required."
    },
    {
      "field": "cartInformation.lineItems.0.price",
      "code": "INVALID_VALUE",
      "message": "The cart information.line items.0.price field must be at least 0."
    }
  ],
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

### Server Errors

Internal server error:
```json
{
  "status": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred on the server"
  },
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

Not found error:
```json
{
  "status": {
    "code": "NOT_FOUND",
    "message": "The requested resource does not exist."
  },
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

### Rate Limiting Errors

When rate limits are exceeded:
```json
{
  "status": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Rate limit exceeded"
  },
  "requestId": "req-1234567-abcd-efgh-5678"
}
```

**Rate Limit Headers:**
All API responses include rate limiting headers:
- `X-RateLimit-Limit`: Total requests allowed per minute
- `X-RateLimit-Remaining`: Remaining requests in current window  
- `X-RateLimit-Reset`: Unix timestamp when rate limit resets
- `Retry-After`: Seconds until rate limit resets (429 responses only)

**Rate Limit Tiers:**
- **Standard**: 100 requests per minute
- **Premium**: 500 requests per minute

## Signature Generation Examples

### JavaScript (Node.js)
```javascript
const crypto = require('crypto');

function generateSignature(requestBody, signingKey) {
  const jsonString = JSON.stringify(requestBody);
  return crypto.createHmac('sha256', signingKey).update(jsonString).digest('hex');
}

// Usage
const requestData = { /* your request data */ };
const signature = generateSignature(requestData, 'your-signing-key');
```

### PHP
```php
function generateSignature($requestBody, $signingKey) {
    $jsonString = json_encode($requestBody);
    return hash_hmac('sha256', $jsonString, $signingKey);
}

// Usage
$requestData = [/* your request data */];
$signature = generateSignature($requestData, 'your-signing-key');
```

### Python
```python
import hmac
import hashlib
import json

def generate_signature(request_body, signing_key):
    json_string = json.dumps(request_body, separators=(',', ':'))
    signature = hmac.new(
        signing_key.encode('utf-8'),
        json_string.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    return signature

# Usage
request_data = {}  # your request data
signature = generate_signature(request_data, 'your-signing-key')
```

## Swagger Documentation

Interactive API documentation is available at: `http://localhost:8000/api/documentation`