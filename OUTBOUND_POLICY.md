# Outbound network policy

The homologation container remains on an internal Docker network with no egress.

If egress is enabled later, server-side HTTP requests must use includes/outbound_guard.php.

Controls:

- HTTPS and port 443 only
- Exact hostname allowlist
- Public IPv4 resolution only
- DNS result pinned into cURL
- TLS peer and hostname verification
- No automatic redirects
- Connection and total timeouts
- Two-megabyte response limit in RSS and monitor fetchers
- Generic errors do not log URLs, tokens or upstream response bodies

Default server-side allowlist:

- generativelanguage.googleapis.com
- api.heygen.com
- api.anthropic.com
- news.google.com
- agenciabrasil.ebc.com.br
- www.saopaulo.sp.gov.br
- portaldesumare.com.br
- sumare.sp.gov.br
- g1.globo.com
- www.bing.com
- search.yahoo.com

Additional hosts require explicit TVSUMARE_OUTBOUND_HOSTS configuration and a security review.
