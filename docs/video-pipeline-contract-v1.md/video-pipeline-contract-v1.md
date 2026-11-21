# Contrato do pipeline de vídeo HeyGen — v1

## Objetivo

Registrar o ciclo editorial de um vídeo gerado para uma matéria aprovada, preservando a referência do provedor no próprio registro da matéria e evitando associação por título.

## Fluxo

1. A matéria aprovada gera um roteiro para revisão editorial.
2. O editor aprova o roteiro e o servidor envia a solicitação ao HeyGen pela VPS.
3. A chave HeyGen permanece somente no servidor; o navegador recebe apenas estados e identificadores não secretos.
4. O job persiste o identificador retornado pelo provedor (session_id e/ou video_id) e o news_id da matéria.
5. O painel consulta a sessão e, quando disponível, GET /v3/videos/{video_id} para obter status, URL, miniatura e falha técnica.
6. O histórico da conta pode ser consultado com GET /v3/videos, sem substituir o vínculo persistido por news_id.
7. Um vídeo pronto precisa de aprovação editorial explícita antes da publicação.
8. Após a aprovação, o vídeo é publicado em data/videos.json com news_id e heygen_video_id, ficando disponível na matéria e em Vídeos.

## Registro mínimo da matéria

| Campo | Tipo | Regra |
|---|---|---|
| video_job_id | string opcional | Identifica o job local atual. |
| video_provider | heygen opcional | Provedor da geração. |
| video_status | string opcional | Estado normalizado exibido no painel. |
| video_session_id | string opcional | session_id retornado por Video Agent. |
| video_id | string opcional | video_id retornado pelo HeyGen. |
| video_url | URL opcional | URL somente após o vídeo estar pronto. |
| video_thumbnail_url | URL opcional | Miniatura retornada pelo provedor. |
| video_approved_at | ISO-8601 opcional | Momento da aprovação editorial. |
| video_published_at | ISO-8601 opcional | Momento da publicação no portal. |
| video_updated_at | ISO-8601 opcional | Última sincronização do estado. |

## Estados editoriais

- roteiro_revisao: roteiro gerado, aguardando revisão.
- roteiro_aprovado: roteiro liberado para envio.
- heygen_agente_processando: solicitação aceita e em processamento.
- video_pronto: URL disponível, aguardando aprovação do vídeo.
- video_aprovado: vídeo aprovado, liberado para publicação.
- publicado: vídeo exibido no portal e em Vídeos.
- heygen_falhou: falha preservada no job; o roteiro pode ser reenviado após correção.

## Operações HeyGen

- Criar: POST /v3/video-agents.
- Consultar sessão: GET /v3/video-agents/{session_id}.
- Consultar vídeo: GET /v3/videos/{video_id}.
- Listar histórico: GET /v3/videos.

## Segurança e homologação

A chave de API não pode ser gravada em data/, no HTML, em logs públicos ou no Git. O primeiro teste de geração deve ocorrer em HML com um roteiro aprovado, registrando o session_id/video_id, o status e a evidência de aprovação. Merge, deploy e geração paga permanecem fora deste contrato e exigem autorização própria.
