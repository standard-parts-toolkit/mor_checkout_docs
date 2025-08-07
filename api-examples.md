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
    "successReturnUrl": "https://example-partner.com/success",
    "failureReturnUrl": "https://example-partner.com/failure",
    "externalOrderId": "ORD-2024-123456"
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
    "successReturnUrl": "https://example-partner.com/success",
    "failureReturnUrl": "https://example-partner.com/failure",
    "allowUserDiscountCodes": true,
    "externalOrderId": "ORD-2024-123456"
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
- **Success**: User is redirected to the `successReturnUrl` with query parameters:
  - `mor_order_id`: The unique order identifier from the MOR system
  - `external_order_id`: The external order identifier provided in the request
  - `timestamp`: The UTC timestamp when the redirect was generated (ISO 8601 format)
  - `nonce`: A security signature (HMAC-SHA256 hash of external_order_id + timestamp)
  - Example: `https://example-partner.com/success?mor_order_id=MOR-123456&external_order_id=ORD-2024-123456&timestamp=2025-06-17T17:22:00Z&nonce=a3f2b8c9d1e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8`
- **Failure**: User is redirected to the `failureReturnUrl` with the same query parameters
  - Example: `https://example-partner.com/failure?mor_order_id=MOR-123456&external_order_id=ORD-2024-123456&timestamp=2025-06-17T17:22:00Z&nonce=a3f2b8c9d1e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8`

## Checkout Status

The checkout status endpoint allows you to retrieve transaction details using the MOR order ID received in the redirect URLs.

### Example Request

```bash
# For checkout-status requests, signature is calculated with external_order_id
EXTERNAL_ORDER_ID="ORD-2024-123456"  # The external order ID from the original checkout request
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Create signature with external_order_id concatenated with timestamp
SIGNATURE=$(echo -n "${EXTERNAL_ORDER_ID}${TIMESTAMP}" | openssl dgst -sha256 -hmac "YOUR_SIGNING_KEY" -binary | base64)

curl -X GET "http://localhost:8000/api/v1/checkout-status?external_order_id=$EXTERNAL_ORDER_ID" \
  -H "X-SPT-MOR-Signature: $SIGNATURE" \
  -H "X-SPT-MOR-Domain: your-domain.com" \
  -H "X-SPT-MOR-Timestamp: $TIMESTAMP" \
  -H "Content-Type: application/json"
```

### Example Response (Success)

```json
{
  "status": {
    "code": "PAYMENT_SUCCEEDED",
    "message": "Payment was processed successfully"
  },
  "merchantOfRecord": {
    "customerId": "MOR-10042857",
    "transactionId": "TXN-98765432",
    "orderId": "ORD-2023-03-17-001"
  },
  "financials": {
    "totalAmount": 228.73,
    "totalDiscount": 10.00,
    "totalTax": 8.75
  }
}
```

### Example Response (Payment Incomplete)

When the order exists but payment was not completed:

```json
{
  "error": {
    "code": "CHECKOUT_ABANDONED",
    "message": "The checkout process was abandoned by the user"
  }
}
```

### Example Response (Order Not Found)

When the specified mor_order_id does not exist, the API returns a `404 Not Found` HTTP status code.

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

function generateSignature(data, signingKey, timestamp) {
  const stringData = typeof data === 'string' ? data : JSON.stringify(data);
  const dataToSign = stringData + timestamp;
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

// Example: Handling the redirect from checkout with nonce and timestamp validation
function handleCheckoutReturn(signingKey) {
  const urlParams = new URLSearchParams(window.location.search);
  const morOrderId = urlParams.get('mor_order_id');
  const externalOrderId = urlParams.get('external_order_id');
  const timestamp = urlParams.get('timestamp');
  const nonce = urlParams.get('nonce');
  
  if (morOrderId && externalOrderId && timestamp && nonce) {
    // First, verify the timestamp is within 5 minutes
    const redirectTime = new Date(timestamp);
    const now = new Date();
    const fiveMinutes = 5 * 60 * 1000; // 5 minutes in milliseconds
    
    if (now - redirectTime > fiveMinutes) {
      console.error('Timestamp expired - possible replay attack');
      return;
    }
    
    // Verify the nonce by recreating it with external_order_id + timestamp
    const crypto = require('crypto');
    const dataToSign = externalOrderId + timestamp;
    const expectedNonce = crypto.createHmac('sha256', signingKey)
      .update(dataToSign)
      .digest('hex');
    
    if (nonce === expectedNonce) {
      // Nonce and timestamp are valid, proceed to get the checkout status
      getCheckoutStatus(externalOrderId);
    } else {
      console.error('Invalid nonce - possible security issue');
    }
  }
}

// Example: Checking order status
async function getCheckoutStatus(externalOrderId) {
  const timestamp = new Date().toISOString().replace(/\.\d{3}/, '');
  // For checkout-status requests, sign external_order_id + timestamp
  const signature = generateSignature(externalOrderId, 'your-signing-key', timestamp);
  
  const response = await fetch(`https://api.example.com/api/v1/checkout-status?external_order_id=${externalOrderId}`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'X-SPT-MOR-Signature': signature,
      'X-SPT-MOR-Domain': 'your-domain.com',
      'X-SPT-MOR-Timestamp': timestamp
    }
  });
  
  if (response.ok) {
    const data = await response.json();
    console.log('Order status:', data);
    return data;
  } else if (response.status === 404) {
    console.error('Order not found');
  } else {
    const error = await response.json();
    console.error('Status check failed:', error);
  }
}
```

### PHP
```php
function generateSignature($data, $signingKey, $timestamp) {
    $stringData = is_string($data) ? $data : json_encode($data);
    $dataToSign = $stringData . $timestamp;
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

// Example: Checking order status
function getCheckoutStatus($externalOrderId, $signingKey) {
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    // For checkout-status requests, sign external_order_id + timestamp
    $signature = generateSignature($externalOrderId, $signingKey, $timestamp);
    
    $ch = curl_init("https://api.example.com/api/v1/checkout-status?external_order_id=$externalOrderId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-SPT-MOR-Signature: ' . $signature,
        'X-SPT-MOR-Domain: your-domain.com',
        'X-SPT-MOR-Timestamp: ' . $timestamp
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return json_decode($response, true);
    } elseif ($httpCode == 404) {
        throw new Exception('Order not found');
    } else {
        throw new Exception('Status check failed: ' . $response);
    }
}
```

### Python
```python
import hmac
import hashlib
import json
from datetime import datetime, timezone

def generate_signature(data, signing_key, timestamp):
    string_data = data if isinstance(data, str) else json.dumps(data, separators=(',', ':'))
    data_to_sign = string_data + timestamp
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

# Example: Checking order status
import requests

def get_checkout_status(external_order_id, signing_key):
    timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    # For checkout-status requests, sign external_order_id + timestamp
    signature = generate_signature(external_order_id, signing_key, timestamp)
    
    headers = {
        'Content-Type': 'application/json',
        'X-SPT-MOR-Signature': signature,
        'X-SPT-MOR-Domain': 'your-domain.com',
        'X-SPT-MOR-Timestamp': timestamp
    }
    
    response = requests.get(
        f'https://api.example.com/api/v1/checkout-status?external_order_id={external_order_id}',
        headers=headers
    )
    
    if response.status_code == 200:
        return response.json()
    elif response.status_code == 404:
        raise Exception('Order not found')
    else:
        raise Exception(f'Status check failed: {response.text}')
```

## Swagger Documentation

Interactive API documentation is available at: `https://staging-morcheckout.standardpartstoolkit.com/api/documentation`
