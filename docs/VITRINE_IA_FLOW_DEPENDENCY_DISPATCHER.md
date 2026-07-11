# Vitrine IA Flow — Dependency Dispatcher e Checkpoint Commit

## Objetivo

Definir a execução operacional do grafo de dependências de workflows compostos.

O Dependency Dispatcher recebe um plano de execução, consulta checkpoints existentes, identifica nós elegíveis e dispara child workflows em sequência ou paralelo.

## Regras

Um nó só pode ser executado quando:

- todas as dependências estiverem concluídas;
- não existir checkpoint final para o mesmo nó e execução;
- o lock do recurso tiver sido adquirido;
- a feature estiver habilitada para o tenant;
- a cota necessária estiver aprovada;
- o circuit breaker do provider estiver fechado.

## Estados dos nós

- pending
- ready
- running
- waiting_dependency
- completed
- failed
- skipped
- compensating
- compensated

## Checkpoint commit

Ao concluir um child workflow, a Flow deve enviar:

```text
POST /api/flow/checkpoints/commit
```

Payload:

```json
{
  "workflow_id": "WF-000006",
  "execution_id": "EXEC-20260711-001",
  "child_workflow_id": "WF-CHILD-003",
  "node_id": "provision_docker",
  "tenant_id": "tenant_123",
  "request_id": "uuid",
  "status": "completed",
  "output": {},
  "committed_at": "2026-07-11T16:10:00-03:00"
}
```

## Dispatch paralelo

Nós sem dependência entre si podem ser executados em paralelo.

Exemplo:

```text
Create Database ─┐
Create Storage  ─┼─> Provision Docker
Create DNS      ─┘
```

## Dispatch sequencial

Nós encadeados devem respeitar a ordem declarada no grafo.

## Falha

Quando um nó falhar:

1. registrar checkpoint de falha;
2. aplicar retry policy;
3. suspender nós dependentes;
4. emitir `CHILD_WORKFLOW_FAILED`;
5. disparar compensação quando configurada;
6. enviar telemetria ao Mission Control.

## Retomada

Na retomada, o Dispatcher deve ignorar nós concluídos e iniciar apenas os nós `ready` ainda pendentes.
