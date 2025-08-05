<?php

/**
 * Simple MOR Checkout API PHP Script
 * Based on Standard Parts Toolkit MOR Checkout API
 * 
 * This script demonstrates:
 * 1. Making a checkout request (returns 302 redirect)
 * 2. Checking order status using the checkout-status endpoint
 * 
 * Updated for API v1.3.0 with externalOrderId support
 */

// Configuration
$api_base_url = 'https://staging-morcheckout.standardpartstoolkit.com/api/v1';
$signing_key = ''; // Replace with your actual signing key
$partner_domain = ''; // Replace with your registered partner domain

/**
 * Generate HMAC-SHA256 signature for authentication
 */
function generateSignature($requestBody, $timestamp, $signingKey)
{
    $jsonString = json_encode($requestBody);
    $dataToSign = $jsonString . $timestamp;
    return hash_hmac('sha256', $dataToSign, $signingKey);
}

/**
 * Make API request with proper authentication
 */
function makeApiRequest($url, $data, $signingKey, $domain, $method = 'POST')
{
    // Generate timestamp in ISO 8601 format
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    // Generate signature
    $signature = generateSignature($data, $timestamp, $signingKey);

    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'X-SPT-MOR-Signature: ' . $signature,
        'X-SPT-MOR-Domain: ' . $domain,
        'X-SPT-MOR-Timestamp: ' . $timestamp
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects automatically

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }

    // Handle redirect responses (302, 301, etc.)
    if ($httpCode >= 300 && $httpCode < 400) {
        echo "Received redirect (HTTP $httpCode)\n";
        if ($redirectUrl) {
            echo "Redirect URL: $redirectUrl\n";
        }
        return [
            'status_code' => $httpCode,
            'redirect_url' => $redirectUrl,
            'data' => ['redirect' => true, 'url' => $redirectUrl]
        ];
    }

    // Debug: Show raw response for non-redirect responses
    echo "Raw Response (HTTP $httpCode):\n";
    echo "Response Length: " . strlen($response) . " characters\n";
    echo "Raw Content:\n" . $response . "\n";
    echo "---End of Raw Response---\n\n";

    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg() . "\nRaw response was: " . substr($response, 0, 500));
    }

    return [
        'status_code' => $httpCode,
        'data' => $decodedResponse
    ];
}

/**
 * Get checkout status using MOR order ID
 */
function getCheckoutStatus($morOrderId, $signingKey, $domain, $apiBaseUrl)
{
    $url = $apiBaseUrl . '/checkout-status/' . $morOrderId;
    // For GET requests, use empty array as data
    return makeApiRequest($url, [], $signingKey, $domain, 'GET');
}

// Sample checkout data based on API examples
$checkout_data = [
    'cartInformation' => [
        'lineItems' => [
            [
                'sku' => 'PROD-001',
                'price' => 29.99,
                'quantity' => 2,
                'description' => 'Premium Widget',
                'discounts' => [
                    [
                        'discountId' => 'DISC-001',
                        'description' => '10% off',
                        'type' => 'percentage',
                        'value' => 10.0
                    ]
                ]
            ]
        ]
    ],
    'orderDiscounts' => [
        [
            'discountId' => 'ORDER-DISC-001',
            'description' => 'Order discount',
            'type' => 'fixed',
            'value' => 5.0
        ]
    ],
    'shippingAddress' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'addressLine1' => '123 Main St',
        'addressLine2' => 'Apt 4B',
        'city' => 'New York',
        'state' => 'NY',
        'postalCode' => '10001',
        'country' => 'US',
        'phone' => '+1-555-123-4567'
    ],
    'billingAddress' => [
        'sameAsShipping' => false,
        'firstName' => 'John',
        'lastName' => 'Doe',
        'addressLine1' => '456 Oak Ave',
        'addressLine2' => 'Suite 100',
        'city' => 'New York',
        'state' => 'NY',
        'postalCode' => '10002',
        'country' => 'US',
        'phone' => '+1-555-987-6543'
    ],
    'email' => 'john.doe@example.com',
    'renewal' => [
        'originalPurchaseDate' => '2023-01-15',
        'originalTransactionId' => 'TXN-12345'
    ],
    'existingClientId' => 'CLIENT-789',
    'configuration' => [
        'successReturnUrl' => 'https://example-partner.com/success',
        'failureReturnUrl' => 'https://example-partner.com/failure',
        'allowUserDiscountCodes' => true,
        'externalOrderId' => 'ORD-2024-123456'
    ]
];

try {
    echo "Processing checkout...\n";

    // Process the checkout
    $checkout_url = $api_base_url . '/checkout';
    $checkout_response = makeApiRequest($checkout_url, $checkout_data, $signing_key, $partner_domain);

    // Echo the full API response
    echo "API Response (Status: " . $checkout_response['status_code'] . "):\n";
    echo json_encode($checkout_response['data'], JSON_PRETTY_PRINT) . "\n\n";

    if ($checkout_response['status_code'] >= 300 && $checkout_response['status_code'] < 400) {
        echo "Checkout initiated with redirect!\n";
        echo "You should redirect the user to: " . $checkout_response['redirect_url'] . "\n";
        echo "This is the checkout page where the customer will complete payment.\n";
        echo "After payment, the user will be redirected back to your success/failure URLs with mor_order_id and external_order_id parameters.\n";
        
        // Example of how to check order status later using a sample MOR order ID
        echo "\n--- Example: Checking Order Status ---\n";
        try {
            // Replace 'MOR-123456' with an actual MOR order ID you receive
            $sample_mor_order_id = 'MOR-123456';
            echo "Attempting to check status for order: $sample_mor_order_id\n";
            
            $status_response = getCheckoutStatus($sample_mor_order_id, $signing_key, $partner_domain, $api_base_url);
            
            if ($status_response['status_code'] == 200) {
                echo "Order Status Retrieved Successfully!\n";
                if (isset($status_response['data']['status'])) {
                    echo "Status: " . $status_response['data']['status']['message'] . "\n";
                }
                
                if (isset($status_response['data']['merchantOfRecord'])) {
                    $mor = $status_response['data']['merchantOfRecord'];
                    echo "Customer ID: " . $mor['customerId'] . "\n";
                    echo "Transaction ID: " . $mor['transactionId'] . "\n";
                    echo "Order ID: " . $mor['orderId'] . "\n";
                }
                
                if (isset($status_response['data']['financials'])) {
                    $financials = $status_response['data']['financials'];
                    echo "Total Amount: $" . $financials['totalAmount'] . "\n";
                    echo "Total Discount: $" . $financials['totalDiscount'] . "\n";
                    echo "Total Tax: $" . $financials['totalTax'] . "\n";
                }
            } elseif ($status_response['status_code'] == 404) {
                echo "Order not found (this is expected for the sample order ID)\n";
            } else {
                echo "Status check failed with code: " . $status_response['status_code'] . "\n";
            }
        } catch (Exception $e) {
            echo "Status check example failed: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "Checkout failed with status: " . $checkout_response['status_code'] . "\n";
        if (isset($checkout_response['data'])) {
            echo "Response: " . json_encode($checkout_response['data'], JSON_PRETTY_PRINT) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
