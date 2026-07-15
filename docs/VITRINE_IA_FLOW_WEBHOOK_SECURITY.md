# Vitrine IA Flow — Segurança de Webhooks

## Objetivo

Proteger toda comunicação Laravel ↔ n8n contra falsificação, replay e duplicidade.

## Headers obrigatórios

```http
Authorization: Bearer {TOKEN}
Content-Type: application/json
X-Vitrine-Request-Id: {uuid}
X-Vitrine-Timestamp: {unix_timestamp}
X-Vitrine-Signature: sha256={hmac}
X-Vitrine-Event: {event_name}
X-Vitrine-Product: {product_code}
```

## Assinatura HMAC

A assinatura deve ser calculada sobre:

```text
{timestamp}.{request_id}.{raw_body}
```

Algoritmo:

```text
HMAC-SHA256(secret, timestamp.request_id.raw_body)
```

## Validações obrigatórias

1. Token Bearer válido.
2. Timestamp com tolerância máxima de 5 minutos.
3. Assinatura HMAC válida.
4. Request ID ainda não processado.
5. Evento permitido para o workflow solicitado.
6. Produto autorizado a consumir o workflow.

## Replay protection

Todo `X-Vitrine-Request-Id` deve ser tratado como chave idempotente.

Requisições repetidas devem retornar o mesmo resultado ou:

```json
{
  "ok": true,
  "status": "duplicate_ignored",
  "request_id": "uuid"
}
```

## Segredos

- Nunca versionar segredos no GitHub.
- Usar credenciais do n8n ou variáveis de ambiente.
- Rotacionar chaves periodicamente.
- Separar segredos de produção, homologação e desenvolvimento.
