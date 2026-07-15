# Central de Automação n8n — Vitrine IA Pro

## Função na arquitetura

O n8n será o motor de execução das automações. O Centro Operacional Master será o painel de gestão, configuração e monitoramento.

```text
Centro Operacional Master
        ↓ configura / monitora
n8n Central de Automação
        ↓ executa fluxos
TV Sumaré / Social Media / Guia Digital / SISMED / GovTech
```

## O que fica no PHP da TV Sumaré

- Portal público.
- Painel administrativo.
- Banco de notícias.
- Gestão editorial.
- Aprovação humana.
- Exibição da Home.
- TV Play.
- APIs internas.

## O que vai para o n8n

- Captura agendada de RSS.
- Chamadas Gemini/OpenAI.
- Busca de imagem.
- Geração de vídeo HeyGen.
- Publicação Instagram/Facebook/YouTube.
- Alertas ao editor.
- Newsletter.
- Backup.
- Monitoramento de erros.
- Relatórios.

## Primeiros fluxos oficiais

1. `01-rss-editorial-pipeline`
   - Captura RSS.
   - Remove duplicadas.
   - Classifica por IA.
   - Envia para a fila editorial da TV Sumaré.

2. `02-news-to-instagram`
   - Recebe notícia aprovada.
   - Cria legenda.
   - Publica ou prepara post para Instagram.

3. `03-news-to-heygen`
   - Recebe matéria aprovada para vídeo.
   - Gera dois roteiros.
   - Aguarda escolha humana.
   - Envia roteiro escolhido ao HeyGen.

4. `04-video-distribution`
   - Recebe vídeo pronto.
   - Publica TV Play.
   - Publica YouTube.
   - Gera chamada para redes.

5. `05-daily-monitoring`
   - Verifica fontes.
   - Verifica APIs.
   - Envia resumo de falhas.

## Regra principal

Nenhum fluxo deve publicar conteúdo sensível sem aprovação humana, exceto fluxos explicitamente marcados como automáticos e de baixo risco.
