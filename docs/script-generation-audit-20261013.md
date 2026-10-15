# Auditoria e correção de roteiros — 13/10/2026

Base: PR #28, commit 24a73e2bc6d33ace91146f39da9fc8d9c29a3407. A branch operacional está 14 commits atrás desta base; não houve substituição pela main.

## Achados
- Repórter IA: Anthropic → Gemini → texto local. Falhas podiam virar um roteiro genérico sem informar degradação. A gravação não era verificada.
- Boletim IA: chave OpenAI ausente retorna erro, mas bia_create_job criava texto local e a interface dizia sucesso. Portanto, chave ausente isoladamente não comprovava roteiro vazio.
- TV Play: Centro IA → Gemini → texto local. tvp_polish_script podia acrescentar abertura/encerramento ao texto vazio; geração e aprovação alteravam estados sem validar conteúdo nem confirmar gravação.
- Nenhuma evidência obtida de que estas sejam todas as causas do incidente em produção. Não foram consultados textos ou secrets reais.

## Correção
Validação compartilhada de tipo, UTF-8, texto útil (80–20000 caracteres e ao menos 12 palavras), JSON/rótulos de erro e respostas incompletas. Fallback entre provedores válidos é preservado; não há mais sucesso automático por fallback local. Não há aprovação automática. O operador pode revisar e aprovar um texto manual válido.
Persistência de roteiro usa releitura, comparação do job esperado, lock entre escritores de roteiro e substituição atômica. Falha mantém texto/estado anteriores; filas corrompidas não são substituídas. Regeração não sobrescreve roteiro aprovado nem job enviado. Falhas aparecem na interface e são registradas com código e referência sem secrets, textos ou resposta bruta do provedor.
A validação também protege o envio de jobs legados inválidos. Payloads HeyGen v3, BrandKit, políticas territoriais e identidade visual foram preservados.

## Validação executada
Ambiente separado: tvsumare-script-validation-hml-20261013. A HML editorial anterior e seus arquivos locais foram preservados.
- SCRIPT_GENERATION=PASS checks=41 (provedores simulados, chaves ausentes, texto vazio/JSON/tipo inválido, resposta truncada, armazenamento indisponível, fila corrompida, conflito e preservação de aprovação).
- SCRIPT_HTTP_HML=PASS checks=31: Apache real, autenticação e CSRF reais; POST nos três painéis; falhas sem provedores; aprovação inválida recusada; aprovação manual válida persistida; preservação de roteiro aprovado.
- EDITORIAL_LIFECYCLE=39 PASS.
- Diretor Editorial e política V3: PASS.
- Build Docker e aplicadores existentes: PASS; HOMOLOGATION_SMOKE=PASS total=56.
- Demais testes do Dockerfile: modelo HeyGen, imagens, regras do Radar, eleições, deduplicação e YouTube passaram.
- Container HTTP sem rede (network_mode: none), sem chaves de provedores e com data/uploads/logs em tmpfs. Somente fixtures sintéticas; nenhum vídeo pago ou publicação real.
O teste HTTP é CLI-only e exige TVSUMARE_ENV=homologation, HML_ISOLATED=1, HML_SYNTHETIC_DATA=1 e ausência de credenciais de provedores. Deve ser executado em container descartável isolado, nunca com volumes de produção.

## Limites e gate
O protocolo de lock cobre as gravações de roteiro; outros escritores legados da fila de vídeo ainda não participam desse lock. Não se declara cobertura completa de concorrência com callbacks/worker.
Não houve teste de geração com credenciais reais: falhas dos provedores foram simuladas e o HTTP confirmou o caminho sem configuração. CI remoto deve ser conferido no novo commit. PR permanece draft; sem merge, mudanças de secrets ou atualização em produção.
