<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Exceptions;

class NetSuiteServerException extends NetSuiteException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
    ) {
        parent::__construct($message);
    }
}
