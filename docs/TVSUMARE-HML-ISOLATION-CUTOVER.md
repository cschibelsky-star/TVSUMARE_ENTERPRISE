# TV Sumaré — Plano controlado de isolamento de homologação

Data: 2026-11-08. Issue #65. Este PR apenas prepara a correção do P0; **NÃO autoriza deploy**, mudança de volumes ativos, cópia de notícias reais, alteração de produção ou merge.

## Diagnóstico real (SSH, fornecido pelo responsável)

`tvsumare_web` e `tvsumare_web_prod` montam, simultaneamente, os quatro MESMOS caminhos RW:
`/srv/tvsumare/shared/{data,uploads,videos,logs}` em `/var/www/html/{data,uploads,videos,logs}`.
A falha de isolamento está **comprovada**. Nenhuma perda de dados foi demonstrada.

## Alteração proposta, somente para HML

- Em `docker-compose.vps.yml`, o serviço `web` e o serviço `radar_scheduler` passam a apontar para `/srv/tvsumare/hml-isolated/{data,uploads,videos,logs}`, cada um conforme as necessidades de seu container.
- Diretórios precisam ser criados/provisionados explicitamente em janela de manutenção autorizada. `bind.create_host_path=false` impede que Docker crie diretórios automaticamente em caminho errado.
- `radar_scheduler` torna-se **opt-in**, sob profile `hml-radar-opt-in`, para evitar coleta, publicação ou consumo involuntários.
- `env_file` do HML passa a referir um arquivo **exclusivo**, `.env.hml.runtime`, ainda inexistente, não versionado. A ausência deve impedir o boot, até sua preparação segura. Não copiar `.env.runtime` nem credenciais reais para HML.
- Integrações externas e chamadas com custo ficam explicitamente desabilitadas nas variáveis de ambiente da homologação por padrão. Se a aplicação puder ler chaves persistidas em outros locais, auditar antes de habilitar testes.
- A configuração de produção **não é alterada** por este PR, mas o runtime PROD é uma implantação separada que precisa ser inventariada antes do cutover HML.

## Gate obrigatório antes de qualquer implantação (não executar automaticamente)

1. Validar backup geral que contém `/srv/tvsumare`, índice, checksum e **restauração em destino descartável**. Não restaurar sobre dados ativos.
2. Capturar health e configuração de volumes reais atual de HML e PROD; não coletar `.Config.Env` ou secrets. Documentar imagem e versão em execução.
3. Confirmar que `/srv/tvsumare/hml-isolated` está fora dos bind mounts de PROD e que não possui symlink apontando para `shared`. Preparar diretórios com permissões apropriadas, sem dados reais.
4. Criar `.env.hml.runtime` minimalista, credenciais distintas de HML (não publicar na issue nem no GitHub), **sem chaves pagas de APIs**, com permissões restritas e políticas de autenticação apropriadas.
5. Simular `docker compose config` e validar `tests/hml_isolation_compose_test.py` na revisão; capturar paths e permissões sem segredos.
6. Com autorização específica de cutover HML, atuar **somente em `web`** e deixar `radar_scheduler` parado/opt-in. Verificar antes que o Compose alvo não alcança `web_prod`.
7. Reinspecionar mounts efetivos de `tvsumare_web` e `tvsumare_web_prod`: todos os quatro diretórios devem ser distintos, sem symlinks/identidade de inode; validar HTTP health e login HML com credenciais próprias; observar logs sem expor tokens.
8. Verificar que notícias, vídeos e imagens **na produção** permanecem intactos; execução editoral/HeyGen real somente em etapa posterior, com autorização para gastos/publicação se aplicável.

## Reversão (planejada, não executada)

Se HML não iniciar ou sua saúde regredir, parar apenas a transição de HML. **Não iniciar HML novamente com os mounts compartilhados**, pois isso reintroduz P0. Reverter para imagem/configuração de homologação isolada anterior, caso exista, ou manter HML desligada até corrigir o ambiente. Nunca restaurar `shared/*` sobrescrevendo dados de produção.

## Critérios de aceite

- CI da configuração resolvida PASS, sem segredos, e revisado por outro agente.
- Backup restaurável em ambiente isolado; garantia de não impacto no PROD.
- Prova por inspeção dos mounts efetivos (diretórios de HML diferentes dos de PROD).
- Testes funcionais e publicação continuam **bloqueados** até esses critérios passarem.
- PR draft, sem merge/deploy sem autorização separada.


## Revisão independente Claude Opus 5 — 08/11/2026 (Avello)

Consulta técnica realizada após CI da branch aprovado (run 42532405979). **Não substitui homologação real.** Principais bloqueios e decisões:

1. **Namespace Docker / colisão de projetos:** `name: tvsumare` ainda coincide com o projeto Compose existente. Antes do cutover, identificar os labels `com.docker.compose.project` e `com.docker.compose.service` em HML e PROD, sem ler `.Config.Env`; provar que a atualização direcionada a `web` não gerenciará `web_prod`. Uma migração de namespace para `tvsumare_hml` pode ser alternativa, mas exige plano próprio para nomes, Traefik, serviços, redes e rollback; **não modificar o nome cegamente**.
2. **Credenciais:** campos explícitos `environment: OPENAI_API_KEY: ''`, `HEYGEN_API_KEY: ''`, `GEMINI_API_KEY: ''`, `SITE_FACTORY_TOKEN: ''` foram mantidos vazios de propósito; **não remover** esses overrides sem revisão, pois `env_file` pode carregar credenciais. Confirmar que `.env.hml.runtime` não herda segredos nem configurações mutáveis de produção e que os helpers PHP não consultam configurações legadas ou globais persistidas.
3. **Diretórios HML vazios:** verificar quais arquivos JSON/estrutura mínima são necessários ao bootstrap, sem semear cópias operacionais de produção; usar fixtures sintéticas e validar PHP health/admin com identidade distinta.
4. **Permissões e bind mounts:** confirmar owner UID/GID e permissão necessária a `www-data`, sem `chmod 777`, sem `chown -R` em `shared/*`. O entrypoint aplica `chown -R` a diretórios montados; só é admissível após comprovar origem HML exclusiva.
5. **Redes Docker:** `vitrine_net` e `n8n-traefik_app_network` podem permitir reachability à produção. Auditar dependências e regras de egress; separação do filesystem **não comprova isolamento L3**.
6. **Scheduler e concorrência:** `profiles: [hml-radar-opt-in]` impede ativação padrão, porém ativação explícita/wildcard continua possível. Não iniciar o scheduler até proteger todas as gravações JSON concorrentes e provar ausência de chamadas de API/publicação.

### Verificações já comprovadas × ainda pendentes

- **Comprovado no CI** do commit `865274a982960d23315840b0d99c5ee314ab498f`: workflow `TV Sumare CI` run `42532405979` `completed/success`; etapas `php-quality`, validação Compose HML incluindo scheduler desativado por padrão, e build/smoke Docker `success`.
- **Comprovado em ambiente operacional anterior:** HML e PROD compartilham quatro bind mounts RW; falha P0 reproduzida com leitura de metadados Docker fornecida pelo responsável.
- **Comprovado por inventário V5:** backup geral VPS está habilitado e inclui `/srv/tvsumare`, snapshot `2026-11-07T171312Z`, 2.254.843.540 bytes; `project_backup_status(tvsumare)=backup_not_configured` para backup específico.
- **Ainda NÃO comprovado:** verificação de checksum recalculado, restauração efetiva, permissões/estrutura do futuro HML, isolamento de rede, ausência de credenciais em outras fontes, compatibilidade das rotas e isolamento real após cutover.
- **Operação proibida nesta fase:** deploy, merge, restart, criação ou cópia de dados ativos, `docker compose down`, testes com escrita em HML ou ativação do scheduler. O próximo passo é um pré-flight auditado, seguido de aprovação específica antes de qualquer cutover.


## Verificação amostral de backup — sem modificar dados ativos

Novo utilitário `tools/tvsumare_backup_sample_restore.py` valida o checksum SHA-256 informado, abre o backup em modo **somente leitura** e restaura até três **arquivos JSON estritamente permitidos** de `srv/tvsumare/shared/data/` em um diretório temporário privado sob `/var/tmp`. O diretório é automaticamente descartado. Nenhum caminho de origem é sobrescrito e o conteúdo dos JSONs não é exibido. O utilitário rejeita membros que não sejam arquivos regulares, dados inválidos e arquivos grandes; não usa `extractall`. Testes sintéticos são executados por `tests/tvsumare_backup_sample_restore_test.py` no CI.

Execução no host autorizada somente após conferir espaço livre em `/var/tmp`, que o backup está estável, e que a leitura do arquivo não prejudicará I/O dos serviços:

```bash
python3 /srv/tvsumare/repository/tools/tvsumare_backup_sample_restore.py \
  --archive /srv/vitrine/backups/vps/vps-filesystem-20261107T171312Z.tar.gz \
  --expected-sha256 1a6e502d3ccdfe9b46c9a4d5d51381baf639004f0f04bcb33ec6f9890f88a558
```

**Atenção:** o script só estará disponível nesse caminho depois de uma entrega isolada e autorizada. Não fazer `git pull` na árvore suja para obtê-lo. Mesmo o resultado `PASS_SAMPLED_ONLY` **não comprova recuperação completa** do ambiente e não autoriza a troca dos mounts. `INCONCLUSIVE` deve manter Gate 0 bloqueado. Verificar que o formato do backup realmente contém os caminhos permitidos; caso contrário adaptar allowlist em PR revisado, sem ampliar extração.
