# JDM Kenya OAuth 2.0 provider

JDM Kenya is the external identity provider for Gacio Sports. It implements OAuth 2.0 Authorization Code Flow with mandatory PKCE `S256` using `league/oauth2-server` 9.4. Gacio is an independent confidential client. The applications do not share databases, cookies, sessions, credentials, password hashes, reset tokens, or signing keys.

## Endpoints

| Endpoint | Responsibility |
|---|---|
| `GET/POST /oauth/authorize.php` | Validate the exact registered client and redirect, require JDM login, active/approved status, PKCE, and CSRF-protected consent; issue a 90-second single-use code. |
| `POST /oauth/token.php` | Authenticate the client, rate limit, serialize code exchange, verify the PKCE verifier, and issue a five-minute signed bearer token. |
| `GET /oauth/userinfo.php` | Validate bearer signature, expiry, revocation, client, and current account eligibility; return minimal identity claims. |

The protected identity response contains only `iss`, `aud`, opaque UUID `sub`, `name`, `email`, `email_verified`, and `account_active`. It does not expose passwords, password hashes, phones, addresses, roles, approval notes, or session data. OAuth audit events omit credentials, codes, tokens, and personal claim values; network identifiers are HMAC-pseudonymized.

## Storage and controls

Apply `database/migrations/oauth/20260827_oauth2_sso.sql` during an approved maintenance window. It adds an immutable opaque subject and explicit verification/status fields to JDM users, plus separate OAuth client, authorization-code, access-token, audit, and rate-limit tables. Code and token identifiers and client secrets are stored only as one-way digests. Existing email addresses remain unverified until an evidence-backed process marks them verified.

Authorization codes expire after 90 seconds and are single use. Access tokens expire after five minutes. Redirect URIs are exact per-client values, not wildcard or prefix matches. The provider rejects plain or missing PKCE, disabled/revoked clients, ineligible users, expired/replayed codes, revoked tokens, and flooding. Browser consent writes use the existing JDM CSRF control. Provider errors are safe and logs record exception classes rather than secrets.

## Environment

```dotenv
JDM_OAUTH_ENABLED=false
JDM_OAUTH_ISSUER=https://verified-jdm-origin.example
JDM_OAUTH_PRIVATE_KEY_PATH=/secure/outside-web-root/jdm-oauth-private.key
JDM_OAUTH_PUBLIC_KEY_PATH=/secure/outside-web-root/jdm-oauth-public.key
JDM_OAUTH_ENCRYPTION_KEY=
JDM_OAUTH_AUDIT_SALT=
JDM_OAUTH_ALLOW_INSECURE_LOCAL=false
JDM_SESSION_COOKIE=jdm_kenya_session
```

Production requires PHP 8.2+, OpenSSL, JSON, native Sodium, trusted CA certificates, and keys outside every served document root. `JDM_OAUTH_ALLOW_INSECURE_LOCAL=true` permits loopback HTTP and the installed compatibility implementation for isolated local tests only; it must be false in production.

Generate RSA keys with `oauth/bin/generate-keys.php`, register the exact Gacio callback with `oauth/bin/register-client.php`, and capture the printed client secret once into the secret manager. Revoke a client and its outstanding grants with `oauth/bin/revoke-client.php`. Never commit generated keys or secrets.

## Deployment and verification

1. Back up the JDM database and verify restore in a non-production environment.
2. Deploy dependencies and provider code together with the user-status-aware login change during maintenance, then apply the OAuth migration before serving requests.
3. Configure keys and secrets, register Gacio, and leave `JDM_OAUTH_ENABLED=false` until checks pass.
4. Run Composer platform checks/audit, PHPUnit, the legacy sports test, and endpoint smoke tests against a dedicated test database.
5. Enable the provider, then enable the Gacio client. Exercise success, consent denial, wrong redirect/client, state and PKCE failures, replay, expiry, disabled/unverified users, rate limits, revocation, and local-only Gacio logout.

The production issuer/domain, cPanel document roots and environment management, native Sodium availability, proxy/TLS topology, key owners, and existing-account email-verification operation remain external deployment gates. The local library-level integration tests do not constitute a production compatibility claim.
