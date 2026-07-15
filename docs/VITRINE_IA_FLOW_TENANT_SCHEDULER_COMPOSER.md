# Vitrine IA Flow — Tenant Manager, Scheduler Enterprise e Workflow Composer

## Tenant Manager

Toda execução deve ser multi-tenant e receber contexto mínimo obrigatório:

```json
{
  "tenant_id": "tenant_123",
  "product_code": "tv_digital_enterprise",
  "plan_code": "enterprise",
  "region": "br-sudeste",
  "timezone": "America/Sao_Paulo",
  "locale": "pt-BR",
  "features": [],
  "quotas": {},
  "metadata": {}
}
```

O n8n não consulta diretamente banco de clientes. O contexto do tenant é obtido via API do Centro Operacional Master.

## Contratos do Tenant Manager

```text
GET /api/flow/tenants/{tenant_id}/context
POST /api/flow/tenants/{tenant_id}/validate
POST /api/flow/tenants/{tenant_id}/feature-check
```

## Scheduler Enterprise

O Scheduler Enterprise deve ser controlado pelo Laravel e executado pelo n8n.

Deve suportar:

- timezone por tenant;
- recorrência;
- janela de execução;
- feriados;
- prioridade;
- suspensão temporária;
- limite por plano;
- próxima execução calculada;
- prevenção de execução duplicada.

Contrato de disparo:

```json
{
  "event": "SCHEDULE_TRIGGERED",
  "schedule_id": "SCH-000001",
  "tenant_id": "tenant_123",
  "workflow_id": "WF-000001",
  "request_id": "uuid",
  "scheduled_for": "2026-07-11T15:00:00-03:00",
  "payload": {}
}
```

## Workflow Composer

Workflows compostos devem utilizar child workflows reutilizáveis.

Exemplo de provisionamento:

```text
Factory Provisioning
  ├── Validate Tenant
  ├── Create License
  ├── Provision Database
  ├── Provision Storage
  ├── Provision Docker
  ├── Configure Domain
  ├── Configure SSL
  ├── Deploy Application
  ├── Health Check
  └── Delivery Notification
```

Cada child workflow deve:

- possuir ID e versão próprios;
- emitir evento de início, sucesso e falha;
- ser idempotente;
- possuir retry independente;
- poder ser executado isoladamente;
- registrar telemetria própria.

## Estados do workflow composto

- pending
- running
- partially_completed
- waiting_dependency
- completed
- failed
- compensating
- rolled_back

## Compensação

Falhas em workflows compostos podem disparar ações de compensação:

- remover container criado;
- revogar licença temporária;
- excluir banco provisório;
- remover DNS parcial;
- liberar quota reservada;
- liberar locks.
