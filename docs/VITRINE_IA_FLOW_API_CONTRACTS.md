# Vitrine IA Flow — Contratos de API e Eventos

## Padrão geral

Todas as chamadas entre Laravel e n8n devem seguir REST + Webhook.

Headers obrigatórios:

```http
Authorization: Bearer {TOKEN}
Content-Type: application/json
X-Vitrine-Product: {product_code}
X-Vitrine-Event: {event_name}
X-Vitrine-Request-Id: {uuid}
```

## Payload base

```json
{
  "event": "ORDER_PAID",
  "product_code": "tv_digital_enterprise",
  "tenant_id": "cliente_123",
  "request_id": "uuid",
  "timestamp": "2026-07-07T00:00:00-03:00",
  "payload": {}
}
```

## Eventos oficiais iniciais

### Comercial / Vendas

- LEAD_CREATED
- PROPOSAL_SENT
- ORDER_CREATED
- ORDER_PAID
- ORDER_CANCELLED

### Provisionamento

- LICENSE_CREATED
- PROVISION_REQUESTED
- PROVISION_STARTED
- PROVISION_DOCKER_READY
- PROVISION_DATABASE_READY
- PROVISION_DOMAIN_READY
- PROVISION_SSL_READY
- PROVISION_COMPLETED
- PROVISION_FAILED

### Deploy

- DEPLOY_REQUESTED
- DEPLOY_STARTED
- DEPLOY_COMPLETED
- DEPLOY_FAILED
- ROLLBACK_REQUESTED
- ROLLBACK_COMPLETED

### IA

- AI_JOB_REQUESTED
- AI_JOB_COMPLETED
- AI_JOB_FAILED

### Monitoramento

- HEALTH_CHECK_REQUESTED
- HEALTH_CHECK_COMPLETED
- SERVICE_DOWN
- SERVICE_RECOVERED

### Notificação

- NOTIFICATION_REQUESTED
- NOTIFICATION_SENT
- NOTIFICATION_FAILED

## Webhook Laravel → n8n

Endpoint n8n:

```text
POST /webhook/vitrine-flow/{workflow_slug}
```

## Callback n8n → Laravel

Endpoint Laravel:

```text
POST /api/flow/events/callback
```

## Resposta padrão

```json
{
  "ok": true,
  "workflow_id": "wf_123",
  "execution_id": "exec_456",
  "status": "accepted",
  "message": "Workflow recebido para processamento."
}
```

## Erro padrão

```json
{
  "ok": false,
  "status": "failed",
  "error_code": "INVALID_PAYLOAD",
  "message": "Payload inválido."
}
```
