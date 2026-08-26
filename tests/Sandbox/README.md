# Sandbox tests

Tests in `tests/Sandbox/Tests/` make real HTTP calls to an actual NetSuite sandbox
account — they create, read, update, and delete real records. That's deliberately
different from everything under the top-level `tests/` directory, which runs against
mocked PSR-18 clients and never touches the network.

## Safety

- **Never committed.** `tests/Sandbox/Tests/` is gitignored (see `.gitignore`). Only
  this README, `SandboxTestCase.php`, and `bootstrap.php` are tracked — they contain no
  credentials and don't talk to NetSuite themselves.
- **Never run by default.** `phpunit.xml.dist` explicitly excludes `tests/Sandbox`, so
  a plain `vendor/bin/phpunit` / `composer test` (what CI runs) never touches these,
  even though they exist locally and gitignore has no effect on that.
- **Refuses to run against production.** `SandboxTestCase::buildConfig()` throws if
  `NETSUITE_ENVIRONMENT` resolves to `production` — a code-level backstop, not just
  gitignore hygiene, in case these ever get copied somewhere unexpected.

Because the actual test files never enter version control, they don't travel with a
`git clone` — a fresh checkout (or a teammate's machine) has the harness but no test
files. Write new ones locally as needed, following the pattern of whatever's already in
`tests/Sandbox/Tests/`; there's nothing to keep in sync since nothing there is shared.

## Running

```bash
composer test:sandbox
# equivalent to:
vendor/bin/phpunit -c phpunit-sandbox.xml.dist
```

Requires a `.env` in the project root (gitignored, see `.env.example`) with real
sandbox credentials — `NETSUITE_ACCOUNT_ID` plus whichever of the `oauth2.*`/`tba.*`
vars match `NETSUITE_AUTH_METHOD`. `tests/Sandbox/bootstrap.php` loads `.env` into the
environment before PHPUnit runs. Tests skip themselves (rather than fail) if
`NETSUITE_ACCOUNT_ID` isn't set.

## Writing a new sandbox test

```php
<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Sandbox\Tests;

use Ditto\NetSuiteClient\Tests\Sandbox\SandboxTestCase;

final class ExampleSandboxTest extends SandboxTestCase
{
    public function test_it_does_something_against_the_real_sandbox(): void
    {
        $client = $this->client(); // fully wired RecordClient, real auth

        $record = $client->get('customer', '123');

        $this->assertSame('Some Company', $record->get('companyname'));
    }
}
```

Guidelines:

- Namespace must be `Ditto\NetSuiteClient\Tests\Sandbox\Tests` (PSR-4, matches the
  `tests/Sandbox/Tests/` directory) so the standalone Composer autoloader picks it up
  even though the file isn't tracked by git.
- Clean up anything you create — `tearDown()` should delete records the test created,
  wrapped in try/catch so a cleanup failure doesn't mask the real assertion failure
  (see the pattern in whatever CRUD test already exists locally).
- Don't hardcode assumptions about specific existing record IDs unless you know they're
  stable in your sandbox — prefer creating your own fixture data and cleaning it up.
