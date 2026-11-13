<?php

declare(strict_types=1);

namespace MonCash\Exceptions;

use RuntimeException;

class MonCashException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
