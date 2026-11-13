<?php

/**
 * Example: create a MonCash payment and redirect the customer to approve it.
 *
 * Usage (with the PHP built-in server):
 *   MONCASH_CLIENT_ID=xxx MONCASH_CLIENT_SECRET=yyy php -S localhost:8000 examples/create-payment.php
 * Then open http://localhost:8000 in a browser.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MonCash\MonCashClient;

$client = new MonCashClient(
    clientId: (string) getenv('MONCASH_CLIENT_ID'),
    clientSecret: (string) getenv('MONCASH_CLIENT_SECRET'),
    mode: 'sandbox',
);

$orderId = 'ORDER-' . date('YmdHis');
$payment = $client->createPayment(500, $orderId); // 500 HTG

header('Location: ' . $payment->redirectUrl);
exit;
