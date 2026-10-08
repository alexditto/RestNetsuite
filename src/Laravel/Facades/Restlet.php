<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Laravel\Facades;

use Ditto\NetSuiteClient\RestletClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed call(string $script, string $deploy, \Ditto\NetSuiteClient\Http\RestletMethod $method, ?array $body = null)
 *
 * @see RestletClient
 */
final class Restlet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RestletClient::class;
    }
}
