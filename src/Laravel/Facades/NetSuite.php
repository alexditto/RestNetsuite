<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Laravel\Facades;

use Ditto\NetSuiteClient\RecordClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ditto\NetSuiteClient\Responses\NetSuiteRecord get(string $recordType, string $id)
 * @method static \Ditto\NetSuiteClient\Responses\NetSuiteRecord create(string $recordType, array $fields)
 * @method static \Ditto\NetSuiteClient\Responses\NetSuiteRecord update(string $recordType, string $id, array $fields)
 * @method static \Ditto\NetSuiteClient\Responses\NetSuiteRecord replace(string $recordType, string $id, array $fields)
 * @method static void delete(string $recordType, string $id)
 * @method static \Ditto\NetSuiteClient\Responses\NetSuiteCollection query(string $suiteQl, int $limit = 1000, int $offset = 0)
 *
 * @see RecordClient
 */
final class NetSuite extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RecordClient::class;
    }
}
