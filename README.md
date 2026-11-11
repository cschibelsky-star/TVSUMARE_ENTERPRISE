# TVSUMARE_ENTERPRISE 3.0

Enterprise News OS da TV Sumaré, mantido pela Vitrine IA Pro.

## Estado atual

Esta é uma aplicação PHP 8.3/Apache operacional, com persistência em arquivos JSON e execução containerizada. O ambiente de homologação e o runtime de produção estão descritos em `AUDIT_STATUS.md`, `OPERATIONS.md` e `RELEASE.md`.

O arquivo `product.json` é o manifesto do produto; ele não substitui a documentação operacional.

## Estrutura canônica

- `index.php`, `noticia.php`, `noticias.php`, `videos.php` e demais arquivos públicos: superfície editorial pública.
- `admin/`: painel autenticado, cron jobs e módulos administrativos. Não duplicar módulos administrativos na raiz.
- `includes/`: bibliotecas compartilhadas de domínio, segurança e integrações.
- `api/` e `routes/`: entradas de integração HTTP protegidas.
- `config/`: configurações de integrações; segredos vêm do ambiente.
- `app/`: ponte preparada para a futura integração Site → Factory; não é o bootstrap do runtime PHP atual.
- `docker/`: Dockerfile auxiliar, scripts de build-time e smoke tests da imagem.
- `tests/`: testes executáveis do domínio editorial.
- `tools/`: utilitários operacionais portáveis, executados sob demanda.
- `assets/`: recursos estáticos públicos.

Detalhes, regras de movimentação e dívida técnica estão em `docs/STRUCTURE.md`.

## Execução local

```bash
docker compose -f docker-compose.vps.yml up --build
```

O serviço web expõe `/health.php`. Dados, uploads, vídeos e logs devem ser montados externamente; não versionar esses diretórios.

## Validação

1. executar lint PHP em todos os arquivos;
2. executar `php tests/editorial_policy_v3_test.php`;
3. construir a imagem Docker, que aplica hardening e executa os smoke tests;
4. validar homologação antes de qualquer deploy seletivo em produção.

Consulte `OPERATIONS.md` e `RELEASE.md` antes de operar cron, dados ou produção.
