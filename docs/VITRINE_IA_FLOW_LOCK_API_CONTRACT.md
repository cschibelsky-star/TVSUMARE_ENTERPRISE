# Vitrine IA Flow — Contrato de Lock Distribuído

## Objetivo

Evitar execuções concorrentes sobre o mesmo tenant, domínio, projeto, banco, conta social ou outro recurso crítico.

## Adquirir lock

```http
POST /api/flow/locks/acquire
```

Payload:

```json
{
  "request_id": "uuid",
  "tenant_id": "tenant_123",
  "workflow_id": "WF-000006",
  "resource_type": "domain",
  "resource_key": "cliente.com.br",
  "ttl_seconds": 900,
  "owner_execution_id": "exec_456"
}
```

Resposta de sucesso:

```json
{
  "ok": true,
  "lock_id": "lock_789",
  "status": "acquired",
  "expires_at": "2026-07-11T13:15:00-03:00"
}
```

Resposta de conflito:

```json
{
  "ok": false,
  "status": "locked",
  "error_code": "RESOURCE_ALREADY_LOCKED",
  "retry_after_seconds": 60
}
```

## Renovar lock

```http
POST /api/flow/locks/heartbeat
```

## Liberar lock

```http
POST /api/flow/locks/release
```

## Regras

- O n8n deve adquirir o lock antes de iniciar uma etapa crítica.
- O lock deve ser renovado durante execuções longas.
- O lock deve ser liberado no sucesso, falha controlada ou cancelamento.
- O Mission Control é a fonte de verdade dos locks.
- O banco interno do n8n nunca deve ser usado como registry de locks.
