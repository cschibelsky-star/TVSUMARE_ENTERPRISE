# HANDOFF — TV Sumaré / Issue #63 / PR #64

## Objetivo
Validar acesso ao Digital Twin da HeyGen pelo runtime da TV Sumaré antes de integrar o look ao Repórter IA.

## Estado
**PENDENTE DE EVIDÊNCIA EXTERNA**. CI mock e lint passaram em commits anteriores; diagnóstico autenticado ainda não foi executado na identidade real do serviço. A implementação não foi implantada.

## O que foi feito
- `includes/heygen_digital_twin_probe.php`: análise somente leitura do catálogo, com tratamento conservador de paginação.
- `scripts/heygen_digital_twin_diagnostic.php`: CLI com GET allowlist e saída reduzida.
- `tests/heygen_digital_twin_probe_test.php`: testes mock.
- `.github/workflows/heygen-digital-twin-probe.yml`: lint, mock e teste sem chave.
- `docs/HEYGEN_DIGITAL_TWIN_CONTRACT.md`: contrato e gates de aceite.

## Evidências e lacunas
- PR: https://github.com/cschibelsky-star/TVSUMARE_ENTERPRISE/pull/64
- CI do commit e84c074: workflows HeyGen Digital Twin Probe Tests e TV Sumare CI concluídos com sucesso.
- Runtime config status: HEYGEN_API_KEY configurada; **não prova** que esteja exportada para o script CLI.
- O diagnóstico usa `tvs_heygen_load_config([])` e, desde o commit `c36fb85`, `tvs_outbound_curl_options()` por meio de `includes/outbound_guard.php`. A execução autenticada no runtime ainda não ocorreu.
- O look foi encontrado em conta HeyGen conectada ao ChatGPT; não comprovado no workspace da credencial da TV Sumaré.
- `AGENTS.md`, `CLAUDE.md`, `docs/OPERACAO-CHATGPT.md` não encontrados nos caminhos convencionais da branch na auditoria.

## Próximo executor
1. Confirmar a rota operacional **real** de execução read-only na HML, sem exposição da chave e sem mexer em árvore Git suja.
2. Verificar no runtime que a política de saída funciona para ambos os endpoints GET allowlisted.
3. Validar respostas reais e paginação; atualizar testes com fixtures sanitizadas.
4. Obter evidência HML e solicitar supervisão da VIA; **não avançar** sem comprovação.
5. Não gerar vídeo pago, fazer merge, deploy ou alterar produção sem autorização específica.

## Gate de supervisão
`can_advance=false`; `evidence_verified=false`; motivo: `pending_external_verification`.

## Atualização da execução
- Commit `2a5dd4f`: diagnóstico CLI passa a usar o carregador canônico de credenciais HeyGen.
- Commit `28ec72c`: teste de falha segura alinhado ao novo carregamento.
- CI dos commits acima: pendente de confirmação; não houve execução real da API.

## Atualização: política de saída
- Commit `c36fb85`: o diagnóstico passa a usar `includes/outbound_guard.php` e `tvs_outbound_curl_options()`; redirecionamentos são desabilitados para não encaminhar a credencial a outros hosts.
- O diagnóstico continua sem execução autenticada e sem deploy. CI da alteração deve ser verificado antes do avanço.

## Verificação posterior — 09/11/2026
- Commit `c36fb85`: HeyGen Digital Twin Probe Tests #9 concluído com `success`; TV Sumare CI #494 estava `in_progress` na consulta. Não afirmar sucesso total.
- Git na VPS: `main...origin/main` com seis arquivos não rastreados. Preservar todos; não fazer checkout/reset/clean/sincronização para executar o diagnóstico.
- O script atualizado não está implantado na VPS; consulta autenticada ao Digital Twin continua **não executada**.
- Próximo gate: ambiente isolado e autorizado com a credencial do serviço, ou conector operacional com comando de diagnóstico explicitamente permitido; registrar apenas HTTP/status/visibilidade, nunca credenciais ou payload bruto.

## Correção de esquema — 09/11/2026
- Commit `4977922`: formato de catálogo desconhecido retorna `catalog_schema_unrecognized` (fail-closed), sem confundir com lista vazia.
- Commit `61ac7bf`: adicionados testes mock de esquema desconhecido e catálogo reconhecido vazio.
- Confirmado no commit `c36fb85`: HeyGen Digital Twin Probe Tests #9 e TV Sumare CI #494 concluídos com sucesso. CI dos commits novos ainda requer verificação.
- Gate permanece `can_advance=false` até evidência autenticada na HML.

## Verificação adicional
- CI do commit `79b3f67`: HeyGen Digital Twin Probe Tests #11 concluído com sucesso; TV Sumare CI #496 ainda estava em andamento na consulta.
- Commit `5b64c36`: itens do catálogo sem `id` ou `avatar_id` agora falham com `catalog_item_schema_unrecognized`, em vez de serem ignorados.
- Commit `2e733c7`: teste mock do item malformado adicionado.
- Não houve consulta autenticada, deploy ou merge. Validar CI desses commits antes de avançar.
