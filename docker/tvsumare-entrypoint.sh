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

# Recupera somente assets já concluídos e corrige jobs HeyGen que tenham sido
# classificados incorretamente como Centro IA. Esta rotina não inicia geração.
if [ -f /var/www/html/includes/video_ai_helper.php ]; then
  php -r 'require "/var/www/html/config.php"; require "/var/www/html/includes/video_ai_helper.php"; if (function_exists("tvp_recover_completed_media_jobs")) { $r=tvp_recover_completed_media_jobs(); fwrite(STDOUT, "VIDEO_RECOVERY=".json_encode($r, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL); }' || true
  for dir in /var/www/html/data /var/www/html/uploads; do
    if [ -d "$dir" ]; then
      chown -R www-data:www-data "$dir" 2>/dev/null || true
      chmod -R u+rwX,g+rwX,o-rwx "$dir" 2>/dev/null || true
    fi
  done
fi


# Validação não destrutiva do volume de dados com as permissões do Apache.
# Executa uma vez na inicialização e remove o arquivo temporário do teste.
if su -s /bin/sh -c 'php -r '\''$dir="/var/www/html/data"; $probe=@tempnam($dir,".tvs-monitor-probe-"); if ($probe===false) {fwrite(STDERR,"MONITOR_DATA_WRITE=FAIL create\\n");exit(1);} $payload="tvs-monitor-volume-ok"; $ok=@file_put_contents($probe,$payload,LOCK_EX)===strlen($payload) && @file_get_contents($probe)===$payload; @unlink($probe); if (!$ok) {fwrite(STDERR,"MONITOR_DATA_WRITE=FAIL write-read\\n");exit(1);} echo "MONITOR_DATA_WRITE=PASS path=/var/www/html/data\\n";'\'' ' www-data; then
  :
else
  echo "MONITOR_DATA_WRITE=FAIL check data volume permissions" >&2
fi

exec docker-php-entrypoint "$@"
