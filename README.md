# aditto/netsuite-client

Framework-agnostic PHP client for NetSuite's SuiteTalk REST API, with an optional
Laravel bridge (service provider + config publishing). Built to replace SOAP-based
NetSuite integrations (e.g. `netsuitephp/netsuite-laravel`) ahead of Oracle's SOAP
deprecation (2026.1 stops including new SOAP endpoints by default, 2027.1 disallows
new SOAP integrations).

## Status

All v1 features are implemented and unit tested: `NetSuiteConfig`, the exception
hierarchy, the Laravel service provider + `NetSuite` facade, both authentication
methods (OAuth 2.0 Client Credentials/M2M and Token-Based Authentication), HTTP
transport (auth-applying `HttpClient` + 429-retry `RetryMiddleware`), response parsing
(`ResponseParser` + `NetSuiteRecord` + `NetSuiteCollection`, with status/error-payload
→ exception mapping), and `RecordClient` itself
(`get`/`create`/`update`/`replace`/`delete`/`query`).

Token-Based Authentication has been validated against a real NetSuite sandbox: full
`customer` CRUD round-trip and paginated SuiteQL both pass (`composer test:sandbox` —
see `tests/Sandbox/README.md`; these tests aren't committed to the repo by design).
OAuth 2.0 M2M is built to NetSuite's documented spec but not yet confirmed against a
live account.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12 (only if using the Laravel bridge — the core client has no
  hard Laravel dependency)

## Installation

```bash
composer require aditto/netsuite-client
```

In a Laravel app, publish the config file:

```bash
php artisan vendor:publish --tag=netsuite-client-config
```

## NetSuite-side setup

You need an Integration record and a way to authenticate as it — either Token-Based
Authentication (TBA) or OAuth 2.0 Client Credentials (M2M). TBA is simpler to set up
(no certificate management) and is what this package has actually been validated
against; OAuth2 M2M is built to NetSuite's documented spec but not yet confirmed
live (see Status above).

Full step-by-step walkthroughs for each method live in `docs/`:

- [Token-Based Authentication setup](./docs/netsuite-setup-tba.md)
- [OAuth 2.0 M2M setup](./docs/netsuite-setup-oauth2.md)

The summary below covers both together; use the dedicated guide above for the
full click-by-click process.

### 1. Enable features

**Setup → Company → Enable Features → SuiteCloud**, turn on:

- REST Web Services
- Token-Based Authentication (if using TBA)
- OAuth 2.0 (if using OAuth2 M2M)

### 2. Create an Integration record

**Setup → Integration → Manage Integrations → New.**

- Name it (e.g. "netsuite-client").
- Under Authentication, check **Token-Based Authentication** for TBA, or **OAuth 2.0**
  with the **Client Credentials (Machine to Machine) Grant** checkbox for OAuth2 M2M.
- Save. NetSuite shows a **Consumer Key**/**Consumer Secret** (TBA) or **Client ID**
  (OAuth2) — copy these now, the secret is only shown once.

### 3a. TBA: create an Access Token

**Setup → Users/Roles → Access Tokens → New.**

- Pick the Integration record from step 2, a user, and a role with REST Web Services
  login access plus whatever record-level permissions you need (see step 4).
- Save. NetSuite shows a **Token ID**/**Token Secret** — copy these now, the secret is
  only shown once. These map to `NETSUITE_TOKEN_ID`/`NETSUITE_TOKEN_SECRET`; the
  Consumer Key/Secret from step 2 map to `NETSUITE_CONSUMER_KEY`/`NETSUITE_CONSUMER_SECRET`.

### 3b. OAuth2 M2M: generate and upload a certificate

M2M authenticates via an ES256-signed JWT, so you need an EC (P-256) keypair — NetSuite
verifies the JWT against a certificate you upload, it never sees your private key.

```bash
openssl ecparam -name prime256v1 -genkey -noout -out netsuite-private-key.pem
openssl req -new -x509 -key netsuite-private-key.pem -out netsuite-cert.pem -days 730 -subj "/CN=netsuite-client"
```

Upload `netsuite-cert.pem` under the Integration record's **Certificates** tab —
NetSuite assigns a **Certificate ID** (`NETSUITE_CERTIFICATE_ID`). The Client ID from
step 2 is `NETSUITE_CLIENT_ID`. Keep `netsuite-private-key.pem` out of version control
(`NETSUITE_PRIVATE_KEY_PATH` points to it on disk) — certificates are valid up to 2
years and rotation is manual for v1 (see the project outline's non-goals).

### 4. Grant role permissions

Whichever role is tied to the Access Token (TBA) or the Integration record's user
(OAuth2) needs REST Web Services login access, plus standard View/Create/Edit/Full
permissions for every record type you'll touch through this client — NetSuite
enforces these the same way for REST as it does for the UI. If `query()` fails with a
permissions-flavored error, check this role has access to the tables referenced in
your SuiteQL.

### Sandbox vs. production

There's no separate sandbox/production endpoint config — the API host is derived
entirely from `NETSUITE_ACCOUNT_ID`:

- Sandbox account IDs look like `1234567_SB1` → host
  `1234567-sb1.suitetalk.api.netsuite.com`
- Production account IDs look like `1234567` → host
  `1234567.suitetalk.api.netsuite.com`

`NETSUITE_ENVIRONMENT` (`sandbox`/`production`) doesn't affect the URL — it's a
declared-intent safety flag. In particular, `tests/Sandbox/SandboxTestCase` refuses to
run against a config with `environment=production`, as a backstop against ever
pointing the live-sandbox test harness at a real account by accident.

### Configuration

Set the following in `.env` (see `.env.example` for the full list):

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
$customer->get('companyName');

$created = NetSuite::create('customer', ['companyName' => 'Acme Co']);

NetSuite::update('customer', '123', ['email' => 'new@example.com']);

NetSuite::delete('customer', '123');

$results = NetSuite::query("SELECT id, companyName FROM customer WHERE isinactive = 'F'");
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

### A note on field names

NetSuite's REST API returns field names in **camelCase** (`companyName`,
`entityId`) in every response body, regardless of what casing was accepted on a
write. `NetSuiteRecord` and `ResponseParser` pass fields through exactly as NetSuite
sent them — this client is intentionally schema-agnostic, it doesn't know or
normalize any record type's field names. Always read fields back using the casing
NetSuite actually returns (check a real response, or NetSuite's REST record browser),
not whatever casing happened to work on a write.

## Logging requests and responses

For local debugging, every REST request/response pair can be logged to disk as a
correlated pair of plain-text files (`{id}.request.txt` / `{id}.response.txt`), one
attempt per pair — including each retried attempt on a 429, not just the final one.
`Authorization` headers are redacted before writing. This is off by default and meant
for local debugging, not production traffic.

In Laravel, enable it via config/env:

```
NETSUITE_LOG_REQUESTS=true
NETSUITE_LOG_PATH=/path/to/log/dir   # defaults to storage_path('logs/netsuite')
```

Outside Laravel, compose `LoggingMiddleware` into the client chain yourself:

```php
use Ditto\NetSuiteClient\Http\FileRequestLogger;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\Http\LoggingMiddleware;
use Ditto\NetSuiteClient\Http\RetryMiddleware;
use GuzzleHttp\Client as GuzzleClient;

$client = new HttpClient($config, $authStrategy, new RetryMiddleware(
    new LoggingMiddleware(new GuzzleClient(), new FileRequestLogger('/path/to/log/dir')),
    maxAttempts: $config->maxAttempts,
));
```

## Migrating from SOAP (netsuitephp/netsuite-php)

Most SOAP calls collapse to a single `get`/`create`/`update`/`replace`/`delete`/`query`
call, since SOAP's typed request/response objects mostly boil down to "record type +
field values." Example — a method that unsubscribes a contact from email by clearing a
custom field:

```php
// Before (netsuitephp/netsuite-php, SOAP)
public function unsubscribeContact(object $contact): void
{
    $contactRecord = new Contact;
    $contactRecord->internalId = $contact->internalId;
    $customFields = new CustomFieldList;

    $subscriberStatus = new BooleanCustomFieldRef;
    $subscriberStatus->value = false;
    $subscriberStatus->scriptId = 'custentity_email_subscribed';

    $customFields->customField[] = $subscriberStatus;
    $contactRecord->customFieldList = $customFields;

    $request = new UpdateRequest;
    $request->record = $contactRecord;
    $updateResponse = $this->service->update($request);

    if (! $updateResponse->writeResponse->status->isSuccess) {
        throw new RuntimeException('Unable to update the Contact.');
    }
}
```

```php
// After (this package, REST)
public function unsubscribeContact(object $contact): void
{
    $this->recordClient->update('contact', $contact->internalId, [
        'custentity_email_subscribed' => false,
    ]);
}
```

The `if (! ...isSuccess) { throw ... }` check has no equivalent to write — `update()`
already throws a typed exception (`NetSuiteValidationException`, `NetSuiteAuthException`,
etc., all extending `NetSuiteException`) on any non-2xx response, so a plain call that
returns normally means it succeeded. `RecordClient` never needed to know anything
about the `Contact` record type or the `custentity_email_subscribed` field — that's the
point of the generic-client design (see the project outline's "explicitly out of
scope" section on typed per-record classes).

## Troubleshooting auth

- **`NetSuiteAuthException` on every request** — usually bad credentials, a role
  missing REST Web Services login access, or (TBA specifically) system clock skew:
  TBA signs each request with a timestamp, and NetSuite rejects requests signed too
  far from its own server time.
- **`NetSuiteValidationException` with "Please enter value(s) for: X" on create/update**
  — this is NetSuite telling you a field is mandatory for *this account's*
  configuration (subsidiary, custom mandatory fields, the form in use) — not a client
  bug. Inspect `$exception->errorDetails` for NetSuite's full structured error list.
  OneWorld (multi-subsidiary) accounts commonly require `subsidiary`, and sometimes an
  account-specific custom field for location — there's no generic way to know these
  ahead of time; discover them from the error details, or from NetSuite's UI record
  form for that type.
- **SuiteQL (`query()`) failing with `INVALID_HEADER` / "The required request header
  'Prefer' is missing"** — already handled internally (`RecordClient::query()` always
  sends `Prefer: transient`); this only bites if you're calling the query endpoint
  some other way.
- **`NetSuiteRateLimitException`** — a 429 that persisted past `RetryMiddleware`'s
  retry budget (`NetSuiteConfig::$maxAttempts`, default 3). `$exception->retryAfterSeconds`
  carries what NetSuite's `Retry-After` header said, if present.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests against a real NetSuite sandbox (`composer test:sandbox`) are intentionally not
part of this repo — see `tests/Sandbox/README.md`.