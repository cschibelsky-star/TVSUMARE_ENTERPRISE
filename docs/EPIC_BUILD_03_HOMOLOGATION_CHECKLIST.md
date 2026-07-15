# EPIC BUILD 03 — Checklist de Homologação do Runtime Engine

## Pré-requisitos

- [ ] n8n ativo no ambiente de homologação.
- [ ] `VITRINE_MASTER_API_URL` configurada.
- [ ] `VITRINE_MASTER_API_TOKEN` configurado.
- [ ] Endpoints de locks disponíveis.
- [ ] Endpoints de usage disponíveis.
- [ ] Endpoints de telemetry disponíveis.
- [ ] Endpoint de callback disponível.
- [ ] DLQ disponível.

## Cenário 1 — Execução concluída

- [ ] Runtime inicia execução.
- [ ] Child workflows são disparados.
- [ ] Checkpoints são persistidos.
- [ ] Próxima camada é calculada.
- [ ] Resultado é agregado.
- [ ] Lock principal é liberado.
- [ ] Telemetria final é registrada.
- [ ] `WORKFLOW_FINALIZED` é recebido pelo Mission Control.

## Cenário 2 — Falha com retry

- [ ] Child workflow falha.
- [ ] Retry respeita política da fila.
- [ ] Checkpoint anterior permanece válido.
- [ ] Execução retoma sem repetir etapa concluída.
- [ ] Excesso de tentativas vai para DLQ.

## Cenário 3 — Cancelamento

- [ ] Pedido de cancelamento é idempotente.
- [ ] Novos child workflows deixam de iniciar.
- [ ] Etapa não interrompível finaliza com segurança.
- [ ] Compensações são executadas em ordem inversa.
- [ ] Locks são liberados.
- [ ] Reservas não consumidas são liberadas.
- [ ] Estado final é `cancelled` ou `compensated`.

## Cenário 4 — Rollback

- [ ] Rollback parte do último checkpoint seguro.
- [ ] Cada compensação gera telemetria.
- [ ] Falha de compensação vai para DLQ.
- [ ] Estado final é `rolled_back` ou `compensated`.

## Cenário 5 — Concorrência e duplicidade

- [ ] Mesmo `request_id` não gera segunda execução.
- [ ] Mesmo `execution_id` não é finalizado duas vezes.
- [ ] Duas requisições de cancelamento não duplicam compensações.
- [ ] Lock por `company_id + execution_id` funciona.

## Critérios de aprovação

O EPIC BUILD 03 será considerado homologado quando:

1. Todos os cinco cenários forem aprovados.
2. Nenhum lock permanecer órfão.
3. Nenhuma reserva permanecer ativa após encerramento.
4. Todas as execuções possuírem telemetria terminal.
5. Mission Control refletir o estado final correto.
6. DLQ receber falhas irrecuperáveis.
7. Retomada automática não repetir efeitos irreversíveis.
