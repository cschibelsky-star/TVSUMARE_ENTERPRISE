# EPIC BUILD 03 — Runtime Engine Enterprise Closure

## Auditoria

Antes deste pacote foi realizada busca no repositório por componentes de cancelamento, encerramento, compensação automática, liberação de locks e homologação do Runtime. Não foram encontrados módulos equivalentes completos. Este pacote integra os componentes já existentes de checkpoints, resume, rollback, DLQ, telemetria e locks, criando apenas o que estava ausente.

## Objetivo

Fechar o Runtime Engine Enterprise com:

- cancelamento controlado;
- encerramento definitivo;
- compensação automática;
- liberação de locks e reservas;
- consolidação final;
- telemetria de encerramento;
- checklist de homologação.

## Estados finais oficiais

- completed
- failed
- cancelled
- compensated
- rolled_back
- finished

## Contratos esperados no Mission Control

```text
POST /api/flow/runtime/cancel
POST /api/flow/runtime/finalize
POST /api/flow/runtime/compensate
POST /api/flow/locks/release
POST /api/flow/usage/release
POST /api/flow/telemetry
POST /api/flow/events/callback
```

## Regras de cancelamento

1. Validar `company_id`, `execution_id`, `workflow_uuid` e `request_id`.
2. Adquirir lock de cancelamento.
3. Impedir novos child workflows.
4. Aguardar etapa não interrompível, quando aplicável.
5. Executar compensações configuradas.
6. Liberar locks e reservas.
7. Emitir `WORKFLOW_CANCELLED`.
8. Finalizar execução como `cancelled` ou `compensated`.

## Regras de encerramento

O encerramento deve ser idempotente. Uma execução finalizada não pode ser encerrada novamente com efeitos colaterais.

O Finalizer deve:

- agregar resultados dos filhos;
- calcular duração, retries, custos e SLA;
- registrar estado final;
- liberar lock principal;
- liberar reservas remanescentes;
- emitir telemetria final;
- enviar callback ao Mission Control.

## Eventos oficiais

- WORKFLOW_CANCEL_REQUESTED
- WORKFLOW_CANCELLING
- WORKFLOW_CANCELLED
- WORKFLOW_COMPENSATION_STARTED
- WORKFLOW_COMPENSATION_COMPLETED
- WORKFLOW_COMPENSATION_FAILED
- WORKFLOW_FINALIZING
- WORKFLOW_FINALIZED
- WORKFLOW_LOCKS_RELEASED
- WORKFLOW_RESERVATIONS_RELEASED

## Critérios de aceite

- cancelamento idempotente;
- nenhum novo child é iniciado após pedido de cancelamento;
- compensação executada em ordem inversa;
- locks sempre liberados em sucesso, falha ou cancelamento;
- reservas de uso liberadas quando não consumidas;
- telemetria final registrada;
- execução aparece no Mission Control com estado terminal;
- falha de compensação enviada à DLQ;
- retomada não reabre execução finalizada.
