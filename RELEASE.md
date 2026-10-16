# TV Sumare — Release e Promocao

## Regra principal

Nenhum release deve sair de uma arvore Git suja. GitHub e a fonte de verdade do codigo.

## Gate de promocao

Para promover uma versao:

1. branch candidata limpa e sincronizada com o remoto;
2. CI verde;
3. build HML concluido;
4. smoke de homologacao aprovado;
5. `tvsumare_web` healthy;
6. `tvsumare_radar_scheduler` Up;
7. home HML HTTP 200;
8. revisao dos arquivos que efetivamente irao para HostGator;
9. backup integral e seletivo de producao;
10. deploy seletivo;
11. PHP lint em producao;
12. smoke HTTP publico;
13. validacao do cron e logs;
14. registro do SHA publicado.

## Deploy HostGator

Nao usar `rsync --delete` nem copiar o repositorio inteiro para `public_html`.

Copiar apenas os arquivos de runtime aprovados no release. Dados vivos, configuracoes locais, uploads e secrets ficam fora do pacote de codigo.

## Saneamento editorial

Mudancas em `data/` sao operacoes de conteudo, nao deploy de codigo. Devem ter:

- snapshot antes da mudanca;
- relatorio dos registros afetados;
- modo dry-run sempre que possivel;
- quarentena preferida a exclusao definitiva;
- validacao posterior da home/ticker/editorias.

## Rollback

O rollback deve restaurar o menor escopo possivel. Prioridade:

1. arquivos seletivos;
2. estado editorial/quarentena;
3. backup integral apenas como ultimo recurso.

Nunca usar reset/clean do repositorio como mecanismo de rollback de producao.
