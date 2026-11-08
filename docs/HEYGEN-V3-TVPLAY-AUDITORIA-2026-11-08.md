# HeyGen Video Agent v3 — auditoria e migração do TV Play

Data: 2026-11-08. Escopo: PR #60, somente GitHub, sem chamadas à API, sem merge e sem deploy.

## Estado encontrado em main

- `admin/reporter-ia.php` já envia `POST /v3/video-agents`, guarda `session_id`, consulta `GET /v3/video-agents/{session_id}` e `GET /v3/videos/{video_id}`. Exige aprovação do roteiro antes de enviar e mantém publicação manual.
- `admin/tvplay.php` carrega `includes/heygen_helper.php` e `includes/video_ai_helper.php`. O primeiro declara `tvp_send_heygen` / `tvp_check_heygen` dentro de guarda, priorizando na prática chamadas antigas `/v2/video/generate` e `/v1/video_status.get`.
- `includes/video_ai_helper.php` também contém implementações v3 de `tvp_send_heygen` / `tvp_check_heygen`, mas podem ser suprimidas pelo carregamento anterior do helper. Há implementação duplicada a consolidar.
- `api/heygen-callback.php` já suporta correspondência por callback ID, session ID ou video ID e bloqueia regressão de estado terminal. Porém lê/escreve `data/videos_ia.json` com read-modify-write não transacional; a mudança de persistência segura deve ser coordenada com PR #59.
- As gravações de `videos_ia.json` ocorrem em vários módulos; ainda não há garantia universal de concorrência.
- O token de callback aparece em parâmetro de URL; avaliar autenticação por assinatura/documentação do provedor, rotação e exposição em logs antes do deploy.
- O controle editorial exige revisão da notícia, aprovação do roteiro e aprovação/publicação posterior do vídeo. Não habilitar publicação automática nem consumir créditos em teste.

## Mudanças propostas neste PR

- `includes/heygen_helper.php`: TV Play migra para Video Agent v3 com resposta session-first e consultas progressivas à sessão/vídeo.
- `admin/tvplay.php`: preserva indicadores separados de sessão e vídeo.
- `tests/tvplay_heygen_v3_contract_test.php`: testes offline de resposta session-only, ausência de IDs, vídeo pendente sem URL liberada, conclusão e falha.
- `.github/workflows/tvsumare-ci.yml`: etapa de contrato offline.

## Gates antes de liberação

1. CI PHP e Docker concluído com sucesso para o commit do PR #60.
2. Revisão de status/eventos com fixtures equivalentes ao formato atual do provedor.
3. Resolver persistência transacional de `videos_ia.json` entre callback, TV Play e Repórter IA; não aplicar solução parcial insegura.
4. Confirmar que HML não compartilha dados operacionais com produção.
5. Validar credenciais e consumo de créditos **somente com autorização específica para chamadas reais**.
6. Verificar apresentação, voz, Brand Kit, pronúncia, orientação 16:9 e 9:16 e publicação manual.
