# TV Sumaré Enterprise — Runbook de Homologação

## Objetivo

Homologar a nova versão da TV Sumaré Enterprise sem substituir prematuramente o ambiente atual.

## Fluxo oficial

1. Criar backup da versão atual.
2. Publicar a branch de homologação em diretório e domínio separados.
3. Validar PHP, JSON e Docker Compose.
4. Subir n8n com PostgreSQL, Redis e worker.
5. Importar workflows da TV Sumaré.
6. Configurar credenciais fora do GitHub.
7. Executar smoke tests.
8. Importar uma cópia das notícias atuais.
9. Homologar editorial, imagens, vídeos e redes sociais.
10. Corrigir falhas.
11. Executar teste de rollback.
12. Somente depois promover para produção.

## Ambientes recomendados

- Produção atual: `https://tvsumare.com.br`
- Homologação portal: `https://homolog.tvsumare.com.br`
- Automação: `https://automacoes.vitrineiapro.com.br`

## Critérios editoriais

- Nenhuma notícia duplicada na Home.
- Destaques limitados às matérias elegíveis e atuais.
- Notícias antigas permanecem acessíveis em arquivo e busca, mas não dominam a Home.
- Painel principal nunca aparece vazio por falta de imagem.
- Imagem OG e thumbnail devem existir ou usar fallback oficial.
- Aprovação deve retornar ao painel sem erro.
- Roteiros A e B devem ser visualizáveis antes do envio ao HeyGen.

## Workflows prioritários da TV

- 01 RSS Editorial Pipeline
- 02 News to Instagram
- 03 News to HeyGen
- 04 Video Distribution
- 05 Daily Monitoring
- 07 Global Error Handler
- 08 Infrastructure Heartbeat
- 10 AI Router
- 11 Provider Adapter
- 28 Observability Event Router
- 31 SLA Alert Cost Analytics

Os demais workflows de infraestrutura permanecem disponíveis, mas não devem ser ativados sem necessidade da homologação da TV.

## Aprovação final

A promoção para produção exige evidências dos seguintes fluxos:

- notícia capturada → régua → revisão → aprovação → Home;
- notícia aprovada → roteiro A/B → HeyGen → TV Play;
- matéria/vídeo → Instagram com ID real de publicação;
- falha simulada → retry → telemetria → DLQ quando aplicável;
- backup → deploy → health check → rollback testado.
