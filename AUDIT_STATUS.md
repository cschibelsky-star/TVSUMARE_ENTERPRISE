# Estado tecnico auditado

## Situacao atual

- Baseline PHP legado importado de snapshot imutavel da HostGator.
- 97 arquivos PHP validados no PHP 8.3, sem erros de sintaxe.
- Runtime Apache/PHP 8.3 versionado.
- Docker Compose validado e container local saudavel.
- Dados, uploads, videos e logs separados em /srv/tvsumare/shared.
- Configuracoes sensiveis substituidas por variaveis de ambiente.
- Health check disponivel em /health.php.
- Rede Docker interna, sem publicacao externa.
- DNS e producao inalterados.

## Classificacao oficial

HOMOLOGATION_LOCAL

O baseline esta funcional e isolado, mas ainda nao foi publicado em dominio de homologacao nem validado para producao.

## Security hardening

- /admin and /api blocked at HTTP layer.
- Sensitive internal artifacts return HTTP 403.
- Missing require_login() fixed in admin/aovivo.php.
- Security headers validated.
- Unauthorized POST returned 403 without changing persistent data.
- Public routes remain HTTP 200.
- Publication is pending DNS, certificate and explicit authorization.

## CSRF validation

- 256-bit session token enabled.
- SameSite=Strict enabled for admin sessions.
- 94 POST forms across 32 files protected.
- Invalid login token redirects to erro=csrf.
- Valid token with invalid credentials redirects to erro=credentials.
- Invalid authenticated POST returns HTTP 419 and leaves data unchanged.

## Outbound validation

- Legacy HeyGen credentials removed from homologation.
- Server-side requests restricted by allowlist and public-IP validation.
- TLS verification enabled; automatic redirects disabled.
- SSRF negative tests passed.
- Public routes remain HTTP 200 and all administrative surfaces remain HTTP 403.

## Runtime hardening

- Read-only container root filesystem enabled.
- no-new-privileges enabled.
- Only NET_BIND_SERVICE, SETGID and SETUID capabilities retained.
- PID, memory, CPU and open-file limits enabled.
- Upload and video script execution blocked by immutable Apache configuration.
- Persistent data remains writable; application root writes are blocked.
- Runtime tests passed with zero restarts and no permission errors.
