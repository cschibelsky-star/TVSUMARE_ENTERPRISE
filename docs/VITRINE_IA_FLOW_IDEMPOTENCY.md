# Vitrine IA Flow — Idempotência

## Regra oficial

Todo workflow que altera estado deve ser idempotente.

## Chave idempotente

Campo obrigatório:

```text
request_id
```

Também pode ser enviado no header:

```http
X-Vitrine-Request-Id: {uuid}
```

## Escopo

A chave deve ser única por operação e tenant:

```text
{tenant_id}:{workflow_slug}:{request_id}
```

## Comportamento

- Primeira execução: processar normalmente.
- Repetição enquanto em andamento: retornar `processing`.
- Repetição após sucesso: retornar resultado anterior.
- Repetição após falha definitiva: retornar falha anterior, salvo reprocessamento explícito.

## Resposta padrão para duplicidade

```json
{
  "ok": true,
  "status": "duplicate_ignored",
  "request_id": "uuid",
  "execution_id": "exec_123"
}
```

## Retenção

Sugestão inicial:

- Provisionamento: 180 dias.
- Billing: 365 dias.
- Social: 30 dias.
- Editorial: 90 dias.
- Monitoramento: 7 dias.

## Proibição

Nenhum workflow deve gerar dois provisionamentos, duas cobranças, dois deploys ou duas publicações para a mesma chave idempotente.
