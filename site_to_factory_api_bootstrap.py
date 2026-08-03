from pathlib import Path

required = [
    Path("config/site_factory.php"),
    Path("app/Http/Requests/SiteFactoryIntakeRequest.php"),
    Path("app/Http/Controllers/Api/SiteFactoryIntakeController.php"),
    Path("routes/site_factory_api.php"),
    Path("app/Console/Commands/SiteFactoryApiTestCommand.php"),
]

missing = [str(p) for p in required if not p.exists()]
if missing:
    raise SystemExit("Arquivos obrigatórios ausentes: " + ", ".join(missing))

api = Path("routes/api.php")
if not api.exists():
    api.write_text("<?php\n\n")

text = api.read_text()
include_line = "require __DIR__.'/site_factory_api.php';"

if include_line not in text:
    if not text.strip().startswith("<?php"):
        text = "<?php\n\n" + text
    text = text.rstrip() + "\n\n" + include_line + "\n"
    api.write_text(text)
    print("Rota site_factory_api.php incluída em routes/api.php")

print("Site to Factory API 1.0 aplicado.")
print("Configure SITE_FACTORY_TOKEN no .env")
print("Rode: composer dump-autoload && php artisan optimize:clear && php artisan route:list | grep 'site/factory'")
