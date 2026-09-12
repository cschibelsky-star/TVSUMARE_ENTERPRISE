# TV Sumare — Operacao

## Ambientes

- Homologacao: `https://tv-hml.vitrineiapro.com.br`
- Producao: `https://tvsumare.com.br`
- Projeto Super: `tvsumare`
- Workspace HML: `/srv/tvsumare`
- Repositorio HML: `/srv/tvsumare/repository`
- Compose HML: `docker-compose.vps.yml`

## Fonte de verdade

GitHub e a fonte de verdade do codigo. A branch reconciliada deve ser validada em CI/HML antes de consolidacao em `main`.

Nunca executar `reset`, `clean`, merge ou reconciliacao em arvore suja sem preservacao previa.

## Homologacao

Servicos esperados:

- `tvsumare_web`
- `tvsumare_radar_scheduler`

Validacoes minimas:

1. arvore Git limpa;
2. PHP lint dos arquivos alterados;
3. build Docker;
4. smoke `docker/run-homologation-smoke.php`;
5. `tvsumare_web` healthy;
6. scheduler Up;
7. home HML HTTP 200;
8. sem erro fatal nos logs.

## Producao HostGator

Producao usa deploy seletivo. Nao sincronizar o repositorio inteiro sobre `public_html`.

Preservar sempre antes do deploy:

- backup integral de `public_html`;
- backup seletivo dos arquivos que serao substituidos;
- crontab quando houver alteracao de agendamento.

Nao sobrescrever automaticamente:

- `data/`;
- `config/` e configuracoes locais;
- uploads;
- secrets;
- arquivos gerados pelo cPanel;
- `.htaccess` sem comparacao explicita.

## Radar editorial

Cidades permitidas:

- Sumare;
- Hortolandia;
- Paulinia;
- Nova Odessa;
- Americana;
- Campinas.

Pautas sensiveis devem ir para revisao humana; nao devem ser descartadas apenas por sensibilidade.

Materias sem imagem jornalistica confirmada exigem revisao de imagem.

## Cron

Producao esperada:

`0 * * * * /opt/cpanel/ea-php83/root/usr/bin/php -q /home1/cris1649/public_html/admin/cron_radar.php >> /home1/cris1649/logs/tvsumare-cron-radar.log 2>&1`

O cron deve ser idempotente e registrar START/SKIP/END/FATAL.

## Rollback

Se o smoke de producao falhar:

1. interromper novas alteracoes;
2. restaurar primeiro o backup seletivo dos arquivos afetados;
3. validar PHP lint;
4. validar HTTP da home/admin;
5. usar o backup integral apenas se o rollback seletivo for insuficiente;
6. registrar a causa no Git antes de nova tentativa.
