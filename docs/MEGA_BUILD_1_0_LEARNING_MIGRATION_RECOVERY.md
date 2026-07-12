# MEGA BUILD 1.0 — Learning, Human Approval, Migration e Recovery

## Auditoria

Antes deste pacote foi realizada busca no repositório por componentes equivalentes de Learning Manager, Human-in-the-loop, Migration Engine, Backup/Restore, Auto Healing e Predictive Monitoring. Não foram encontrados módulos equivalentes. O pacote foi criado sem duplicar os componentes já homologados de auditoria, compliance, locks, quotas, telemetry, DLQ e Event Bus.

## Learning Manager

Responsável por transformar feedback humano em regras e sinais reutilizáveis.

Entradas:
- aprovação;
- rejeição;
- edição;
- correção;
- nota;
- motivo;
- resultado final.

Saídas:
- LEARNING_SIGNAL_RECORDED;
- LEARNING_PATTERN_DETECTED;
- LEARNING_RULE_PROPOSED;
- LEARNING_RULE_APPROVED;
- LEARNING_RULE_REJECTED.

Nenhuma regra aprendida entra em produção sem feature flag e aprovação humana.

## Human-in-the-loop

Decisões críticas devem parar em estado `waiting_human`.

Casos obrigatórios:
- publicação editorial sensível;
- aprovação de roteiro;
- envio de proposta comercial;
- alteração irreversível de infraestrutura;
- restore de produção;
- exclusão ou anonimização LGPD;
- ultrapassagem de orçamento.

Contratos esperados no Master:

```text
POST /api/flow/approvals/request
GET  /api/flow/approvals/{uuid}
POST /api/flow/approvals/{uuid}/decision
```

## Migration Engine

Orquestra migrações de aplicação, banco, configuração e storage.

Estados:
- planned;
- validating;
- snapshotting;
- migrating;
- verifying;
- completed;
- failed;
- rolling_back;
- rolled_back.

## Backup e Restore

Backups devem possuir checksum, retenção, escopo, company_id e criptografia.

Escopos:
- database;
- files;
- volumes;
- configuration;
- workflow_registry;
- secrets_metadata.

## Auto Healing

Ações permitidas:
- restart de serviço;
- retry controlado;
- limpeza segura de cache;
- renovação SSL;
- reprocessamento de fila;
- alternância para provider de fallback.

Ações destrutivas exigem aprovação humana.

## Predictive Monitoring

Analisa tendência de:
- latência;
- falhas;
- CPU;
- memória;
- disco;
- filas;
- custos;
- expiração SSL;
- consumo de quotas.

Emite:
- PREDICTIVE_RISK_DETECTED;
- CAPACITY_WARNING;
- COST_ANOMALY_DETECTED;
- FAILURE_PROBABILITY_HIGH.
