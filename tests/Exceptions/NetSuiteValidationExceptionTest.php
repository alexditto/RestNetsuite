<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Exceptions;

use Ditto\NetSuiteClient\Exceptions\NetSuiteValidationException;
use Ditto\NetSuiteClient\Tests\TestCase;

final class NetSuiteValidationExceptionTest extends TestCase
{
    public function test_it_exposes_field_level_error_details(): void
    {
        $details = [
            ['o:errorCode' => 'INVALID_FLD_VALUE', 'message' => 'Invalid email address'],
        ];

        $exception = new NetSuiteValidationException('Validation failed', $details);

        $this->assertSame($details, $exception->errorDetails);
    }
}
