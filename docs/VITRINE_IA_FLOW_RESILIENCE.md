# Vitrine IA Flow — Resiliência, Circuit Breaker e Concorrência

## Objetivo

Garantir que falhas de provedores externos, excesso de carga ou múltiplas execuções simultâneas não derrubem o Motor de Orquestração Enterprise.

## Circuit Breaker

Cada integração externa deve operar com três estados:

- `closed`: chamadas liberadas.
- `open`: chamadas bloqueadas após atingir o limite de falhas.
- `half_open`: libera poucas chamadas de teste antes de reabrir totalmente.

### Eventos oficiais

- CIRCUIT_OPENED
- CIRCUIT_HALF_OPEN
- CIRCUIT_CLOSED
- CIRCUIT_CALL_REJECTED

### Regras iniciais

- Abrir após 5 falhas consecutivas.
- Permanecer aberto por 5 minutos.
- Em `half_open`, permitir apenas 1 chamada de teste.
- Fechar após 2 chamadas de teste bem-sucedidas.

## Rate Limiting

O limite deve considerar:

1. provedor;
2. tenant;
3. produto;
4. workflow;
5. fila.

Nunca aplicar somente um limite global.

### Headers esperados

```http
X-Vitrine-Tenant-Id: tenant_123
X-Vitrine-Workflow-Id: WF-000006
X-Vitrine-Rate-Key: heygen:tenant_123
```

### Eventos oficiais

- RATE_LIMIT_REACHED
- RATE_LIMIT_DELAYED
- RATE_LIMIT_RELEASED

## Controle de concorrência

A concorrência deve ser controlada por chave composta:

```text
{tenant_id}:{workflow_id}:{resource_key}
```

Exemplo:

```text
tenant_123:WF-000006:domain_cliente_com_br
```

Isso impede dois provisionamentos simultâneos sobre o mesmo domínio, banco ou cliente.

### Estratégias

- `reject`: rejeitar nova execução.
- `queue`: aguardar liberação.
- `replace`: cancelar execução anterior e manter a mais recente.

O padrão da Vitrine IA Flow será `queue`.

## Locks distribuídos

O Mission Control será responsável por disponibilizar APIs de lock.

Contratos previstos:

```text
POST /api/flow/locks/acquire
POST /api/flow/locks/release
POST /api/flow/locks/heartbeat
```

O n8n nunca deve manter lock apenas em memória local quando o workflow puder executar em múltiplos workers.

## Regras por domínio

### Provisionamento

- Concorrência por tenant: 1.
- Concorrência por domínio: 1.
- Estratégia: queue.

### Deploy

- Concorrência por projeto: 1.
- Estratégia: queue.

### IA

- Concorrência por tenant: configurável por plano.
- Estratégia: queue.

### Social

- Concorrência por conta/canal: 1.
- Estratégia: queue.

### Monitoramento

- Concorrência global limitada.
- Estratégia: reject para execuções duplicadas no mesmo intervalo.

## Observabilidade obrigatória

Toda decisão de circuit breaker, rate limit ou lock deve gerar evento para o Mission Control.
