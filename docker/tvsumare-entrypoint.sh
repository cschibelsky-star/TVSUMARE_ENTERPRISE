#!/bin/sh
set -eu

# Normaliza somente os volumes persistentes do HML. O container inicia como root,
# mas Apache usa www-data para escrita em runtime. Evita 777 e preserva isolamento.
mkdir -p /var/www/html/uploads/.tmp
for dir in /var/www/html/data /var/www/html/logs /var/www/html/uploads /var/www/html/uploads/.tmp /var/www/html/videos; do
  if [ -d "$dir" ]; then
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R u+rwX,g+rwX,o-rwx "$dir" 2>/dev/null || true
  fi
done

# Recupera somente assets VEO já concluídos. Não inicia nenhuma nova geração.
if [ -f /var/www/html/includes/video_ai_helper.php ]; then
  php -r 'require "/var/www/html/config.php"; require "/var/www/html/includes/video_ai_helper.php"; if (function_exists("tvp_recover_completed_veo_jobs")) { $r=tvp_recover_completed_veo_jobs(); fwrite(STDOUT, "VEO_RECOVERY=".json_encode($r, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL); }' || true
fi

exec docker-php-entrypoint "$@"
