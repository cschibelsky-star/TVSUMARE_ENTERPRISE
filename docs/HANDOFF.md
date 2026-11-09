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
- O diagnóstico agora usa `tvs_heygen_load_config([])` para resolver a credencial como o Repórter IA. **Ainda não** reutiliza `tvs_outbound_curl_options`; a política de saída precisa ser reconciliada e validada antes da execução autenticada.
- O look foi encontrado em conta HeyGen conectada ao ChatGPT; não comprovado no workspace da credencial da TV Sumaré.
- `AGENTS.md`, `CLAUDE.md`, `docs/OPERACAO-CHATGPT.md` não encontrados nos caminhos convencionais da branch na auditoria.

## Próximo executor
1. Confirmar a rota operacional **real** de execução read-only na HML, sem exposição da chave e sem mexer em árvore Git suja.
2. Reconciliar o adaptador com a política de saída do helper canônico, mantendo os endpoints GET allowlisted.
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
