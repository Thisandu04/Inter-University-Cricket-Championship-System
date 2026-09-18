<?php
/**
 * Minimal Stripe API wrapper using raw cURL — no Composer/SDK required.
 * Stripe's API accepts application/x-www-form-urlencoded POST bodies with
 * bracket notation for nested params (e.g. line_items[0][price_data][...]).
 */

function stripeRequest(string $method, string $endpoint, array $params = []): array {
    $ch = curl_init();
    $url = 'https://api.stripe.com/v1/' . $endpoint;

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // On some local Windows/WAMP setups, PHP's cURL can't find a valid CA
    // certificate bundle, causing "unable to get local issuer certificate."
    // If a bundle exists at this project-relative path, use it; otherwise,
    // fall back to whatever PHP is configured to use by default (works fine
    // on most Mac/Linux setups and correctly-configured servers).
    $caBundlePath = __DIR__ . '/../cacert.pem';
    if (file_exists($caBundlePath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundlePath);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($error) {
        return ['error' => ['message' => 'cURL error: ' . $error]];
    }

    $decoded = json_decode($response, true);
    return $decoded ?? ['error' => ['message' => 'Invalid response from Stripe']];
}

/**
 * Creates a Stripe Checkout Session for a team's registration fee.
 * Returns the full session array (includes 'url' to redirect the user to)
 * or an array with an 'error' key on failure.
 */
function createCheckoutSession(int $teamId, string $teamName, float $amountLKR): array {
    // Charge directly in LKR — no conversion needed, so the amount shown to
    // the payer matches exactly what Admin set in System Settings.
    $amountInCents = (int) round($amountLKR * 100);

    $params = [
        'mode' => 'payment',
        'payment_method_types' => ['card'],
        'success_url' => BASE_URL . '/manager/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => BASE_URL . '/manager/payment.php?cancelled=1',
        'client_reference_id' => (string) $teamId,
        'line_items' => [
            [
                'price_data' => [
                    'currency' => 'lkr',
                    'product_data' => [
                        'name' => 'Tournament Registration Fee — ' . $teamName,
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ],
        ],
    ];

    return stripeRequest('POST', 'checkout/sessions', flattenStripeParams($params));
}

/**
 * Retrieves a Checkout Session by ID, used after redirect back to verify payment.
 */
function retrieveCheckoutSession(string $sessionId): array {
    return stripeRequest('GET', 'checkout/sessions/' . urlencode($sessionId));
}

/**
 * Stripe's form-encoded API needs nested arrays flattened into bracket
 * notation (e.g. line_items[0][price_data][currency]). This recursively
 * converts a normal PHP nested array into that flat key => value format.
 */
function flattenStripeParams(array $params, string $prefix = ''): array {
    $result = [];
    foreach ($params as $key => $value) {
        $fullKey = $prefix === '' ? $key : "{$prefix}[{$key}]";
        if (is_array($value)) {
            $result += flattenStripeParams($value, $fullKey);
        } else {
            $result[$fullKey] = $value;
        }
    }
    return $result;
}