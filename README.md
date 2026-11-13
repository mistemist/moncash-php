# moncash-php

A clean, framework-agnostic **PHP SDK for the Digicel MonCash API** (Haiti 🇭🇹).
Zero dependencies — just PHP 8.1+ and cURL.

> **Unofficial project.** Not affiliated with, endorsed by, or sponsored by Digicel or MonCash.
> "MonCash" is a trademark of Digicel. You need your own MonCash Business account and API
> credentials to use this library.

*Yon SDK PHP senp epi pwòp pou API MonCash Digicel la — peman mobil an Ayiti, san depandans.*

## Features

- OAuth2 authentication with automatic token caching and refresh
- Create a payment and get the MonCash gateway redirect URL
- Verify a payment by order id or transaction id (the source of truth)
- Sandbox and production environments
- Amounts in **HTG** (MonCash is HTG only)
- Fully typed, with an injectable HTTP transport for easy testing

## Requirements

- PHP >= 8.1 with the cURL and JSON extensions
- A MonCash Business account (`clientId` + `clientSecret`), created from the
  MonCash Business portal. Test in the **sandbox** portal first, then ask
  Digicel's business services to move you to production.

## Installation

```bash
composer require moncash-php/moncash-php
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MonCash\MonCashClient;

$client = new MonCashClient(
    clientId: getenv('MONCASH_CLIENT_ID'),
    clientSecret: getenv('MONCASH_CLIENT_SECRET'),
    mode: 'sandbox', // or 'live'
);

// 1. Create a payment (amount in HTG, your own unique order id)
$payment = $client->createPayment(500, 'ORDER-001');

// 2. Redirect the customer to MonCash to approve the payment
header('Location: ' . $payment->redirectUrl);
exit;
```

When MonCash redirects the customer back to your site, **do not trust the
redirect** — confirm the payment server-side:

```php
$result = $client->retrieveOrderPayment('ORDER-001');
// Inspect $result for the payment status before delivering anything.
```

Or look a payment up by its MonCash transaction id:

```php
$result = $client->retrieveTransactionPayment('123456789');
```

## Security notes

- **Never** expose your `clientSecret` in frontend code or commit it to a repository.
  Load credentials from environment variables (see `.env.example`).
- A customer returning from the MonCash gateway is **not** proof of payment.
  Always verify with `retrieveOrderPayment()` before fulfilling an order.
- Use a unique `orderId` per order so a payment can never be replayed against
  a different purchase.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite runs fully offline against a fake HTTP transport.

## Roadmap

- Laravel service provider + facade
- Transfer (cash-out) support for business accounts
- Webhook/notification verification helpers

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).
Please keep the library dependency-free.

## License

[MIT](LICENSE)
