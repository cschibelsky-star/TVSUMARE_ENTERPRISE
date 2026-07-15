# Vitrine IA Flow — Observabilidade Enterprise

## Princípio

O Mission Control não acessará diretamente o banco interno do n8n. Toda telemetria será enviada pela Vitrine IA Flow para APIs Laravel padronizadas.

## Dimensões observadas

- workflow_id
- workflow_version
- execution_id
- request_id
- product_code
- tenant_id
- queue
- event
- status
- step
- attempts
- duration_ms
- started_at
- finished_at
- error_code
- error_message

## Estados oficiais de execução

- accepted
- queued
- running
- waiting_human_approval
- retry_scheduled
- completed
- failed
- dead_lettered
- cancelled

## Eventos de observabilidade

- WORKFLOW_ACCEPTED
- WORKFLOW_QUEUED
- WORKFLOW_STARTED
- WORKFLOW_STEP_STARTED
- WORKFLOW_STEP_COMPLETED
- WORKFLOW_WAITING_APPROVAL
- WORKFLOW_RETRY_SCHEDULED
- WORKFLOW_COMPLETED
- WORKFLOW_FAILED
- WORKFLOW_SENT_TO_DLQ
- WORKFLOW_CANCELLED

## Payload padrão

```json
{
  "workflow_id": "WF-000006",
  "workflow_version": "1.0.0",
  "execution_id": "EXEC-20260711-000001",
  "request_id": "uuid",
  "product_code": "tv_digital_enterprise",
  "tenant_id": "cliente_123",
  "queue": "Provision Queue",
  "event": "WORKFLOW_STEP_COMPLETED",
  "status": "running",
  "step": "provision_database",
  "attempts": 1,
  "duration_ms": 1842,
  "started_at": "2026-07-11T13:00:00-03:00",
  "finished_at": "2026-07-11T13:00:01-03:00",
  "metadata": {}
}
```

## Endpoints esperados no Laravel

```text
POST /api/flow/executions/events
POST /api/flow/executions/heartbeat
POST /api/flow/executions/metrics
POST /api/flow/dlq/events
```

## Métricas mínimas

- execuções por workflow
- taxa de sucesso
- taxa de falha
- tempo médio
- p95 de duração
- tamanho da fila
- quantidade de retries
- quantidade em DLQ
- disponibilidade dos webhooks
- saúde dos serviços externos

## Heartbeat

A Vitrine IA Flow enviará heartbeat periódico contendo:

```json
{
  "service": "vitrine-ia-flow",
  "status": "healthy",
  "timestamp": "2026-07-11T13:00:00-03:00",
  "active_executions": 4,
  "queued_executions": 12,
  "failed_last_hour": 1,
  "version": "1.0.0"
}
```
