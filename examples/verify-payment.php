<?php

/**
 * Example: verify a payment server-side before fulfilling an order.
 * This is the source of truth — never trust the customer's redirect alone.
 *
 * Usage:
 *   MONCASH_CLIENT_ID=xxx MONCASH_CLIENT_SECRET=yyy php examples/verify-payment.php ORDER-001
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MonCash\MonCashClient;

$orderId = $argv[1] ?? null;
if ($orderId === null) {
    fwrite(STDERR, "Usage: php examples/verify-payment.php <orderId>\n");
    exit(1);
}

$client = new MonCashClient(
    clientId: (string) getenv('MONCASH_CLIENT_ID'),
    clientSecret: (string) getenv('MONCASH_CLIENT_SECRET'),
    mode: 'sandbox',
);

$result = $client->retrieveOrderPayment($orderId);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
