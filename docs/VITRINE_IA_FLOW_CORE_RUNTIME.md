# Vitrine IA Flow — Core Runtime 2.1

## Objetivo

Definir a camada de execução reutilizável da Vitrine IA Flow sem qualquer dependência visual do Centro Operacional Master.

## Componentes oficiais

### Event Bus

Recebe eventos normalizados do Laravel e direciona para o Workflow Registry.

### Workflow Composer

Permite compor um workflow principal a partir de workflows filhos independentes.

Exemplo:

```text
factory-provisioning
  ├── provision-database
  ├── provision-storage
  ├── provision-docker
  ├── provision-domain
  ├── provision-ssl
  ├── deploy-application
  └── health-check
```

### Child Workflows

Cada child workflow deve:

- receber contrato padronizado;
- ser idempotente;
- emitir evento de sucesso ou falha;
- ter timeout próprio;
- suportar retry e DLQ;
- não conhecer detalhes visuais do produto.

### Scheduler Enterprise

Agendamentos devem considerar:

- tenant;
- timezone;
- janela de execução;
- recorrência;
- prioridade;
- feriados;
- bloqueios operacionais;
- feature flags;
- cota disponível.

## Execução paralela

Workflows independentes podem ser disparados em paralelo quando não houver dependência.

Exemplo:

```text
NEWS_APPROVED
  ├── social-instagram
  ├── social-facebook
  ├── social-threads
  ├── social-telegram
  └── analytics-register
```

## Contrato mínimo de execução

```json
{
  "event": "WORKFLOW_REQUESTED",
  "request_id": "uuid",
  "tenant_id": "tenant_123",
  "product_code": "portal_news_enterprise",
  "workflow_slug": "factory-provisioning",
  "workflow_version": "1.0.0",
  "priority": 50,
  "scheduled_at": null,
  "payload": {}
}
```

## Regras

- Nenhum workflow deve acessar banco interno do n8n.
- Nenhum workflow deve conter segredo embutido.
- Nenhum workflow deve publicar sem validar feature flag, cota e idempotência quando aplicável.
- Todo workflow deve emitir telemetria para o Mission Control via API.
