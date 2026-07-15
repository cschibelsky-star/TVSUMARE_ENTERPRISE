# Vitrine IA Flow — Workflow Registry

## Objetivo

Padronizar o catálogo de workflows reutilizáveis da Vitrine IA Flow.

Nenhum workflow deve ser criado como solução isolada para um único produto. Todo workflow deve declarar metadados, fila, versão, produtos compatíveis, política de retry e eventos emitidos.

## Modelo de registro

```yaml
id: WF-000001
slug: factory-provisioning
name: Factory Provisioning
version: 1.0.0
queue: Provision Queue
owner: Factory Automation
reusable: true
products:
  - tv_sumare_enterprise
  - portal_news_enterprise
  - guia_digital_cidade
  - social_media_ia
triggers:
  - ORDER_PAID
  - PROVISION_REQUESTED
emits:
  - PROVISION_STARTED
  - PROVISION_DOCKER_READY
  - PROVISION_DATABASE_READY
  - PROVISION_DOMAIN_READY
  - PROVISION_SSL_READY
  - PROVISION_COMPLETED
  - PROVISION_FAILED
retry_policy:
  attempts: 3
  backoff_seconds:
    - 30
    - 120
    - 600
dead_letter_queue: true
status: draft
```

## Workflows iniciais

| ID | Slug | Fila | Status |
|---|---|---|---|
| WF-000001 | rss-editorial-pipeline | Editorial Queue | draft |
| WF-000002 | news-to-instagram | Social Queue | draft |
| WF-000003 | news-to-heygen | Video Queue | draft |
| WF-000004 | video-distribution | Social Queue | draft |
| WF-000005 | daily-monitoring | Monitoring Queue | draft |
| WF-000006 | factory-provisioning | Provision Queue | draft |

## Regra de versionamento

- Alterações pequenas: 1.0.1
- Novas etapas compatíveis: 1.1.0
- Mudança incompatível: 2.0.0

Nunca editar workflow ativo em produção sem versionar.
