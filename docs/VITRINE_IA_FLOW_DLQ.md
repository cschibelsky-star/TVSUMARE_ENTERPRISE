# Vitrine IA Flow — Dead Letter Queue (DLQ)

## Objetivo

Garantir que nenhuma execução perdida, falha definitiva ou payload inválido desapareça sem rastreabilidade.

A DLQ será consumida pelo Mission Control exclusivamente via API.

## Quando uma execução entra na DLQ

- Todas as tentativas da política de retry foram esgotadas.
- O payload é incompatível com o contrato esperado.
- A credencial obrigatória está ausente ou inválida.
- Um serviço externo ficou indisponível além do limite definido.
- O callback para o Laravel falhou de forma definitiva.
- O workflow foi interrompido manualmente por política de segurança.

## Payload padrão da DLQ

```json
{
  "dlq_id": "DLQ-20260711-000001",
  "workflow_id": "WF-000006",
  "workflow_version": "1.0.0",
  "execution_id": "EXEC-20260711-000001",
  "queue": "Provision Queue",
  "event": "PROVISION_FAILED",
  "product_code": "tv_digital_enterprise",
  "tenant_id": "cliente_123",
  "request_id": "uuid",
  "attempts": 3,
  "error_code": "SSL_PROVISION_FAILED",
  "error_message": "Não foi possível emitir o certificado SSL.",
  "last_step": "provision_ssl",
  "retryable": true,
  "payload": {},
  "failed_at": "2026-07-11T13:00:00-03:00"
}
```

## Eventos emitidos

- WORKFLOW_RETRY_SCHEDULED
- WORKFLOW_RETRY_STARTED
- WORKFLOW_RETRY_FAILED
- WORKFLOW_SENT_TO_DLQ
- DLQ_REPROCESS_REQUESTED
- DLQ_REPROCESS_STARTED
- DLQ_REPROCESS_COMPLETED
- DLQ_REPROCESS_FAILED

## Reprocessamento

O Mission Control solicitará o reprocessamento por API.

```text
POST /api/flow/dlq/{dlq_id}/reprocess
```

O Laravel emitirá um novo webhook para a Vitrine IA Flow com o mesmo payload original, novo `request_id` e referência ao `dlq_id` anterior.

## Regras

- Nunca reprocessar automaticamente uma DLQ sem política explícita.
- Nunca apagar registros de DLQ.
- Toda ação de reprocessamento deve ser auditável.
- Credenciais e segredos nunca devem ser persistidos no payload da DLQ.
- O n8n não deve ser consultado diretamente pelo Mission Control.
