<?php
class PayPalAPI {
    private $client_id;
    private $client_secret;
    private $is_sandbox;
    private $access_token;

    public function __construct() {
        $this->client_id = PAYPAL_CLIENT_ID;
        $this->client_secret = PAYPAL_CLIENT_SECRET;
        $this->is_sandbox = PAYPAL_SANDBOX;
    }

    private function getApiUrl() {
        return $this->is_sandbox 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com';
    }

    private function getAccessToken() {
        if ($this->access_token) {
            return $this->access_token;
        }

        $ch = curl_init($this->getApiUrl() . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ":" . $this->client_secret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $result = curl_exec($ch);
        if (!$result) {
            throw new Exception("Error getting PayPal access token: " . curl_error($ch));
        }

        $data = json_decode($result);
        $this->access_token = $data->access_token;
        return $this->access_token;
    }

    public function createOrder($course, $user) {
        $access_token = $this->getAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $course['id'],
                'description' => "Inscripción al curso: " . $course['title'],
                'amount' => [
                    'currency_code' => 'MXN',
                    'value' => number_format($course['price'], 2, '.', '')
                ]
            ]],
            'application_context' => [
                'return_url' => BASE_URL . '/payment/paypal/capture.php',
                'cancel_url' => BASE_URL . '/payment/paypal/cancel.php'
            ]
        ];

        $ch = curl_init($this->getApiUrl() . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);

        $result = curl_exec($ch);
        if (!$result) {
            throw new Exception("Error creating PayPal order: " . curl_error($ch));
        }

        return json_decode($result, true);
    }

    public function captureOrder($order_id) {
        $access_token = $this->getAccessToken();

        $ch = curl_init($this->getApiUrl() . "/v2/checkout/orders/{$order_id}/capture");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);

        $result = curl_exec($ch);
        if (!$result) {
            throw new Exception("Error capturing PayPal order: " . curl_error($ch));
        }

        return json_decode($result, true);
    }

    public function validateWebhook($headers, $body) {
        $webhook_id = PAYPAL_WEBHOOK_ID;
        $auth_algo = $headers['PAYPAL-AUTH-ALGO'] ?? '';
        $cert_url = $headers['PAYPAL-CERT-URL'] ?? '';
        $transmission_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
        $transmission_sig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
        $transmission_time = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';

        $validation_url = $this->getApiUrl() . '/v1/notifications/verify-webhook-signature';
        $validation_payload = [
            'auth_algo' => $auth_algo,
            'cert_url' => $cert_url,
            'transmission_id' => $transmission_id,
            'transmission_sig' => $transmission_sig,
            'transmission_time' => $transmission_time,
            'webhook_id' => $webhook_id,
            'webhook_event' => json_decode($body, true)
        ];

        $ch = curl_init($validation_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validation_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken()
        ]);

        $result = curl_exec($ch);
        if (!$result) {
            throw new Exception("Error validating webhook: " . curl_error($ch));
        }

        $data = json_decode($result, true);
        return $data['verification_status'] === 'SUCCESS';
    }
} 