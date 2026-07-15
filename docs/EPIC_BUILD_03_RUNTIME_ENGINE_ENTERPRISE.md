# EPIC BUILD 03 — Runtime Engine Enterprise

## Auditoria

Antes desta build foram auditados os componentes já existentes: Workflow Registry, Event Bus, Locks, Quotas, Usage, Feature Flags, Telemetry, DLQ, Checkpoints, Resume, Rollback, Dependency Graph e Child Workflow Runtime.

Esta build não duplica esses componentes. Ela os integra em um único Runtime Engine.

## Objetivo

Executar workflows compostos de forma padronizada, multi-company, observável, idempotente e recuperável.

## Identificador oficial de tenant

`company_id`

Não existe `tenant_id` na plataforma.

## Fluxo do Runtime

```text
EVENT_RECEIVED
  ↓
RUNTIME_VALIDATING
  ↓
RUNTIME_CONTEXT_READY
  ↓
DEPENDENCY_GRAPH_RESOLVED
  ↓
CHILDREN_DISPATCHED
  ↓
CHECKPOINTS_COMMITTED
  ↓
RESULT_AGGREGATED
  ↓
WORKFLOW_COMPLETED | WORKFLOW_FAILED
  ↓
COMPENSATION | ROLLBACK quando necessário
```

## Estados oficiais

- pending
- validating
- waiting
- running
- checkpoint
- partially_completed
- completed
- failed
- compensating
- compensated
- rollback
- rolled_back
- cancelled
- finished

## APIs esperadas no Mission Control

```text
POST /api/flow/runtime/start
POST /api/flow/runtime/resume
POST /api/flow/runtime/cancel
POST /api/flow/runtime/rollback
GET  /api/flow/runtime/status/{execution_uuid}
GET  /api/flow/runtime/checkpoints/{execution_uuid}
GET  /api/flow/runtime/result/{execution_uuid}
POST /api/flow/runtime/children/dispatch
POST /api/flow/runtime/result/commit
```

## Eventos emitidos

- WORKFLOW_STARTED
- WORKFLOW_VALIDATING
- WORKFLOW_RUNNING
- WORKFLOW_WAITING
- WORKFLOW_CHILD_STARTED
- WORKFLOW_CHILD_COMPLETED
- WORKFLOW_CHILD_FAILED
- WORKFLOW_CHECKPOINT_COMMITTED
- WORKFLOW_LAYER_COMPLETED
- WORKFLOW_RESUMED
- WORKFLOW_COMPLETED
- WORKFLOW_FAILED
- WORKFLOW_COMPENSATION_STARTED
- WORKFLOW_COMPENSATED
- WORKFLOW_ROLLBACK_STARTED
- WORKFLOW_ROLLED_BACK
- WORKFLOW_FINISHED

## Critérios de aceite

- Nenhuma execução usa acesso direto ao banco do n8n.
- Toda execução possui `company_id`, `workflow_uuid`, `execution_uuid`, `request_id` e `correlation_id`.
- Toda etapa concluída gera checkpoint.
- Reexecução com o mesmo `request_id` não duplica efeitos.
- Dependências são respeitadas.
- Nós independentes podem executar em paralelo.
- Falhas podem disparar compensação em ordem inversa.
- Resultado consolidado é enviado ao Mission Control.
