# Security review - isolated homologation

Date: 2026-08-03

## Findings addressed

- Added missing require_login() to admin/aovivo.php.
- Blocked HTTP access to /admin and /api while homologation credentials are unset.
- Blocked direct access to internal app, routes, config, data, vendor and logs paths.
- Blocked delivery of Docker, manifest, Markdown, YAML, Python, ZIP and log artifacts.
- Added nosniff, same-origin framing, referrer and permissions headers.
- Confirmed HeyGen callback fails closed when its token is absent.
- Confirmed empty admin credentials cannot authenticate.

## Remaining before proxy publication

- Add a shared CSRF token mechanism to all administrative POST forms.
- Configure dedicated homologation credentials and callback secrets.
- Review outbound integration allowlists and timeouts.
- Re-enable /admin and /api selectively only after those controls pass.

## CSRF hardening

- Session-bound 256-bit CSRF token added.
- SameSite cookie policy changed to Strict.
- Login POST validates CSRF before credentials.
- Every authenticated POST fails with HTTP 419 when the token is absent or invalid.
- Hidden CSRF fields added to all legacy POST forms.

## Outbound and credential hardening

- Removed legacy HeyGen API key and callback token from homologation data.
- Removed hardcoded callback token from source.
- Immutable HostGator snapshot remains the only retained legacy source.
- Added exact HTTPS hostname allowlist and public IPv4 validation.
- Added DNS pinning, TLS verification, timeouts and response limits.
- Disabled automatic server-side redirects.
- Blocked duplicated root-level administrative endpoints.
- Negative SSRF tests passed for HTTP, loopback, localhost, unlisted hosts, custom ports and URL userinfo.
