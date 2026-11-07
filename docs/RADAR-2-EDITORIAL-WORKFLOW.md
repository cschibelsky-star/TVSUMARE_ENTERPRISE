# Contrato editorial Radar 2.0 — homologação

## Separação de responsabilidades

1. **Radar (captação)**: coleta, deduplicação, triagem, classificação e justificativa do score. Nunca publica.
2. **Editor IA (preparação)**: transforma candidatos em rascunhos, mantém URL de origem, data, crédito de imagem e histórico. Não aprova automaticamente.
3. **Aprovações (decisão humana)**: exibe somente matérias preparadas e validadas; oferece prévia integral, aprovar para fila, devolver ao Editor e descartar com motivo.
4. **Publicação (operação separada)**: publica somente itens previamente aprovados por humano, com confirmação e registro idempotente.

## Estados e transições propostas

`captada -> triagem -> em_edicao -> pronta_para_revisao -> aprovada -> publicada`

Transições alternativas: `triagem -> descartada`, `pronta_para_revisao -> devolvida -> em_edicao`, `pronta_para_revisao -> descartada`.

A aprovação NÃO equivale a publicar. É proibido expor ação de publicação direta na tela de revisão. Alterações de estado exigem autorização, token CSRF, persistência atômica, verificação de versão e log editorial.

## Critérios obrigatórios antes de aprovação

- Título, texto jornalístico completo, cidade, categoria e fonte com URL verificável.
- Data da publicação original, ou indicação explícita de ausência e revisão manual.
- Imagem com crédito e licença/proveniência, ou selo visível de imagem ilustrativa.
- Score com justificativa, verificação de duplicidade por URL canônica e similaridade.
- Ausência de bloqueios por matéria antiga, conteúdo institucional ou fonte inconsistente.
- Editor e revisão humana identificáveis; falha em qualquer critério devolve para edição.

## Interface

**Radar:** configurações de coleta, Atualizar Agora, Volume Máximo, limpeza e reprocessamento ficam exclusivamente em Operação do Radar.

**Editor:** campos Título, Subtítulo, Resumo, Cidade, Categoria, Imagem, Crédito, Texto, Fonte, URL, Tags, SEO e textos sociais; botões Salvar rascunho e Enviar para revisão.

**Aprovações:** título, corpo integral, origem, data, imagem e justificativas; botões Aprovar para fila, Devolver para edição, Descartar. Nenhum botão Aprovar e publicar.

## Implementação segura

Este documento é um contrato de mudança, **não** uma migração aplicada. A implementação deve inspecionar os dois fluxos existentes (`admin/radar-regional.php` e `drafts.php`), preservar os JSON existentes, adicionar compatibilidade para estados legados, e executar testes com fixtures isoladas antes de alterar rotas ou ações. Não modificar dados reais de notícias nem fazer deploy de produção.

## Testes de aceite exigidos

- Rascunho incompleto nunca aparece como pronto; item completo aparece uma vez.
- Aprovação move somente para fila; publicação exige ação posterior distinta.
- Clique repetido não gera publicação ou log duplicado.
- Falha de gravação preserva estado anterior.
- Devolver e descartar preservam fonte, histórico e motivo.
- URLs inválidas e imagens ilustrativas não são tratadas como verificadas.
- Fluxos legados e smoke HML continuam operacionais.

Referência: issue #53.
