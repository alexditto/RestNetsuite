<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Exceptions;

class NetSuiteValidationException extends NetSuiteException
{
    /**
     * @param array<int, array{'o:errorCode'?: string, message?: string}> $errorDetails Raw `o:errorDetails` entries from the NetSuite response.
     */
    public function __construct(
        string $message,
        public readonly array $errorDetails = [],
    ) {
        parent::__construct($message);
    }
}
