<?php

/**
 * Simple MOR Payment Processing API PHP Script
 * Based on Standard Parts Toolkit MOR Payment API
 *
 * This script demonstrates:
 * 1. Calculating tax estimates before checkout
 * 2. Making a checkout request (returns 302 redirect to payment page)
 * 3. Checking order status using the checkout-status endpoint
 *
 * Updated for API v1.3.1 with enhanced security for payment flow redirects
 */

// Configuration
$api_base_url = 'https://staging-morcheckout.standardpartstoolkit.com/api/v1';
$signing_key = ''; // Replace with your actual signing key
$partner_domain = ''; // Replace with your registered partner domain

/**
 * Generate HMAC-SHA256 signature for authentication
 */
function generateSignature($data, $timestamp, $signingKey)
{
    $stringData = is_string($data) ? $data : json_encode($data);
    $dataToSign = $stringData . $timestamp;
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
 * Get checkout status using external order ID
 */
function getCheckoutStatus($externalOrderId, $signingKey, $domain, $apiBaseUrl)
{
    $url = $apiBaseUrl . '/checkout-status?external_order_id=' . urlencode($externalOrderId);
    // For checkout-status requests, use external_order_id as data
    return makeApiRequest($url, $externalOrderId, $signingKey, $domain, 'GET');
}

/**
 * Calculate tax estimate for a given set of items and location
 */
function calculateTaxEstimate($cartData, $signingKey, $domain, $apiBaseUrl)
{
    $url = $apiBaseUrl . '/calculate-tax-estimate';
    return makeApiRequest($url, $cartData, $signingKey, $domain, 'POST');
}

/**
 * Validate nonce and timestamp from redirect URL
 * The nonce is an HMAC-SHA256 hash of external_order_id + timestamp
 */
function validateNonceAndTimestamp($externalOrderId, $timestamp, $nonce, $signingKey)
{
    // First, verify the timestamp is within 5 minutes
    $redirectTime = strtotime($timestamp);
    $now = time();
    $fiveMinutes = 5 * 60; // 5 minutes in seconds

    if (($now - $redirectTime) > $fiveMinutes) {
        throw new Exception('Timestamp expired - possible replay attack');
    }

    // Recreate the nonce by hashing external_order_id + timestamp
    $dataToSign = $externalOrderId . $timestamp;
    $expectedNonce = hash_hmac('sha256', $dataToSign, $signingKey);

    // Compare the received nonce with the expected one
    return hash_equals($expectedNonce, $nonce);
}

/**
 * Handle redirect from payment process (success or failure page)
 * This function demonstrates how to process the return from the payment flow
 */
function handleCheckoutReturn($signingKey, $domain, $apiBaseUrl)
{
    // Get parameters from query string
    $morOrderId = isset($_GET['mor_order_id']) ? $_GET['mor_order_id'] : null;
    $externalOrderId = isset($_GET['external_order_id']) ? $_GET['external_order_id'] : null;
    $timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : null;
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : null;

    if (!$morOrderId || !$externalOrderId || !$timestamp || !$nonce) {
        throw new Exception('Missing required parameters from payment flow redirect');
    }

    echo "Received redirect from payment process:\n";
    echo "MOR Order ID: $morOrderId\n";
    echo "External Order ID: $externalOrderId\n";
    echo "Timestamp: $timestamp\n";
    echo "Nonce: $nonce\n\n";

    // Validate the nonce and timestamp
    if (!validateNonceAndTimestamp($externalOrderId, $timestamp, $nonce, $signingKey)) {
        throw new Exception('Invalid nonce or expired timestamp - possible security issue');
    }

    echo "Nonce and timestamp validated successfully\n\n";

    // Get the full order status
    $statusResponse = getCheckoutStatus($externalOrderId, $signingKey, $domain, $apiBaseUrl);

    return $statusResponse;
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
    'configuration' => [
        'successReturnUrl' => 'https://example-partner.com/success',
        'failureReturnUrl' => 'https://example-partner.com/failure',
        'allowUserDiscountCodes' => true,
        'externalOrderId' => 'ORD-2024-123456'
    ]
];

try {
    echo "--- Example: Calculating Tax Estimate ---\n";

    // Calculate tax estimate first
    $tax_estimate_response = calculateTaxEstimate($checkout_data, $signing_key, $partner_domain, $api_base_url);

    if ($tax_estimate_response['status_code'] == 200) {
        echo "Tax Estimate Retrieved Successfully!\n";
        echo "Tax Estimate Response:\n";
        echo json_encode($tax_estimate_response['data'], JSON_PRETTY_PRINT) . "\n";

        if (isset($tax_estimate_response['data']['financials'])) {
            $financials = $tax_estimate_response['data']['financials'];
            echo "Total Tax Estimated: $" . $financials['totalTaxCharged'] . "\n";

            if (isset($financials['lineItemTotals'])) {
                echo "Line Item Tax Breakdown:\n";
                foreach ($financials['lineItemTotals'] as $item) {
                    echo "  SKU: " . $item['sku'] . " - Tax: $" . $item['tax'] . " - Total: $" . $item['total'] . "\n";
                }
            }
        }
        echo "\n";
    } else {
        echo "Tax estimate failed with status: " . $tax_estimate_response['status_code'] . "\n";
        if (isset($tax_estimate_response['data'])) {
            echo "Response: " . json_encode($tax_estimate_response['data'], JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }

    echo "--- Example: Initiating Payment Flow ---\n";

    // Initiate the payment flow
    $checkout_url = $api_base_url . '/checkout';
    $checkout_response = makeApiRequest($checkout_url, $checkout_data, $signing_key, $partner_domain);

    // Echo the full API response
    echo "API Response (Status: " . $checkout_response['status_code'] . "):\n";
    echo json_encode($checkout_response['data'], JSON_PRETTY_PRINT) . "\n\n";

    if ($checkout_response['status_code'] >= 300 && $checkout_response['status_code'] < 400) {
        echo "Payment flow initiated with redirect!\n";
        echo "You should redirect the user to: " . $checkout_response['redirect_url'] . "\n";
        echo "This is the payment page (/pay/{order_id}) where the customer will complete payment using Stripe's Payment Element.\n";
        echo "After payment, the user will be redirected through intermediate success/cancel pages, then to your success/failure URLs with mor_order_id, external_order_id, timestamp, and nonce parameters.\n";

        // Example of how to check order status later using external order ID
        echo "\n--- Example: Checking Order Status ---\n";
        try {
            $sample_external_order_id = $checkout_data['configuration']['externalOrderId'];
            echo "Using external order ID: $sample_external_order_id\n";

            $status_response = getCheckoutStatus($sample_external_order_id, $signing_key, $partner_domain, $api_base_url);

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
                echo "Order not found (this is expected for the sample external order ID)\n";
            } else {
                echo "Status check failed with code: " . $status_response['status_code'] . "\n";
            }
        } catch (Exception $e) {
            echo "Status check example failed: " . $e->getMessage() . "\n";
        }

    } else {
        echo "Payment flow initiation failed with status: " . $checkout_response['status_code'] . "\n";
        if (isset($checkout_response['data'])) {
            echo "Response: " . json_encode($checkout_response['data'], JSON_PRETTY_PRINT) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
