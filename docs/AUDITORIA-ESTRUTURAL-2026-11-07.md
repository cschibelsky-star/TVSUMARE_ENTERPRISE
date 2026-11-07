# Auditoria estrutural TV Sumaré Enterprise — diagnóstico inicial
Data: 2026-11-07. Escopo: leitura de repositório main e conector operacional V5. **Não é homologação funcional nem auditoria completa.**

## Evidências confirmadas
- `README.md` ainda classifica projeto como scaffold sem runtime; `AUDIT_STATUS.md` (2026-09-12) descreve Apache/PHP 8.3, Docker HML, scheduler, health e produção HostGator. **Documentação contraditória e histórica**: confirmar estado atual.
- `product.json` registra `php-8.3-apache`, armazenamento `json-files`, dados persistentes `/srv/tvsumare/shared`, `/health.php`, versão `3.0.0-hml.2`.
- Código em `admin/radar-regional.php` (arquivo grande ~105 KB), `admin/editor-ia.php`, `admin/drafts.php`, `admin/redes.php`, `includes/video_ai_helper.php`, `includes/youtube_helper.php`, `includes/heygen_helper.php`, `includes/tvs_editorial_policy_v3.php`.
- Teste versionado `tests/editorial_policy_v3_test.php`. Cobertura funcional de todo o produto não comprovada.
- PR #54 e PR #55 trabalham regras editoriais em branches distintas, ainda sem integração homologada.
- V5 `system_health` respondeu (CPU 46.3%, RAM 49.9%, disco 40.64% no momento da consulta). **Isso mede a VPS geral, não saúde da TV Sumaré.**
- `project_manifest` para ID tentativo `tv-sumare` retornou `manifest_not_found`; por isso não foi possível validar runtime, Docker, Git e backup da TV Sumaré via este ID. Não criar manifesto sem localizar ID canônico e caminho reais.

## Inventário de domínios funcionais — estado evidenciado
| Domínio | Evidência de código | Operação verificada? | Próxima inspeção |
|---|---|---|---|
| Radar e coleta | admin/radar-regional.php | Não | cron, fontes, dedup, volume, fila e isolamento |
| Editor IA | admin/editor-ia.php | Não | contrato de entrada/saída, falhas e custo |
| Rascunhos/aprovação | admin/drafts.php e PR #55 | Não | estados, reenvio, aprovação, publicação |
| Site e notícias | dados JSON / helpers | Não | publicação, cache, SEO e correções |
| Vídeo/Repórter | includes/video_ai_helper.php, heygen_helper.php | Não | filas, versões API, custos e falhas |
| YouTube/distribuição | includes/youtube_helper.php e youtube_oauth.php | Não | tokens, permissões, retry e auditoria |
| Redes sociais | admin/redes.php | Não | criação de pautas e publicação externa |
| Segurança | admin/auth.php e outbound_guard.php | Não | RBAC, CSRF, SSRF, segredos e logs |
| Infraestrutura | AUDIT_STATUS.md, product.json | Não | HML/prod, Docker, DNS, backup e restore |
| Comercial/analytics | Não inventariado completamente | Não | painel, contratos, audiência, receita |

## Riscos priorizados
- **P0 — publicação editorial**: caminhos de publicação múltiplos; PR #55 ainda draft, sem testes integrados e HML. Não realizar merge/deploy antes de prova ponta a ponta.
- **P0 — integridade de dados**: JSON como armazenamento vivo e múltiplos escritores; inspecionar LOCK_EX, leitura/escrita atômica, concorrência, backups e restauração sem modificar dados.
- **P0 — documentação e ambiente**: README contradiz AUDIT_STATUS; confirmar ambiente atual e registrar evidência de health do serviço TV.
- **P1 — responsabilidades misturadas**: Radar mistura coleta, regras, processamento e interface em arquivo monolítico; extrair contratos por etapas, sem big-bang.
- **P1 — identidade editorial e imagem**: provenance de fonte, imagem original vs arte ilustrativa, URLs e direitos de uso.
- **P1 — integrações externas**: HeyGen/YouTube/IA exigem timeout, idempotência, custos, limites e proteção de credenciais.
- **P2 — produto**: métricas, comercial, portal, TV Play, programação 24h, canais e acessibilidade ainda requerem inventário.

## Arquitetura-alvo proposta (não implantada)
1. **Coleta e inventário**: RSS, Google News, fontes oficiais; triagem temporal/regional, dedup, sugestões persistentes.
2. **Apuração**: URL canônica, texto-fonte verificado, evidências, imagem com procedência.
3. **Produção editorial**: Editor IA ou redação humana; conteúdo/SEO/social em rascunho.
4. **Governança**: fila única, devolução, aprovação humana separada de publicação, trilha de auditoria.
5. **Publicação**: único serviço/contrato de publicação, idempotência, rollback editorial, cache.
6. **Audiovisual**: roteiro, geração, QA, aprovação, TV Play.
7. **Distribuição**: site, redes, YouTube, ao vivo e programação, com estados independentes.
8. **Operações**: observabilidade, RBAC, backup/restore, HML, testes, custos e relatórios.

## Plano de execução por gates
- **Gate 0 (somente leitura)**: identificar projeto canônico no conector, mapa HML/prod, manifests, serviços, dados, backups e Git. Resultado: inventário de ativos e divergências.
- **Gate 1 (contratos)**: estados editoriais e ownership de JSON/filas, contratos de imagens/fontes e publicação. Resultado: diagrama e testes de contrato.
- **Gate 2 (correções P0)**: bloquear publicação indevida, concorrência e perda de dados. Resultado: testes negativos e rollback.
- **Gate 3 (modularização)**: dividir Radar, Editor IA, aprovação, publicação e audiovisual em entregas pequenas com testes por módulo.
- **Gate 4 (homologação)**: smoke E2E em HML com dados fictícios, checagem de observabilidade e restore. Resultado: evidências e aceite.
- **Gate 5 (produção)**: apenas mediante autorização explícita, backup validado, plano de rollback e janela aprovada.

## Regras
GitHub é fonte de verdade. PRs #54/#55 permanecem isolados; não misturar auditoria com código em revisão. Não usar dados reais para testes, não apagar notícias, não reconfigurar VPS, não gastar APIs, não fazer merge/deploy sem autorização.

## Bloqueios e próximos dados
1. Descobrir ID canônico do projeto na V5 (o nome tentativo `tv-sumare` não existe).
2. Obter inventário atual de containers, vhosts, cron, backups e storage com conector read-only apropriado.
3. Auditar todas as rotas de publicação e escrita JSON antes de refatorar.
4. Estabelecer checklist de pronto por módulo e baseline de testes.
