# Estado tecnico auditado

## Situacao atual — 2026-09-12

- Homologacao publicada em `tv-hml.vitrineiapro.com.br`.
- Runtime Apache/PHP 8.3 versionado em Docker.
- `tvsumare_web` validado operacionalmente com health check.
- `tvsumare_radar_scheduler` validado operacionalmente em homologacao.
- Dados, uploads, videos e logs persistentes separados em `/srv/tvsumare/shared`.
- Root filesystem dos containers em modo read-only, com `no-new-privileges`, limites de PID, memoria, CPU e descritores.
- Configuracoes sensiveis fornecidas por variaveis de ambiente/runtime.
- Health check disponivel em `/health.php`.
- Producao ativa em `tvsumare.com.br` no HostGator.
- Deploy de producao realizado de forma seletiva, preservando `data/`, `config/`, uploads e demais dados vivos.
- Cron de producao para `admin/cron_radar.php` instalado com execucao horaria.
- Backup integral e backup seletivo de producao obrigatorios antes de novas publicacoes.
- Branch candidata consolidada: `reconcile/hostgator-tvsumare-20260911`.
- CI dedicado adicionado em `.github/workflows/tvsumare-ci.yml`.

## Classificacao oficial

HOMOLOGATION_ACTIVE + PRODUCTION_ACTIVE

A base tecnica esta operacional. Alteracoes de codigo devem passar pela branch candidata/canonica, CI e homologacao antes de publicacao seletiva no HostGator.

## Politica editorial

- Regiao permitida: Sumare, Hortolandia, Paulinia, Nova Odessa, Americana e Campinas.
- Evidencia regional deve vir do conteudo/fonte; o campo `city` isolado nao e suficiente.
- Pautas sensiveis ou de alto impacto permanecem elegiveis por relevancia, mas exigem revisao humana antes da publicacao.
- Materias sem imagem jornalistica confirmada exigem revisao de imagem.
- Conteudo vencido ou fora da regiao nao deve permanecer em destaque/home.
- Saneamento de dados historicos deve ser executado separadamente das regras de ingestao.

## Security hardening

- CSRF habilitado nas superficies administrativas.
- SameSite=Strict nas sessoes administrativas.
- Requisicoes server-side sujeitas a allowlist e validacao de IP publico.
- Verificacao TLS habilitada e redirects automaticos restringidos no outbound guard.
- Container root read-only e capabilities reduzidas.
- Execucao de scripts em uploads/videos bloqueada pela configuracao imutavel do Apache.

## Operacao Git

- GitHub e a fonte de verdade do codigo.
- Nao executar reset/clean/reconcile em arvore suja sem preservacao previa.
- `main` deve receber somente estado validado e reproduzivel.
- Alteracoes de producao feitas fora do Git devem ser reconciliadas imediatamente.
- PRs de recovery intermediarios nao devem ser usados como baseline quando existir uma branch reconciliada posterior.

## Pendencias para fechamento 100%

1. Validar CI da branch reconciliada.
2. Consolidar a branch reconciliada em `main` por fast-forward somente apos os checks.
3. Sanear dados editoriais historicos fora da regiao, vencidos ou com editoria incorreta.
4. Formalizar release/rollback do HostGator e registrar manifestos de release.
5. Habilitar protecao/ruleset da branch canonica quando a permissao administrativa do conector permitir.
6. Validar configuracao real do webhook de WhatsApp antes de considerar notificacao externa operacional.
