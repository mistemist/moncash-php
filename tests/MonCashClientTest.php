<?php

declare(strict_types=1);

namespace MonCash\Tests;

use MonCash\Exceptions\AuthenticationException;
use MonCash\MonCashClient;
use PHPUnit\Framework\TestCase;

final class MonCashClientTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, headers: array<int, string>, body: ?string}> */
    private array $requests = [];

    /**
     * Build a client whose HTTP layer answers from a script of responses,
     * keyed by URL suffix. No network is touched.
     *
     * @param array<string, array{status: int, body: string}> $script
     */
    private function fakeClient(array $script): MonCashClient
    {
        $this->requests = [];
        $handler = function (string $method, string $url, array $headers, ?string $body) use ($script): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            foreach ($script as $suffix => $response) {
                if (str_ends_with($url, $suffix)) {
                    return $response;
                }
            }
            return ['status' => 404, 'body' => '{}'];
        };

        return new MonCashClient('test-id', 'test-secret', 'sandbox', $handler);
    }

    public function testCreatePaymentReturnsRedirectUrl(): void
    {
        $client = $this->fakeClient([
            '/Api/oauth/token' => ['status' => 200, 'body' => '{"access_token":"tok-123","expires_in":3600}'],
            '/Api/v1/CreatePayment' => ['status' => 200, 'body' => '{"payment":{"token":"pay-abc","reference":"ORDER-001"}}'],
        ]);

        $payment = $client->createPayment(500, 'ORDER-001');

        self::assertSame('pay-abc', $payment->token);
        self::assertSame(
            'https://sandbox.moncashbutton.digicelgroup.com/Moncash-middleware/Payment/Redirect?token=pay-abc',
            $payment->redirectUrl,
        );

        // The create-payment call must carry the amount and order id as JSON.
        $create = $this->requests[1];
        self::assertSame(['amount' => 500, 'orderId' => 'ORDER-001'], json_decode((string) $create['body'], true));
        self::assertContains('Authorization: Bearer tok-123', $create['headers']);
    }

    public function testTokenIsFetchedWithBasicAuthAndCached(): void
    {
        $client = $this->fakeClient([
            '/Api/oauth/token' => ['status' => 200, 'body' => '{"access_token":"tok-123","expires_in":3600}'],
            '/Api/v1/CreatePayment' => ['status' => 200, 'body' => '{"payment":{"token":"pay-abc"}}'],
        ]);

        $client->createPayment(100, 'ORDER-1');
        $client->createPayment(100, 'ORDER-2');

        $tokenCalls = array_filter($this->requests, fn (array $r) => str_ends_with($r['url'], '/Api/oauth/token'));
        self::assertCount(1, $tokenCalls, 'Token should be requested once and cached.');
        self::assertContains(
            'Authorization: Basic ' . base64_encode('test-id:test-secret'),
            $tokenCalls[array_key_first($tokenCalls)]['headers'],
        );
    }

    public function testRetrieveOrderPaymentPostsOrderId(): void
    {
        $client = $this->fakeClient([
            '/Api/oauth/token' => ['status' => 200, 'body' => '{"access_token":"tok-123","expires_in":3600}'],
            '/Api/v1/RetrieveOrderPayment' => ['status' => 200, 'body' => '{"payment":{"status":"successful"}}'],
        ]);

        $result = $client->retrieveOrderPayment('ORDER-001');

        self::assertSame('successful', $result['payment']['status']);
        $verify = $this->requests[1];
        self::assertSame(['orderId' => 'ORDER-001'], json_decode((string) $verify['body'], true));
    }

    public function testAuthenticationFailureThrows(): void
    {
        $client = $this->fakeClient([
            '/Api/oauth/token' => ['status' => 401, 'body' => '{"error":"invalid_client"}'],
        ]);

        $this->expectException(AuthenticationException::class);
        $client->createPayment(100, 'ORDER-1');
    }

    public function testLiveModeUsesProductionBase(): void
    {
        $client = new MonCashClient('id', 'secret', 'live');
        self::assertSame(
            'https://moncashbutton.digicelgroup.com/Moncash-middleware/Payment/Redirect?token=x',
            $client->gatewayRedirectUrl('x'),
        );
    }
}
