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

NetSuite shows a **Client ID** on save — this maps to:

```
NETSUITE_CLIENT_ID=<Client ID>
```

## 4. Upload the certificate

On the same Integration record, open the **Certificates** subtab and upload
`netsuite-cert.pem` from step 2.

NetSuite assigns a **Certificate ID** once uploaded — this maps to:

```
NETSUITE_CERTIFICATE_ID=<Certificate ID>
```

The Certificate ID must match the JWT's `kid` (key ID) header claim — this
client sets that automatically from `NETSUITE_CERTIFICATE_ID`, so no manual JWT
construction is needed.

## 5. Grant role permissions

Whichever role/user this Integration record's Client Credentials grant runs as
needs REST Web Services login access plus standard View/Create/Edit/Full
permissions for every record type this client will touch, same as any other
NetSuite REST consumer.

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

Certificates uploaded to the Integration record are valid for up to 2 years
(matching the `-days 730` used above). Rotation is manual: generate a new
keypair, upload the new certificate (NetSuite will assign a new Certificate
ID), update `NETSUITE_CERTIFICATE_ID`/`NETSUITE_PRIVATE_KEY_PATH`, then remove
the old certificate from the Integration record.

## Troubleshooting

- **Token request fails / `NetSuiteAuthException` from `TokenManager`** — check
  that `NETSUITE_CLIENT_ID` and `NETSUITE_CERTIFICATE_ID` both belong to the same
  Integration record, and that the certificate hasn't expired or been removed.
- **This method is unverified against a live NetSuite account** — the JWT claim
  shapes, `kid` requirement, and token endpoint path are built from NetSuite's
  documented OAuth 2.0 M2M spec, but if something doesn't work end-to-end, that's
  the most likely place to start debugging. If you get this working against a
  real account, it'd help the project to note what (if anything) differed from
  this guide.
