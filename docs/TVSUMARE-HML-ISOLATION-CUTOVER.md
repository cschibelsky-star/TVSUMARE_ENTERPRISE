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
