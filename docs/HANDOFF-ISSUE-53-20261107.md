# Handoff — Issue #53 Radar Editorial (07/11/2026)

## Alterações realizadas
- Branch `feature/issue-53-radar-editorial-lifecycle` baseada em `fix/radar-editorial-gates-20261107` (PR #54).
- PR #55 em draft.
- `includes/editorial_workflow_policy.php`: valida integridade da fonte e texto final >=350 caracteres, além da conclusão da preparação editorial; máquina de estados independente.
- `admin/radar-regional.php`: usa `mat['text']` como fonte extraída; pauta insuficiente recebe `em_edicao`, fica fora da lista de aprovação e não é publicada por função direta ou lote. O reprocessamento de score preserva esses itens.
- `tests/editorial-workflow-policy-test.php`: testes de política editorial.

## Evidências
- PHP 8.4.24 local.
- `php -l` para política e teste: sem erro.
- `php tests/editorial-workflow-policy-test.php`: 9 verificações PASS em cópia isolada da política e do teste.
- Não foi executado smoke completo de `admin/radar-regional.php`; não foi testado em HML; não houve deploy.

## Pendências bloqueantes antes de merge/deploy
1. Verificar sintaxe PHP do arquivo `admin/radar-regional.php` completo e executar testes integrados com fixtures.
2. Inspecionar `tvs_build_material_from_candidate` e garantir que `mat['text']` representa texto integral da fonte e não snippet duplicado.
3. Separar armazenamento de pautas `em_edicao` do JSON `materias_aprovacao.json`; atualmente ficam no mesmo arquivo mas são filtradas da interface.
4. Unificar `drafts.php` e Radar no mesmo ciclo editorial; implementar aprovação sem publicação imediata. O endpoint legado `tvs_publish_from_queue` ainda publica diretamente itens válidos.
5. Revalidar fluxos de edição e proteção de imagem, CSRF e idempotência; inspecionar compatibilidade com dados legados.
6. Executar testes HML antes de qualquer merge; preservar notícias reais e produção.

## Governança
PR draft, sem merge, sem implantação e sem publicação. Consulta Claude via Avello foi revisão de proposta; alterações de código foram feitas pelo ChatGPT e devem passar por QA independente.
