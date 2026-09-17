<?php

/**
 * Simple MOR Payment Processing API PHP Script
 * Based on Standard Parts Toolkit MOR Payment API
 *
 * This script demonstrates:
 * 1. Calculating tax estimates before checkout
 * 2. Requesting tax estimates in each supported currency (USD / CAD / MXN),
 *    including the not-enabled (422) and legitimate $0-tax cases
 * 3. Making a checkout request (returns 302 redirect to payment page)
 * 4. Checking order status using the checkout-status endpoint
 * 5. Formatting every amount with the currency the API returns
 *    (financials.currency), never a hardcoded "$"
 *
 * Updated for API v1.4.0: multi-currency support (USD / CAD / MXN)
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
 * Currencies supported by the API. Each must also be enabled for your partner
 * account before you can transact in it; an unenabled currency returns HTTP 422.
 */
function supportedCurrencies()
{
    return ['USD', 'CAD', 'MXN'];
}

/**
 * Format a monetary amount using the currency the API returned.
 *
 * Mirrors the server's display: USD -> "$", CAD -> "CA$", MXN -> "MX$". Once you
 * send non-USD currencies you must never assume a bare "$" -- always format with
 * the currency from the response (financials.currency), not the one you sent.
 */
function formatMoney($amount, $currency)
{
    $symbols = ['USD' => '$', 'CAD' => 'CA$', 'MXN' => 'MX$'];
    $code = strtoupper((string) $currency);
    $symbol = isset($symbols[$code]) ? $symbols[$code] : '';
    return $symbol . number_format((float) $amount, 2) . ' ' . $code;
}

/**
 * Clone the sample cart with a specific currency and a unique external order ID.
 *
 * Prices are NOT converted -- in a real integration you would supply prices
 * already denominated in the target currency. Currency is independent of the
 * shipping/billing country, so a US address paying in CAD is valid.
 */
function withCurrency(array $checkoutData, $currency, $externalOrderId)
{
    $checkoutData['cartInformation']['currency'] = $currency;
    $checkoutData['configuration']['externalOrderId'] = $externalOrderId;
    return $checkoutData;
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
        // ISO 4217 currency for every price in this cart. Optional; defaults to 'USD'.
        // Supported: 'USD', 'CAD', 'MXN' -- and the currency must be enabled for your
        // partner account or the request is rejected with a 422.
        //
        // No conversion is performed: the prices below are charged as-is in this currency.
        // Currency is independent of the shipping/billing country, so a Canadian address
        // paying in USD is valid. For a CAD order, use:
        //
        //     'currency' => 'CAD',
        //
        // and supply CAD prices. Note that ACH bank debit (us_bank_account) is offered for
        // USD only; CAD and MXN present card payments only.
        //
        // Omitted here, so this cart defaults to USD.
        'lineItems' => [
            [
                'sku' => 'PROD-001',
                'price' => 5000.00,
                'quantity' => 1,
                'description' => 'Premium Widget',
                'discounts' => [
                    [
                        'discountId' => 'ITEM-20OFF',
                        'description' => '20% off',
                        'type' => 'percentage',
                        'value' => 20.0
                    ]
                ]
            ],
            [
                'sku' => 'PROD-002',
                'price' => 0.00,
                'quantity' => 1,
                'description' => 'Complimentary Setup'
            ]
        ]
    ],
    'orderDiscounts' => [
        [
            'discountId' => 'ORDER-40OFF',
            'description' => '$40 off order',
            'type' => 'fixed',
            'value' => 40.0
        ]
    ],
    'shippingAddress' => [
        'firstName' => '-',
        'lastName' => '-',
        'addressLine1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postalCode' => '10001',
        'country' => 'US',
        'phone' => '-'
    ],
    // Billing identical to shipping. The 20% item discount brings the $5,000 item down
    // to $4,000 and the $40 order discount to $3,960, so this should return $351.45 tax
    // (8.875% NYC combined rate) and a $4,311.45 total, with $1,040.00 in
    // financials.totalDiscount.
    'billingAddress' => [
        'sameAsShipping' => true,
        'firstName' => '-',
        'lastName' => '-',
        'addressLine1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postalCode' => '10001',
        'country' => 'US',
        'phone' => '-'
    ],
    'email' => 'customer@example.com',
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
            // Trust the currency the API echoes back, not the one you sent.
            $currency = isset($financials['currency'])
                ? $financials['currency']
                : ($checkout_data['cartInformation']['currency'] ?? 'USD');

            echo "Currency: " . $currency . "\n";
            echo "Total Tax Estimated: " . formatMoney($financials['totalTaxCharged'], $currency) . "\n";

            if (isset($financials['lineItemTotals'])) {
                echo "Line Item Tax Breakdown:\n";
                foreach ($financials['lineItemTotals'] as $item) {
                    echo "  SKU: " . $item['sku']
                        . " - Tax: " . formatMoney($item['tax'], $currency)
                        . " - Total: " . formatMoney($item['total'], $currency) . "\n";
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

    echo "--- Example: Multi-currency tax estimates (USD / CAD / MXN) ---\n";
    echo "Each currency must be enabled for your partner account; an unenabled\n";
    echo "currency returns HTTP 422. A \$0 tax result can be legitimate where the\n";
    echo "merchant of record has no tax obligation for the destination.\n\n";

    foreach (supportedCurrencies() as $currency) {
        $currency_cart = withCurrency(
            $checkout_data,
            $currency,
            'ORD-' . $currency . '-' . time()
        );

        echo "» Requesting a tax estimate in $currency...\n";
        $currency_response = calculateTaxEstimate($currency_cart, $signing_key, $partner_domain, $api_base_url);

        if ($currency_response['status_code'] == 200 && isset($currency_response['data']['financials'])) {
            $financials = $currency_response['data']['financials'];
            // Always report the currency the API echoed back, not the one we sent.
            $response_currency = isset($financials['currency']) ? $financials['currency'] : $currency;
            $total_tax = isset($financials['totalTaxCharged']) ? $financials['totalTaxCharged'] : 0;

            echo "  Currency (from response): $response_currency\n";
            echo "  Total tax: " . formatMoney($total_tax, $response_currency) . "\n";

            if ((float) $total_tax === 0.0) {
                echo "  Note: \$0 tax -- expected where there is no tax obligation for this destination.\n";
            }
        } elseif ($currency_response['status_code'] == 422) {
            echo "  Rejected (HTTP 422) -- $currency is most likely not enabled for this partner account.\n";
            // The spec error shape is a list of { field, code, message }.
            if (isset($currency_response['data']['errors']) && is_array($currency_response['data']['errors'])) {
                foreach ($currency_response['data']['errors'] as $fieldError) {
                    if (is_array($fieldError) && isset($fieldError['message'])) {
                        echo "    - " . $fieldError['message'] . "\n";
                    }
                }
            }
            echo "    Contact support to have $currency enabled for your account.\n";
        } else {
            echo "  Unexpected status: " . $currency_response['status_code'] . "\n";
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
                    $currency = isset($financials['currency']) ? $financials['currency'] : 'USD';
                    echo "Currency: " . $currency . "\n";
                    echo "Total Amount: " . formatMoney($financials['totalAmount'], $currency) . "\n";
                    echo "Total Discount: " . formatMoney($financials['totalDiscount'], $currency) . "\n";
                    echo "Total Tax: " . formatMoney($financials['totalTax'], $currency) . "\n";
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
