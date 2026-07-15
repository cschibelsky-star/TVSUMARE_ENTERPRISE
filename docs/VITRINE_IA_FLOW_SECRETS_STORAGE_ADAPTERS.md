# Vitrine IA Flow — Secrets Manager, Storage Manager e Provider Adapters

## Objetivo

Padronizar o acesso a segredos, armazenamento e provedores externos sem acoplamento direto dos workflows.

## Secrets Manager

Nenhum workflow deve conter tokens, senhas, chaves ou credenciais embutidas.

Fluxo obrigatório:

```text
Workflow
  ↓
Secrets Manager API
  ↓
Credencial temporária / referência segura
  ↓
Provider Adapter
```

Contrato sugerido:

```text
POST /api/flow/secrets/resolve
```

Payload:

```json
{
  "tenant_id": "cliente_123",
  "provider": "gemini",
  "credential_key": "primary_api_key",
  "request_id": "uuid"
}
```

Resposta:

```json
{
  "ok": true,
  "secret_ref": "sec_abc123",
  "expires_at": "2026-07-11T15:00:00-03:00"
}
```

A Flow deve preferir referências temporárias e nunca persistir secrets em logs.

## Storage Manager

Todo workflow deve utilizar uma abstração única para arquivos.

Backends suportados:

- local
- S3 compatível
- Cloudflare R2
- Google Drive
- Azure Blob Storage

Operações padrão:

- upload
- download
- copy
- move
- delete
- sign_url
- metadata

Contrato sugerido:

```text
POST /api/flow/storage/operations
```

Payload base:

```json
{
  "tenant_id": "cliente_123",
  "operation": "upload",
  "backend": "s3",
  "path": "tvsumare/videos/video.mp4",
  "content_type": "video/mp4",
  "request_id": "uuid"
}
```

## Provider Adapter

Todo provider externo deve ser acessado por adapter padronizado.

Interface lógica:

```text
resolve credentials
validate quota
invoke provider
normalize response
record usage
emit telemetry
```

Adapters iniciais:

- GeminiAdapter
- OpenAIAdapter
- HeyGenAdapter
- MetaAdapter
- YouTubeAdapter
- TelegramAdapter
- StorageAdapter

## Resposta normalizada

```json
{
  "ok": true,
  "provider": "gemini",
  "operation": "generate_text",
  "provider_request_id": "provider-id",
  "usage": {
    "input_tokens": 1200,
    "output_tokens": 380,
    "estimated_cost": 0.014
  },
  "result": {}
}
```

## Regras

- Nunca registrar secrets em logs.
- Nunca chamar provider diretamente de workflow de negócio.
- Sempre emitir uso, custo e telemetria.
- Sempre suportar timeout, retry, circuit breaker e fallback.
- Sempre operar com tenant_id e request_id.
