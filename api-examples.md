# Tax & Checkout API Examples

## Authentication

All API endpoints require HMAC-SHA256 signature authentication and additional security headers:

### Required Headers

1. `X-SPT-MOR-Signature`: HMAC-SHA256 signature of (requestBody + timestamp)
2. `X-SPT-MOR-Domain`: Your registered partner domain
3. `X-SPT-MOR-Timestamp`: Current UTC timestamp in ISO 8601 format

### Signature Generation Process

1. Take the entire request body as a JSON string
2. Get current UTC timestamp in ISO 8601 format (e.g., "2025-06-30T15:30:00Z")
3. Concatenate request body with timestamp (no separator)
4. Create an HMAC-SHA256 hash of this concatenated string using your provided signing key
5. Include all required headers in your request

```bash
X-SPT-MOR-Signature: HMAC_SHA256_SIGNATURE_HERE
X-SPT-MOR-Domain: your-domain.com
X-SPT-MOR-Timestamp: 2025-06-30T15:30:00Z
```

**Note**: Timestamps must be within 5 minutes of the current time to prevent replay attacks.

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

# Generate timestamp
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Create signature with concatenated request body and timestamp
SIGNATURE=$(echo -n "${REQUEST_BODY}${TIMESTAMP}" | openssl dgst -sha256 -hmac "YOUR_SIGNING_KEY" -binary | base64)

curl -X POST "http://localhost:8000/api/v1/calculate-tax-estimate" \
  -H "X-SPT-MOR-Signature: $SIGNATURE" \
  -H "X-SPT-MOR-Domain: your-domain.com" \
  -H "X-SPT-MOR-Timestamp: $TIMESTAMP" \
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

# Generate timestamp
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Create signature with concatenated request body and timestamp
SIGNATURE=$(echo -n "${REQUEST_BODY}${TIMESTAMP}" | openssl dgst -sha256 -hmac "YOUR_SIGNING_KEY" -binary | base64)

# Note: The checkout endpoint returns a 302 redirect to the checkout page
# Use -L flag to follow redirects or -i to see the redirect response
curl -i -X POST "http://localhost:8000/api/v1/checkout" \
  -H "X-SPT-MOR-Signature: $SIGNATURE" \
  -H "X-SPT-MOR-Domain: your-domain.com" \
  -H "X-SPT-MOR-Timestamp: $TIMESTAMP" \
  -H "Content-Type: application/json" \
  -d "$REQUEST_BODY"
```

### Example Response

The checkout endpoint returns a 302 redirect response:

```http
HTTP/1.1 302 Found
Location: https://checkout.example.com/session/abc123xyz
Content-Type: text/html; charset=utf-8
Content-Length: 0
```

The user's browser will automatically follow this redirect to the checkout page where they can complete their payment.

After the checkout process:
- **Success**: User is redirected to the `successReturnUrl` specified in the request
- **Failure**: User is redirected to the `failureReturnUrl` specified in the request

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

function generateSignature(requestBody, signingKey, timestamp) {
  const jsonString = JSON.stringify(requestBody);
  const dataToSign = jsonString + timestamp;
  return crypto.createHmac('sha256', signingKey).update(dataToSign).digest('hex');
}

// Usage
const requestData = { /* your request data */ };
const timestamp = new Date().toISOString().replace(/\.\d{3}/, ''); // Format: 2025-06-30T15:30:00Z
const signature = generateSignature(requestData, 'your-signing-key', timestamp);

// Example: Making a checkout request that returns a 302 redirect
async function initiateCheckout(requestData) {
  const timestamp = new Date().toISOString().replace(/\.\d{3}/, '');
  const signature = generateSignature(requestData, 'your-signing-key', timestamp);
  
  const response = await fetch('https://api.example.com/api/v1/checkout', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-SPT-MOR-Signature': signature,
      'X-SPT-MOR-Domain': 'your-domain.com',
      'X-SPT-MOR-Timestamp': timestamp
    },
    body: JSON.stringify(requestData),
    redirect: 'manual' // Prevent automatic redirect
  });
  
  if (response.status === 302) {
    const checkoutUrl = response.headers.get('Location');
    // Redirect user to checkout page
    window.location.href = checkoutUrl;
  } else {
    // Handle error
    const error = await response.json();
    console.error('Checkout failed:', error);
  }
}
```

### PHP
```php
function generateSignature($requestBody, $signingKey, $timestamp) {
    $jsonString = json_encode($requestBody);
    $dataToSign = $jsonString . $timestamp;
    return hash_hmac('sha256', $dataToSign, $signingKey);
}

// Usage
$requestData = [/* your request data */];
$timestamp = gmdate('Y-m-d\TH:i:s\Z'); // Format: 2025-06-30T15:30:00Z
$signature = generateSignature($requestData, 'your-signing-key', $timestamp);

// Include in headers:
// X-SPT-MOR-Signature: $signature
// X-SPT-MOR-Domain: your-domain.com
// X-SPT-MOR-Timestamp: $timestamp
```

### Python
```python
import hmac
import hashlib
import json
from datetime import datetime, timezone

def generate_signature(request_body, signing_key, timestamp):
    json_string = json.dumps(request_body, separators=(',', ':'))
    data_to_sign = json_string + timestamp
    signature = hmac.new(
        signing_key.encode('utf-8'),
        data_to_sign.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    return signature

# Usage
request_data = {}  # your request data
timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')  # Format: 2025-06-30T15:30:00Z
signature = generate_signature(request_data, 'your-signing-key', timestamp)

# Include in headers:
# X-SPT-MOR-Signature: signature
# X-SPT-MOR-Domain: your-domain.com
# X-SPT-MOR-Timestamp: timestamp
```

## Swagger Documentation

Interactive API documentation is available at: `http://localhost:8000/api/documentation`