# ditto/netsuite-client

Framework-agnostic PHP client for NetSuite's SuiteTalk REST API, with an optional
Laravel bridge (service provider + config publishing). Built to replace the
`netsuitephp/netsuite-laravel` SOAP integration ahead of Oracle's SOAP deprecation
(2026.1 stops including new SOAP endpoints by default, 2027.1 disallows new SOAP
integrations).

See [`netsuite-client-project-outline.md`](./netsuite-client-project-outline.md) for
the full MVP spec, scope, and phased build plan.

## Status

Everything scoped for v1 in the project outline is implemented and unit tested:
`NetSuiteConfig`, the exception hierarchy, the Laravel service provider + `NetSuite`
facade, both authentication methods (OAuth 2.0 Client Credentials/M2M and
Token-Based Authentication), HTTP transport (auth-applying `HttpClient` +
429-retry `RetryMiddleware`), response parsing (`ResponseParser` + `NetSuiteRecord` +
`NetSuiteCollection`, with status/error-payload → exception mapping), and
`RecordClient` itself (`get`/`create`/`update`/`replace`/`delete`/`query`).

Not yet done: validation against a real NetSuite sandbox (OAuth2 in particular is
built to spec but unverified against a live account) and wiring up a first real
consuming app as a smoke test — see the project outline's Phases 6–7.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12 (only if using the Laravel bridge — the core client has no
  hard Laravel dependency)

## Installation

```bash
composer require ditto/netsuite-client
```

In a Laravel app, publish the config file:

```bash
php artisan vendor:publish --tag=netsuite-client-config
```

and set the following in `.env` (see `.env.example` for the full list — Token-Based
Authentication or OAuth 2.0 M2M, selected via `NETSUITE_AUTH_METHOD`):

```
NETSUITE_ACCOUNT_ID=
NETSUITE_AUTH_METHOD=tba
NETSUITE_CONSUMER_KEY=
NETSUITE_CONSUMER_SECRET=
NETSUITE_TOKEN_ID=
NETSUITE_TOKEN_SECRET=
```

## Usage

```php
use Ditto\NetSuiteClient\Laravel\Facades\NetSuite;

$customer = NetSuite::get('customer', '123');
$customer->get('entityid');

$created = NetSuite::create('customer', ['companyname' => 'Acme Co']);

NetSuite::update('customer', '123', ['email' => 'new@example.com']);

NetSuite::delete('customer', '123');

$results = NetSuite::query('SELECT id, entityid FROM customer WHERE isinactive = \'F\'');
foreach ($results as $row) {
    // $row is a raw associative array — SuiteQL rows have no fixed record shape.
}
```

Outside Laravel, construct the pieces directly:

```php
use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\TokenBasedAuthStrategy;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\RecordClient;
use Ditto\NetSuiteClient\Responses\ResponseParser;

$config = new NetSuiteConfig(
    accountId: '1234567_SB1',
    authMethod: AuthMethod::TokenBasedAuth,
    consumerKey: '...',
    consumerSecret: '...',
    tokenId: '...',
    tokenSecret: '...',
);

$client = new RecordClient(
    new HttpClient($config, new TokenBasedAuthStrategy($config)),
    new EndpointBuilder($config),
    new ResponseParser(),
);

$client->get('customer', '123');
```

## Testing

```bash
composer install
vendor/bin/phpunit
```
