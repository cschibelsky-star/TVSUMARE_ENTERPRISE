# MEGA BUILD 1.0 — AI Managers, Factory Lifecycle e Observability Analytics

## Auditoria

Antes desta entrega foi executada auditoria no repositório para localizar implementações existentes de Memory Manager, Context Manager, Skill Manager, Installer Engine, Upgrade Engine, SLA Engine, Alert Engine e Cost Analytics. Não foram encontrados componentes equivalentes. A implementação abaixo complementa os módulos existentes sem duplicar Event Bus, Quotas, Usage, Telemetry, DLQ, Feature Flags ou Auditoria.

## Escopo

Este pacote fecha três blocos do MEGA BUILD 1.0:

1. AI Operating System
   - Memory Manager
   - Context Manager
   - Skill Manager
2. Factory Lifecycle
   - Installer Engine
   - Upgrade Engine
3. Observability Analytics
   - SLA Engine
   - Alert Engine
   - Cost Analytics

## Regras arquiteturais

- Company == Tenant; usar sempre `company_id`.
- Nenhum workflow conhece produto específico.
- Toda persistência e governança pertencem ao Centro Operacional Master.
- A Flow executa via REST/Webhook e emite eventos.
- Secrets não ficam em workflows.
- Custos usam Quota, Usage Reservation e Telemetry existentes.

## Contratos esperados no Master

```text
POST /api/flow/ai/context/resolve
POST /api/flow/ai/memory/query
POST /api/flow/ai/memory/record
POST /api/flow/ai/skills/resolve
POST /api/flow/factory/installations/start
POST /api/flow/factory/installations/step
POST /api/flow/factory/upgrades/start
POST /api/flow/factory/upgrades/step
POST /api/flow/observability/sla/evaluate
POST /api/flow/observability/alerts
POST /api/flow/observability/costs/aggregate
```

## Eventos oficiais

```text
AI_CONTEXT_RESOLVED
AI_MEMORY_QUERIED
AI_MEMORY_RECORDED
AI_SKILLS_RESOLVED
INSTALLATION_STARTED
INSTALLATION_STEP_COMPLETED
INSTALLATION_COMPLETED
INSTALLATION_FAILED
UPGRADE_STARTED
UPGRADE_STEP_COMPLETED
UPGRADE_COMPLETED
UPGRADE_FAILED
SLA_EVALUATED
SLA_BREACHED
ALERT_CREATED
ALERT_RESOLVED
COST_AGGREGATED
BUDGET_WARNING
BUDGET_EXCEEDED
```

## Critérios de homologação

- contexto resolvido por company_id;
- memória consultada e gravada por contrato;
- skills resolvidas sem referência a produto;
- instalação e upgrade idempotentes;
- rollback em falha crítica;
- SLA calculado com telemetria real;
- alertas deduplicados por fingerprint;
- custos agregados por company, provider, workflow e período;
- callbacks enviados ao Mission Control;
- falhas irrecuperáveis encaminhadas à DLQ.
