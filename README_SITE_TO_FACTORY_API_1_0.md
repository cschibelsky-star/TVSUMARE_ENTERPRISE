# SITE TO FACTORY API 1.0

## Objetivo

Conectar o site comercial ao Centro Operacional / Factory.

Fluxo:

```txt
Site comercial
↓
POST /api/site/factory/intake
↓
CommercialFactoryIntakeService
↓
Factory
↓
Projeto criado
↓
Status ready_for_homologation
```

## Instalação

Extraia na raiz:

```bash
cd /home1/cris1649/vitrine-ai-pro
python3 site_to_factory_api_bootstrap.py
composer dump-autoload
php artisan optimize:clear
php artisan route:list | grep "site/factory"
```

## Configurar token

Adicione no `.env`:

```env
SITE_FACTORY_TOKEN=troque-este-token
```

Depois:

```bash
php artisan config:clear
```

## Teste via terminal

```bash
curl -X POST "https://app.vitrineiapro.com.br/api/site/factory/intake" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Site-Factory-Token: troque-este-token" \
  -d '{
    "product":"TV Digital Enterprise",
    "client":"Cliente Site Teste",
    "plan":"enterprise",
    "email":"cliente@teste.com",
    "domain":"cliente-site.tv.br"
  }'
```

## Teste interno

```bash
php artisan site:factory-test
php artisan commercial:factory-status
```

## Endpoint

```txt
POST /api/site/factory/intake
```

Payload:

```json
{
  "product": "TV Digital Enterprise",
  "client": "Nome do Cliente",
  "plan": "enterprise",
  "email": "cliente@email.com",
  "domain": "cliente.com.br",
  "source": "site"
}
```
