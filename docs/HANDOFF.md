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
- O código existente do Repórter IA usa `tvs_heygen_load_config` e `tvs_outbound_curl_options`; o diagnóstico ainda não compartilha esses adaptadores.
- O look foi encontrado em conta HeyGen conectada ao ChatGPT; não comprovado no workspace da credencial da TV Sumaré.
- `AGENTS.md`, `CLAUDE.md`, `docs/OPERACAO-CHATGPT.md` não encontrados nos caminhos convencionais da branch na auditoria.

## Próximo executor
1. Confirmar a rota operacional **real** de execução read-only na HML, sem exposição da chave e sem mexer em árvore Git suja.
2. Reconciliar o adaptador com o helper canônico e a política de saída, mantendo os endpoints GET allowlisted.
3. Validar respostas reais e paginação; atualizar testes com fixtures sanitizadas.
4. Obter evidência HML e solicitar supervisão da VIA; **não avançar** sem comprovação.
5. Não gerar vídeo pago, fazer merge, deploy ou alterar produção sem autorização específica.

## Gate de supervisão
`can_advance=false`; `evidence_verified=false`; motivo: `pending_external_verification`.
