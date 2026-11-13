<?php

declare(strict_types=1);

namespace MonCash;

use MonCash\Exceptions\AuthenticationException;
use MonCash\Exceptions\MonCashException;

/**
 * Client for the Digicel MonCash REST API (Haiti).
 *
 * Flow:
 *   1. $payment = $client->createPayment(500, 'ORDER-001');
 *   2. Redirect the customer to $payment->redirectUrl (they approve on MonCash).
 *   3. When the customer returns, CONFIRM server-side:
 *      $status = $client->retrieveOrderPayment('ORDER-001');
 *
 * Amounts are in HTG (decimal, not centimes). MonCash is HTG only.
 *
 * You need a MonCash Business account to obtain a client id / secret pair.
 * Always develop against the sandbox first (mode: 'sandbox').
 */
final class MonCashClient
{
    public const BASE_SANDBOX = 'https://sandbox.moncashbutton.digicelgroup.com';
    public const BASE_LIVE = 'https://moncashbutton.digicelgroup.com';

    private string $baseUrl;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    /** @var callable(string, string, array<int, string>, ?string): array{status: int, body: string} */
    private $httpHandler;

    /**
     * @param string        $mode        'sandbox' (default) or 'live'
     * @param callable|null $httpHandler Optional custom HTTP transport (used by tests).
     *                                   Signature: fn(string $method, string $url, array $headers, ?string $body)
     *                                   => array{status: int, body: string}
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        string $mode = 'sandbox',
        ?callable $httpHandler = null,
    ) {
        $this->baseUrl = match ($mode) {
            'sandbox' => self::BASE_SANDBOX,
            'live' => self::BASE_LIVE,
            default => throw new MonCashException("Unknown mode '{$mode}', expected 'sandbox' or 'live'."),
        };
        $this->httpHandler = $httpHandler ?? $this->curlHandler(...);
    }

    /**
     * Create a payment and get the URL to redirect the customer to.
     *
     * @param float|int $amount  Amount in HTG (e.g. 500 or 250.50).
     * @param string    $orderId Your unique order reference. Reuse = same order.
     */
    public function createPayment(float|int $amount, string $orderId): Payment
    {
        if ($amount <= 0) {
            throw new MonCashException('Amount must be greater than zero.');
        }
        if ($orderId === '') {
            throw new MonCashException('orderId must not be empty.');
        }

        $response = $this->postJson('/Api/v1/CreatePayment', [
            'amount' => $amount,
            'orderId' => $orderId,
        ]);

        $token = $response['payment']['token'] ?? $response['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new MonCashException(
                'MonCash did not return a payment token.',
                null,
                json_encode($response) ?: null,
            );
        }

        return new Payment(
            token: $token,
            redirectUrl: $this->gatewayRedirectUrl($token),
            raw: $response,
        );
    }

    /** Build the MonCash gateway redirect URL for a payment token. */
    public function gatewayRedirectUrl(string $token): string
    {
        return $this->baseUrl . '/Moncash-middleware/Payment/Redirect?token=' . urlencode($token);
    }

    /**
     * Retrieve the state of a payment by your order id.
     * This is the source of truth for whether an order was paid.
     *
     * @return array<string, mixed>
     */
    public function retrieveOrderPayment(string $orderId): array
    {
        return $this->postJson('/Api/v1/RetrieveOrderPayment', ['orderId' => $orderId]);
    }

    /**
     * Retrieve the state of a payment by its MonCash transaction id.
     *
     * @return array<string, mixed>
     */
    public function retrieveTransactionPayment(string $transactionId): array
    {
        return $this->postJson('/Api/v1/RetrieveTransactionPayment', ['transactionId' => $transactionId]);
    }

    // ------------------------------------------------------------------ HTTP

    /** @return array<string, mixed> */
    private function postJson(string $path, array $payload): array
    {
        $token = $this->accessToken();

        [$status, $body] = $this->send('POST', $this->baseUrl . $path, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ], json_encode($payload) ?: '{}');

        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            throw new MonCashException(
                "MonCash API error on {$path} (HTTP {$status}).",
                $status,
                $body,
            );
        }
        if (!is_array($decoded)) {
            throw new MonCashException("MonCash returned a non-JSON response on {$path}.", $status, $body);
        }

        return $decoded;
    }

    private function accessToken(): string
    {
        // Refresh slightly before expiry: MonCash tokens are short-lived.
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt - 30) {
            return $this->accessToken;
        }

        [$status, $body] = $this->send('POST', $this->baseUrl . '/Api/oauth/token', [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], http_build_query(['grant_type' => 'client_credentials']));

        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
            throw new AuthenticationException(
                "Could not obtain a MonCash access token (HTTP {$status}). Check your client id / secret.",
                $status,
                $body,
            );
        }

        $this->accessToken = (string) $decoded['access_token'];
        $this->tokenExpiresAt = time() + (int) ($decoded['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /** @return array{0: int, 1: string} [status, body] */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        $result = ($this->httpHandler)($method, $url, $headers, $body);

        return [(int) $result['status'], (string) $result['body']];
    }

    /** @return array{status: int, body: string} */
    private function curlHandler(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new MonCashException('Could not initialise cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new MonCashException('HTTP request to MonCash failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $raw];
    }
}
