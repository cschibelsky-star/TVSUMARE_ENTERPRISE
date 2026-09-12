#!/bin/sh
set -eu

# Normaliza somente os volumes persistentes do HML. O container inicia como root,
# mas Apache usa www-data para escrita em runtime. Evita 777 e preserva isolamento.
for dir in /var/www/html/data /var/www/html/logs /var/www/html/uploads /var/www/html/videos; do
  if [ -d "$dir" ]; then
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R u+rwX,g+rwX,o-rwx "$dir" 2>/dev/null || true
  fi
done

exec docker-php-entrypoint "$@"
