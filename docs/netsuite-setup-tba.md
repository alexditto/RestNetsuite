# NetSuite setup: Token-Based Authentication (TBA)

Step-by-step for configuring NetSuite so this client can authenticate via TBA.
This is the auth method this package has actually been validated against a live
sandbox with — see the main [README](../README.md#status) for status.

## 1. Enable features

**Setup → Company → Enable Features → SuiteCloud tab**, turn on:

- **REST Web Services**
- **Token-Based Authentication**

Save.

## 2. Create an Integration record

**Setup → Integration → Manage Integrations → New.**

- Give it a name (e.g. `netsuite-client`).
- Under **Authentication**, check **Token-Based Authentication**. Leave OAuth 2.0
  unchecked unless you're also setting that up separately.
- Save.

NetSuite shows a **Consumer Key** and **Consumer Secret** on save — copy both now,
the secret is only shown this one time. If you lose it, you'll need to reset the
credentials on this Integration record and get a new pair.

These map to:

```
NETSUITE_CONSUMER_KEY=<Consumer Key>
NETSUITE_CONSUMER_SECRET=<Consumer Secret>
```

## 3. Create an Access Token

**Setup → Users/Roles → Access Tokens → New.**

- **Application Name**: the Integration record from step 2.
- **User**: the NetSuite user this token acts as. All requests made with this token
  are logged/permissioned as this user.
- **Role**: a role assigned to that user with **REST Web Services** login access
  (see step 4) plus whatever record-level permissions this client needs.
- Save.

NetSuite shows a **Token ID** and **Token Secret** on save — copy both now, same
one-time-only rule as the Consumer Secret.

These map to:

```
NETSUITE_TOKEN_ID=<Token ID>
NETSUITE_TOKEN_SECRET=<Token Secret>
```

## 4. Grant role permissions

The role selected in step 3 needs:

- **REST Web Services** login access (**Setup → Users/Roles → Manage Roles → [role]
  → Setup** subtab → check **Log in using Access Tokens** and **REST Web Services**,
  or confirm the role already has these under its permission set).
- Standard **View/Create/Edit/Full** permissions for every record type this client
  will touch — NetSuite enforces these identically for REST and the UI. If a
  `query()` call fails with a permissions-flavored error, check this role's access
  to the SuiteQL tables referenced in the query.

## 5. Full configuration

```
NETSUITE_ACCOUNT_ID=1234567_SB1
NETSUITE_AUTH_METHOD=tba
NETSUITE_CONSUMER_KEY=
NETSUITE_CONSUMER_SECRET=
NETSUITE_TOKEN_ID=
NETSUITE_TOKEN_SECRET=
```

See the [README](../README.md#sandbox-vs-production) for how `NETSUITE_ACCOUNT_ID`
determines the sandbox vs. production API host.

## Troubleshooting

- **`NetSuiteAuthException` on every request** — usually one of: a credential typo,
  the role missing REST Web Services login access, or **clock skew**. TBA signs
  each request with a timestamp, and NetSuite rejects requests signed too far from
  its own server time — check the host machine's clock if credentials otherwise
  look correct.
- **Works in the UI but not via REST** — the role needs REST Web Services access
  granted explicitly; standard UI permissions alone aren't enough.
- **Consumer/Token secret lost** — there's no way to retrieve it after the fact.
  Reset the Integration record (for the Consumer Secret) or create a new Access
  Token (for the Token Secret).
