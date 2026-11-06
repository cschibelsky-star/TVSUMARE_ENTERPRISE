# Estrutura do repositório

## Objetivo

Este documento define onde cada tipo de código deve viver. A regra principal é manter uma única implementação por módulo e impedir que cópias administrativas antigas voltem para a raiz.

## Camadas

| Caminho | Responsabilidade | Exposição |
| --- | --- | --- |
| `*.php` na raiz | páginas públicas, feeds, sitemap, bootstrap e bibliotecas de compatibilidade | pública ou filtrada pelo `.htaccess` |
| `admin/` | autenticação, painel, cron e operações editoriais | autenticada ou CLI |
| `includes/` | funções compartilhadas e integrações | indireta |
| `api/` | callbacks externos | somente endpoints previstos |
| `routes/` | definição de integração Site → Factory | indireta |
| `config/` | configuração não secreta de integrações | nunca pública |
| `bootstrap/` | carregamento de configuração e autenticação compartilhada | nunca pública |
| `app/` | ponte futura para o runtime Factory | não carregada pelo site atual |
| `docker/` | build-time, hardening e smoke tests | nunca pública |
| `tests/` | testes de domínio | nunca pública |
| `tools/` | saneamento e auditorias manuais | nunca pública |
| `assets/` | recursos estáticos | pública |

## Regras de organização

1. Novos módulos administrativos entram em `admin/`, mesmo quando também existe uma página pública relacionada.
2. Código reutilizado por mais de uma página entra em `includes/`; não copiar funções entre `admin/` e a raiz.
3. Segredos e dados vivos ficam em variáveis de ambiente e volumes externos (`data/`, `uploads/`, `videos/` e `logs/`).
4. O carregamento comum de configuração e autenticação fica em `bootstrap/`; esses arquivos não são endpoints HTTP.
5. Scripts que alteram a imagem durante o build ficam em `docker/`; scripts de operação manual ficam em `tools/`.
6. Mudanças de estrutura precisam atualizar `.htaccess`, `Dockerfile`, `docker-compose.vps.yml`, CI e documentação no mesmo change set.
7. Não criar novos arquivos administrativos na raiz. Os caminhos legados removidos continuam bloqueados pelo `.htaccess` para proteger instalações antigas que ainda possam conter cópias.

## Compatibilidades mantidas

- `config/runtime.php` é a única implementação da configuração de ambiente. `config.php` e `admin/config.php` são shims de compatibilidade e não devem receber configuração nova.
- `monitor_lib.php` é uma biblioteca compartilhada histórica usada pelo shim `admin/monitor_lib.php`.
- `gemini.php` permanece como compatibilidade do baseline legado; novos helpers de integração devem ser extraídos para `includes/` quando houver refatoração dedicada.

Esses arquivos não são páginas administrativas e não devem receber novas telas ou regras de negócio.

## Auditoria realizada em 2026-11-05

- A raiz continha cópias de módulos administrativos que já eram bloqueadas pelo `.htaccess` e divergiam dos arquivos canônicos em `admin/`.
- `cron_monitor.php` e `cron_radar.php` na raiz eram cópias legadas; além de não serem a entrada documentada, usavam caminhos relativos inadequados para a raiz do projeto.
- A implementação executada pelo Docker e pelo cron é a de `admin/cron_radar.php` e `admin/cron_monitor.php`.
- A documentação principal dizia que o repositório era um scaffold, embora o runtime, Docker, health check e homologação já estivessem versionados.
- As notas `LEIA-ME*.txt` foram preservadas em `docs/archive/` para consulta histórica, fora da superfície da aplicação.
- O PHP e o Docker não estão instalados neste ambiente Windows; lint e smoke local devem ser executados pela CI ou dentro da imagem Docker.

## Dívida técnica conhecida

- O diretório `app/` contém uma ponte Laravel incompleta e não possui `composer.json`; ele deve ser tratado como preparação futura, não como runtime atual.
- A aplicação ainda usa arquivos JSON em vez de banco relacional. Isso é uma decisão do baseline atual e deve continuar explícito em `product.json`.
- A consolidação de `gemini.php` e `monitor_lib.php` em `includes/` deve ser feita em uma mudança isolada, com atualização dos scripts de build e validação de produção.
- `admin/auth.php` permanece como shim compatível; a implementação de sessão e autenticação agora vive em `bootstrap/admin.php`.
