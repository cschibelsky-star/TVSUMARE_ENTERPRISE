# Vitrine IA Flow — Workflow Composer, Saga e Rollback

## Objetivo

Definir o padrão operacional de workflows compostos na Vitrine IA Flow.

Um workflow composto é formado por child workflows independentes, versionados, idempotentes e observáveis.

## Contexto obrigatório

```json
{
  "tenant_id": "tenant_123",
  "product_code": "tv_digital_enterprise",
  "workflow_id": "WF-000006",
  "execution_id": "EXEC-20260711-001",
  "request_id": "uuid",
  "correlation_id": "CORR-001",
  "parent_workflow_id": null,
  "plan_code": "enterprise",
  "feature_flags": [],
  "payload": {}
}
```

## Estados do workflow composto

- pending
- running
- waiting_dependency
- partially_completed
- compensating
- rolled_back
- completed
- failed

## Regras dos child workflows

Cada child workflow deve:

- possuir ID e versão próprios;
- declarar dependências;
- emitir eventos de início, sucesso e falha;
- registrar checkpoint ao concluir;
- possuir retry e timeout próprios;
- ser idempotente;
- poder ser executado isoladamente;
- declarar ação de compensação quando aplicável.

## Eventos oficiais

- COMPOSITE_WORKFLOW_STARTED
- CHILD_WORKFLOW_STARTED
- CHILD_WORKFLOW_COMPLETED
- CHILD_WORKFLOW_FAILED
- CHECKPOINT_RECORDED
- COMPENSATION_REQUESTED
- COMPENSATION_STARTED
- COMPENSATION_COMPLETED
- COMPENSATION_FAILED
- ROLLBACK_REQUESTED
- ROLLBACK_STARTED
- ROLLBACK_COMPLETED
- ROLLBACK_FAILED
- COMPOSITE_WORKFLOW_COMPLETED
- COMPOSITE_WORKFLOW_FAILED

## Saga Pattern

Quando um child workflow falhar após etapas anteriores concluídas, o Composer executará compensações na ordem inversa.

Exemplo:

```text
Provision Database     ✓
Provision Storage      ✓
Provision Docker       ✓
Configure Domain       ✖

Compensação:
Remove Docker
Remove Storage
Drop Database
Release License
Release Quota
Release Locks
```

## Checkpoints

Checkpoint mínimo:

```json
{
  "workflow_id": "WF-000006",
  "execution_id": "EXEC-20260711-001",
  "child_workflow_id": "WF-CHILD-0003",
  "checkpoint": "docker_ready",
  "status": "completed",
  "timestamp": "2026-07-11T15:32:00-03:00",
  "metadata": {}
}
```

## APIs esperadas no Mission Control

```text
POST /api/flow/executions/checkpoint
POST /api/flow/executions/child-event
POST /api/flow/executions/compensation
POST /api/flow/executions/rollback
GET  /api/flow/executions/{execution_id}/state
```

O n8n nunca acessa diretamente o banco Laravel ou o banco interno do n8n para compartilhar estado.
