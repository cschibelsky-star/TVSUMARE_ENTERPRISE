# Vitrine IA Flow — Checkpoints, Resume e Child Workflow Runtime

## Objetivo

Definir a execução real de child workflows, persistência de checkpoints e retomada automática após falhas.

## Princípios

- Todo child workflow deve ser idempotente.
- Todo child workflow deve registrar início, sucesso, falha e checkpoint.
- O estado oficial pertence ao Centro Operacional Master.
- O n8n nunca consulta diretamente o banco do Laravel.
- Toda persistência ocorre por REST API.

## Contexto obrigatório

```json
{
  "workflow_id": "WF-000006",
  "workflow_version": "1.0.0",
  "execution_id": "EXEC-20260711-001",
  "parent_execution_id": null,
  "child_workflow_id": "WF-CHILD-0001",
  "tenant_id": "tenant_123",
  "product_code": "tv_digital_enterprise",
  "request_id": "uuid",
  "correlation_id": "uuid",
  "attempt": 1,
  "payload": {}
}
```

## Contratos de checkpoint

### Criar checkpoint

```text
POST /api/flow/checkpoints
```

Payload:

```json
{
  "execution_id": "EXEC-20260711-001",
  "workflow_id": "WF-000006",
  "child_workflow_id": "WF-CHILD-0001",
  "checkpoint_key": "database_ready",
  "status": "completed",
  "output": {
    "database_name": "tenant_123_db"
  },
  "created_at": "2026-07-11T16:00:00-03:00"
}
```

### Consultar último checkpoint

```text
GET /api/flow/executions/{execution_id}/checkpoints/latest
```

### Listar checkpoints

```text
GET /api/flow/executions/{execution_id}/checkpoints
```

## Resume

A retomada deve seguir:

1. Receber `WORKFLOW_RESUME_REQUESTED`.
2. Validar idempotência.
3. Consultar último checkpoint confirmado.
4. Reconstituir o execution context.
5. Ignorar child workflows já concluídos.
6. Reiniciar do primeiro child pendente.
7. Emitir `WORKFLOW_RESUMED`.
8. Continuar a saga.

## Estados adicionais

- resume_requested
- resuming
- resumed
- resume_failed
- checkpoint_pending
- checkpoint_committed
- checkpoint_failed

## Eventos

- CHILD_WORKFLOW_STARTED
- CHILD_WORKFLOW_COMPLETED
- CHILD_WORKFLOW_FAILED
- CHECKPOINT_CREATED
- CHECKPOINT_COMMITTED
- CHECKPOINT_FAILED
- WORKFLOW_RESUME_REQUESTED
- WORKFLOW_RESUMED
- WORKFLOW_RESUME_FAILED

## Regra de segurança

Nenhum resume deve repetir uma etapa irreversível já confirmada. Para isso, o child workflow deve declarar `idempotency_scope`, `checkpoint_key` e `compensation_workflow_id`.
