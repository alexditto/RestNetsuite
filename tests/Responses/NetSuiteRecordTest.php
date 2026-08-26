<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Responses;

use Ditto\NetSuiteClient\Responses\NetSuiteRecord;
use Ditto\NetSuiteClient\Tests\TestCase;
use LogicException;

final class NetSuiteRecordTest extends TestCase
{
    private function record(): NetSuiteRecord
    {
        return new NetSuiteRecord(
            recordType: 'customer',
            id: '123',
            fields: ['entityid' => 'Acme Co', 'balance' => 100.5],
            links: [['rel' => 'self', 'href' => 'https://example.com/customer/123']],
        );
    }

    public function test_it_exposes_fields_via_get(): void
    {
        $record = $this->record();

        $this->assertSame('Acme Co', $record->get('entityid'));
        $this->assertSame('fallback', $record->get('missing', 'fallback'));
    }

    public function test_it_exposes_fields_via_array_access(): void
    {
        $record = $this->record();

        $this->assertTrue(isset($record['entityid']));
        $this->assertFalse(isset($record['missing']));
        $this->assertSame(100.5, $record['balance']);
        $this->assertNull($record['missing']);
    }

    public function test_it_exposes_all_fields_via_to_array(): void
    {
        $record = $this->record();

        $this->assertSame(['entityid' => 'Acme Co', 'balance' => 100.5], $record->toArray());
    }

    public function test_it_is_read_only(): void
    {
        $record = $this->record();

        $this->expectException(LogicException::class);

        $record['entityid'] = 'changed';
    }
}
