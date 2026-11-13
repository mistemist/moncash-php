<?php

declare(strict_types=1);

namespace MonCash;

/**
 * Result of a CreatePayment call.
 *
 * The customer must be redirected to $redirectUrl to approve the payment
 * on MonCash. A completed redirect back to your site is NOT proof of
 * payment — always confirm with MonCashClient::retrieveOrderPayment().
 */
final class Payment
{
    public function __construct(
        public readonly string $token,
        public readonly string $redirectUrl,
        /** @var array<string, mixed> Raw API response, for fields we do not model. */
        public readonly array $raw = [],
    ) {
    }
}
