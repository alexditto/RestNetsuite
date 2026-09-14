# NetSuite setup: OAuth 2.0 Client Credentials (M2M)

Step-by-step for configuring NetSuite so this client can authenticate via OAuth
2.0's Client Credentials (machine-to-machine) grant. This method is built to
NetSuite's documented spec but has **not yet been confirmed against a live
account** — see the main [README](../README.md#status) for current status.

## 1. Enable features

**Setup → Company → Enable Features → SuiteCloud tab**, turn on:

- **REST Web Services**
- **OAuth 2.0**

Save.

## 2. Generate an EC (P-256) keypair

M2M authenticates via an ES256-signed JWT client assertion, so you need an EC
keypair. NetSuite verifies the JWT against a certificate you upload — it never
sees your private key.

```bash
openssl ecparam -name prime256v1 -genkey -noout -out netsuite-private-key.pem
openssl req -new -x509 -key netsuite-private-key.pem -out netsuite-cert.pem -days 730 -subj "/CN=netsuite-client"
```

Keep `netsuite-private-key.pem` out of version control — it never gets uploaded
anywhere, only referenced locally by path.

## 3. Create an Integration record

**Setup → Integration → Manage Integrations → New.**

- Give it a name (e.g. `netsuite-client`).
- Under **Authentication**, check **OAuth 2.0**, then check **Client Credentials
  (Machine to Machine) Grant**.
- Select the scope(s) this integration needs (at minimum, REST Web Services).
- Save.
- Confirm **State** is **Enabled**.

NetSuite shows a **Client ID** on save, in a one-time confirmation banner —
this maps to:

```
NETSUITE_CLIENT_ID=<Client ID>
```

**Don't confuse this with the Application ID** — the Integration record also
has a permanent, always-visible **Application ID** field, which is a
different value used for other auth flows. Using it here silently breaks
token requests (NetSuite returns a generic `{"error":"server_error"}` with no
indication which field was wrong — see Troubleshooting below).

## 4. Create the OAuth 2.0 Client Credentials (M2M) Setup mapping

This step is easy to miss and **is mandatory** — the Integration record alone
is not enough for the Client Credentials grant to work, regardless of how it's
configured.

**Setup → Integration → Manage Authentication → OAuth 2.0 Client Credentials
(M2M) Setup → Create New.**

- **Application** — the Integration record from step 3 (only selectable
  because Client Credentials (M2M) Grant is checked on it).
- **Entity** — the (active) employee whose permissions the machine-to-machine
  calls run as.
- **Role** — a role assigned to that entity with REST Web Services login
  access, "Log in using OAuth 2.0 Access Tokens" permission, and standard
  View/Create/Edit/Full permissions for every record type this client will
  touch.
- **Certificate** — upload `netsuite-cert.pem` from step 2 here.
- Save.

NetSuite assigns a **Certificate ID** to the uploaded certificate — this is
the JWT's `kid` (key ID) header claim, and maps to:

```
NETSUITE_CERTIFICATE_ID=<Certificate ID>
```

`NETSUITE_PRIVATE_KEY_PATH` must point at the private key paired with
*this* uploaded certificate — if you regenerate the keypair/certificate at any
point, both the Certificate ID and the private key path need to be updated
together. A mismatched pair produces the same undifferentiated
`server_error` as every other setup mistake here.

## 5. Grant role permissions

Covered by the Role selected in step 4 — see the bullet above. There's no
separate permissions step; the mapping's Entity/Role *is* what NetSuite uses
to authorize the token.

## 6. Full configuration

```
NETSUITE_ACCOUNT_ID=1234567_SB1
NETSUITE_AUTH_METHOD=oauth2
NETSUITE_CLIENT_ID=
NETSUITE_CERTIFICATE_ID=
NETSUITE_PRIVATE_KEY_PATH=/path/to/netsuite-private-key.pem
```

See the [README](../README.md#sandbox-vs-production) for how `NETSUITE_ACCOUNT_ID`
determines the sandbox vs. production API host.

## Certificate rotation

Certificates uploaded to the M2M Setup mapping are valid for up to 2 years
(matching the `-days 730` used above; max 5 active certificates per
Integration record). Rotation is manual: generate a new keypair, upload the
new certificate to the mapping (NetSuite assigns a new Certificate ID), update
`NETSUITE_CERTIFICATE_ID`/`NETSUITE_PRIVATE_KEY_PATH` together, then revoke
the old certificate. **Sandbox refresh clears the M2M Setup mapping** — it
must be recreated (step 4) after every sandbox refresh; the Integration record
itself survives the refresh.

## Troubleshooting

NetSuite's OAuth 2.0 token endpoint returns an undifferentiated
`{"error":"server_error"}` (HTTP 500) for most Client Credentials setup
mistakes, and these failures don't appear in **Login Audit Trail** — so the
response gives no signal about which specific thing is wrong. If token
requests fail, work through this checklist rather than guessing from the
error text:

- `NETSUITE_CLIENT_ID` is the Client ID from the Integration record's one-time
  save banner, **not** the Application ID (see step 3).
- The **OAuth 2.0 Client Credentials (M2M) Setup** mapping (step 4) actually
  exists — the Integration record + certificate alone is not sufficient.
- `NETSUITE_CERTIFICATE_ID` matches the certificate uploaded to *that mapping*
  (not a certificate uploaded anywhere else), and `NETSUITE_PRIVATE_KEY_PATH`
  points at the exact private key paired with it.
- The Integration record's **State** is **Enabled**.
- The mapping's **Entity** is an active employee, and its **Role** has REST
  Web Services login access, "Log in using OAuth 2.0 Access Tokens"
  permission, and the record-level permissions this client needs.
- **REST Web Services** and **OAuth 2.0** are both enabled under
  Setup → Company → Enable Features → SuiteCloud (separate from the
  Integration record's own checkboxes).
- If it was working before and stopped, check whether the sandbox was
  refreshed — that clears the M2M mapping (see Certificate rotation above).

This method is still **unverified end-to-end against a live account** — a
2026-09-14 investigation against a real OneWorld sandbox worked through this
entire checklist (confirming each item individually) and still got a bare
`server_error` from the token endpoint with no further diagnostic signal
available outside NetSuite. If you get this working, it would help the
project to record what (if anything) differed from this guide — otherwise the
next likely step is a NetSuite support case, since 500s that never reach the
audit trail point at something NetSuite-side that isn't independently
verifiable from outside their UI.
