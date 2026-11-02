# Referência da Home publicada — PR #43

Registro documental de 28/10/2026, baseado exclusivamente nos dados públicos dos PRs #42 e #43 e na comparação dos respectivos commits no GitHub.

## Referência documentada como homologada e publicada

- PR: https://github.com/cschibelsky-star/TVSUMARE_ENTERPRISE/pull/43
- Branch: `fix/home-regular-production-20261026`.
- Commit de código da restauração: `9c6db7b165c5a95dc295dfb8a87ac170fa3092e1`.
- Base: `d7efe725ffbd3b49f98c1fecbe68e1f40eb4d1cf`.
- Imagem registrada publicamente no PR: `sha256:3629bff1e92640e94ff3e3b42c7495c106de7ea363ad0c4345f2c5b0029a3486`.
- Comportamento registrado: Home regular com matéria em destaque e painel publicitário, preservando a consulta eleitoral em páginas próprias.
- O PR permanece aberto e draft; publicação documentada não equivale a merge.
- A associação do commit à imagem é documental, sem atestação de build apresentada neste registro.

O commit acima identifica o código da restauração. Commits posteriores exclusivamente documentais nesta branch não representam uma nova imagem publicada.

## Preservação da Home na atualização do Radar

PR #42: https://github.com/cschibelsky-star/TVSUMARE_ENTERPRISE/pull/42

A branch base do #43 é `fix/radar-backlog-effective-batch-20261020`, usada pelo #42. Contudo, em 28/10/2026, a comparação entre o commit da Home e o head do #42 (`2cb8242eb77d36f4b24c61a684ec4f5fb4af79c5`) apresentou históricos divergentes: o Radar está três commits à frente e um atrás, com ancestral comum `d7efe725ffbd3b49f98c1fecbe68e1f40eb4d1cf`.

O head do #42 não contém o commit de restauração da Home. Uma futura atualização deve incorporar ambos os trabalhos em uma candidata revisada antes da promoção da árvore ou imagem completa.

Gate operacional da próxima atualização:

1. Confirmar que a candidata preserva a restauração do #43 e as correções desejadas do #42.
2. Revisar o diff final da Home, publicidade e páginas eleitorais.
3. Validar CI e HML, incluindo matéria em destaque, painel publicitário e ausência da chamada eleitoral na capa.
4. Registrar o commit candidato e a imagem efetivamente homologada.
5. Preservar dados, uploads, vídeos e configurações existentes.

Este gate é documental; não constitui trava automática de CI ou proteção de branch.

## Limite deste registro

A descrição original do #43 informa que o cadastro oficial ainda apontava para `feature/tvsumare-boletim-social`. Este documento estabelece a referência pública da restauração e preserva o histórico, mas não comprova nem executa a correção do cadastro operacional. Esse alinhamento deve ser confirmado por releitura no sistema responsável.

Esta alteração é exclusivamente documental e não executa implantação, merge ou reinício.
