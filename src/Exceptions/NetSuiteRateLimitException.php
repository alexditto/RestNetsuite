<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Exceptions;

class NetSuiteRateLimitException extends NetSuiteException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
