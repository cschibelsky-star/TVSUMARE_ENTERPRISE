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

## Evidências adicionais — V4 e backup VPS (2026-11-07)
- Conector V4 `tvsumare_git_status`: diretório em `main...origin/main`, com **seis entradas não rastreadas** de nomes semelhantes a fragmentos de fontes/cidades. Preservar; não executar reset, clean, merge ou deploy sobre essa árvore antes de inventariar.
- V4 `docker_status`: container `tvsumare_web` ativo e marcado **healthy** em 2026-11-07 21:18 UTC; Docker Compose project `tvsumare`, diretório `/srv/tvsumare/repository`, compose `docker-compose.vps.yml`, roteamento Traefik `tv-hml.vitrineiapro.com.br`. Isso confirma o serviço HML, não a produção HostGator.
- V5 `vps_backup_status`: backup geral habilitado, retenção 14 dias, inclui `/srv/tvsumare`; último arquivo listado `vps-filesystem-20261107T171312Z.tar.gz`, 2.254.843.540 bytes, checksum SHA-256 presente, 146.207 membros. **Restore não testado**.
- O ID tentativo `tv-sumare` não é manifest V5; não criar novo manifest sem reconciliar com Compose existente.

### Próximo gate de segurança
1. Inspecionar com leitura os seis untracked e o status de outros serviços do Compose, sem expor segredos.
2. Conferir `/health.php` HML e agendamento `tvsumare_radar_scheduler` com evidência atual.
3. Validar backups específicos de JSON/uploads e plano de restauração isolado; não restaurar em produção.
4. Auditar caminhos de publicação direta e concorrência de escrita JSON; testar em ambiente descartável.

## Verificações adicionais — 2026-11-07, leitura somente
- `tvsumare_health` V4 confirma `/srv/tvsumare` existente e gravável e `/srv/backups/tvsumare` configurado; não confirma integridade do backup específico.
- `docker_status` V4 confirma `tvsumare_web` healthy, **`tvsumare_web_prod` healthy** e `tvsumare_radar_prod_candidate` Exited (0) há aproximadamente 6 dias. Não concluir que o scheduler está inoperante sem inspecionar cron/timers; container pode ser one-shot.
- `hostgator_health` V4 confirma conexão remota disponível, mas a lista de raízes permitidas não inclui uma raiz TV Sumaré específica; não foi auditado conteúdo de produção HostGator.
- `tvsumare_php_lint` V4 falhou (exit 127, `php: No such file or directory`), não considerar lint aprovado. Avaliar execução de lint em container PHP com rota permitida ou CI.
- `container_status` V4 retornou 403 `container_not_allowed` para os nomes TV; preferir `docker_status` read-only e não contornar a política do broker.
- HML `tvsumare_web` foi recriado entre consultas no mesmo dia; investigar motivo e logs com ferramentas autorizadas antes de afirmar estabilidade histórica.

### Riscos novos
1. **P0**: confirmar roteamento de produção VPS vs HostGator e origem canônica de publicação para evitar escrita dupla.
2. **P0**: confirmar scheduler do Radar, execução real e logs, sem reiniciar.
3. **P1**: estabelecer lint/testes no ambiente PHP real, sem depender de `php` no host.
4. **P1**: auditar divergência de deploy e dados entre `tvsumare_web` e `tvsumare_web_prod`.

## Evidências de roteamento, scheduler e health (07/11/2026)
- `docker-compose.vps.yml` em `main` define `tvsumare_web` e `tvsumare_radar_scheduler`, ambos com `TVSUMARE_ENV: homologation`. Scheduler previsto: loop `php /var/www/html/admin/cron_radar.php; sleep 3600`. **A presença do serviço no Compose não comprova que esteja rodando**; o inventário Docker atual não exibiu `tvsumare_radar_scheduler` ativo.
- O Compose monta `/srv/tvsumare/shared/data`, `uploads`, `logs` na homologação. **P0:** conferir os mounts de `tvsumare_web_prod`; se apontarem aos mesmos caminhos, há risco de HML e produção compartilharem dados vivos. Não foi comprovado compartilhamento com produção.
- `health.php` considera saudável apenas `data/` existente e legível. Status Docker healthy **não verifica scheduler, conectividade de fonte, publicação, filas, gravação JSON ou consistência editorial**.
- `AUDIT_STATUS.md` (2026-09-12) relata produção HostGator e cron horário; são informações históricas, não evidência atual de execução.
- `hostgator_dns_status` para `tv-hml.vitrineiapro.com.br` retornou A `143.95.219.238`; validar roteamento DNS/Traefik com testes autorizados antes de atribuir destino. Consulta `tvsumare.com.br` retornou `hostname_not_allowed` (403), não inferir destino.

### Gate P0 atualizado
- Verificar isolamento real de volumes HML/prod e quem escreve `noticias.json` e `materias_aprovacao.json`.
- Identificar scheduler ativo: Compose, cron HostGator, logs e idempotência. Não iniciar um segundo scheduler sem evidência.
- Melhorar health em PR separado para health de dependências, sem expor segredos e sem chamadas pagas.
- Não confundir sucesso do health básico com prontidão editorial.

## Inspeção de volumes efetivos — 07/11/2026
- `docker_status` V4: `tvsumare_web` (HML) e `tvsumare_web_prod` (VPS produção) possuem **quatro mounts cada**. O retorno do broker abrevia todos como `/srv/tvsumare/…`; não fornece o destino completo. Portanto, **não foi possível confirmar nem excluir compartilhamento de volumes**.
- Ambos os containers apresentam label `com.docker.compose.project.config_files=/srv/tvsumare/repository/docker-compose.vps.yml`. Essa label é metadado da criação, não prova que a configuração versionada atual corresponda exatamente ao runtime.
- `tvsumare_radar_prod_candidate` aparece Exited (0) e com três mounts; sem evidência suficiente para concluir que a tarefa agendada esteja parada, pois pode haver cron externo ou execução pontual.
- Tentativa de consultar logs de containers via V4 não retornou evidência utilizável dentro do tempo disponível; **não alegar inspeção de logs concluída**.

### Critério de desbloqueio P0
Exigir evidência read-only do equivalente a `docker inspect --format '{{json .Mounts}}'` para os dois containers, com apenas Source/Destination/Mode/RW, sem variáveis de ambiente nem credenciais. Comparar `data`, `uploads`, `videos` e `logs` por destino; verificar também mounts de scheduler. Se coincidirem, congelar testes que escrevam em JSON até definir isolamento. Identificar cron ativo e últimos eventos do Radar antes de criar/reiniciar scheduler.

## Correção isolada criada em PR #58
- Auditoria de `admin/redes.php` em `main`: a ação `?draft=<id>` gerava rascunho e gravava `data/rascunhos.json` em requisição GET, sem token CSRF específico e com possibilidade de duplicação por reabertura de link.
- PR #58 draft (`fix/redes-draft-post-csrf-20261107`) troca por POST com campo CSRF validado em `admin/auth.php` e adiciona `social_pauta_id` para deduplicação sequencial. **Não houve deploy/merge**.
- Testes PHP/integração ainda não executados; proteção contra concorrência entre requisições simultâneas não está resolvida. A mudança precisa de QA e aprovação antes de integrar.
- `admin/cron_radar.php` declara trava lógica de uma execução por dia (`last_auto_date`) embora o Compose chame a cada 3600 segundos; isso explica frequência de chamadas versus frequência de geração, mas não comprova cron ativo. Inspecionar atomicidade da trava e logs antes de iniciar outro scheduler.

## Desbloqueio do manifesto V5 e verificações — 07/11/2026
- **ID canônico encontrado: `tvsumare`**. `project_manifest` V5 aponta `/srv/tvsumare`, repositório `cschibelsky-star/TVSUMARE_ENTERPRISE`, HML `tv-hml.vitrineiapro.com.br`, produção `tvsumare.com.br`, Compose `docker-compose.vps.yml`, diretórios compartilhados `data/uploads/videos/logs/cache/secrets`.
- **Divergência:** manifesto V5 declara branch `fix/home-regular-production-20261026`, mas `project_git_status` informa checkout `main...origin/main` com seis untracked. Preservar árvore; não sincronizar automaticamente.
- `project_docker_status` V5: `tvsumare_web` e `tvsumare_web_prod` healthy; serviços Compose `web` e `web_prod`. **Nenhum `radar_scheduler` ativo listado.** Não concluir ausência de cron externo.
- `project_backup_status` V5: `backup_not_configured` para backup **específico de projeto**. O backup geral VPS inclui `/srv/tvsumare` e não substitui prova de restauração granular.
- `project_php_lint` V5 em `admin/redes.php` do checkout VPS: **exit 0, sem erros de sintaxe**, em container efêmero `vitrine-core-hml-app:latest`. **Não testa o PR #58**, pois a branch ainda não foi aplicada ao checkout; não equivale a teste funcional.
- `project_compose_service_status` para `radar_scheduler` e `web_prod`: `controlled_service_not_allowed`. Isso é limite do conector, não estado dos serviços.

### Próximas ações permitidas
1. Comparar efetivamente os mounts HML/prod via ferramenta de leitura autorizada; sem `docker inspect` exposto neste conector.
2. Localizar logs e cron do Radar por leitura segura, sem ativar novos agendadores.
3. Revisar manifesto divergente com branch GitHub antes de corrigir metadados.
4. Configurar backup granular **somente após** avaliar destino, retenção, escopo e autorização específica.

## Hardening complementar — PR #58
- Revisão identificou que `admin/redes.php` renderizava `href` externo a partir de URL cadastrada sem limitar esquema; `htmlspecialchars` impede injeção de atributo, mas não impede links `javascript:` ou outros esquemas perigosos.
- Commit `0cda9fc` na branch do PR #58 limita o botão externo a URL válida com esquema `http` ou `https` e usa `rel=noopener noreferrer` para nova aba. Não altera dados persistentes nem fluxo de publicação.
- Testes de sintaxe e navegação da branch PR #58 ainda pendentes; não considerar pronto para merge.

## Gate de regressão para PR #58 — 07/11/2026
- Adicionado `tests/social_draft_security_contract_test.php` ao PR #58; verifica ausência de GET mutável, POST, campo CSRF, ID de origem, esquema HTTP(S) e isolamento de abas. Teste **estático de contrato**, não teste funcional de requisições nem teste de concorrência.
- Atualizado `.github/workflows/tvsumare-ci.yml` na branch do PR #58 para executar o teste em pull requests.
- **Execução CI ainda não verificada**. Não declarar PASS até obter resultado real.

## Evidência de CI — PR #58 (07/11/2026)
- Commit head `57e93b69c118b52baa6e922c4ad94be8cc7eb206`; GitHub Actions `TV Sumare CI`, run ID `42422390472`: `status=completed`, `conclusion=success`.
- Essa evidência confirma conclusão do workflow configurado para o PR, incluindo lint PHP e teste estático de contrato. Não comprova testes HTTP end-to-end, concorrência, backup, mounts nem deploy.
- PR #57 e PR #58 continuam `open`, `draft=true`, `merged=false`.
