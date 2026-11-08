#!/bin/sh
# Read-only gate. Inspects ONLY Compose service labels and the four data mount source paths.
# Never reads .Config.Env, file contents, Docker logs, API keys, or mounted secrets.
set -eu

hml=tvsumare_web
prod=tvsumare_web_prod

if ! command -v docker >/dev/null 2>&1 || ! command -v readlink >/dev/null 2>&1 || ! command -v stat >/dev/null 2>&1; then
  echo 'TVSUMARE_MOUNT_GATE=UNKNOWN REASON=prerequisite_unavailable'
  exit 2
fi

get_service() {
  docker inspect --format '{{index .Config.Labels "com.docker.compose.service"}}' "$1" 2>/dev/null
}

hml_service=$(get_service "$hml") || {
  echo 'TVSUMARE_MOUNT_GATE=UNKNOWN REASON=hml_metadata_unavailable'
  exit 2
}
prod_service=$(get_service "$prod") || {
  echo 'TVSUMARE_MOUNT_GATE=UNKNOWN REASON=prod_metadata_unavailable'
  exit 2
}
if [ "$hml_service" != web ] || [ "$prod_service" != web_prod ]; then
  echo 'TVSUMARE_MOUNT_GATE=UNKNOWN REASON=unexpected_compose_service'
  exit 2
fi

get_source() {
  docker inspect --format "{{range .Mounts}}{{if eq .Destination \"$2\"}}{{.Source}}{{end}}{{end}}" "$1" 2>/dev/null
}

result=0
for kind in data uploads videos logs; do
  dest="/var/www/html/$kind"
  hml_src=$(get_source "$hml" "$dest") || hml_src=''
  prod_src=$(get_source "$prod" "$dest") || prod_src=''
  if [ -z "$hml_src" ] || [ -z "$prod_src" ] || [ ! -d "$hml_src" ] || [ ! -d "$prod_src" ]; then
    printf 'MOUNT=%s RESULT=UNKNOWN_MISSING_OR_UNREADABLE\n' "$kind"
    result=2
    continue
  fi
  hml_real=$(readlink -f "$hml_src") || hml_real=''
  prod_real=$(readlink -f "$prod_src") || prod_real=''
  hml_inode=$(stat -Lc '%d:%i' "$hml_src" 2>/dev/null) || hml_inode=''
  prod_inode=$(stat -Lc '%d:%i' "$prod_src" 2>/dev/null) || prod_inode=''
  if [ -z "$hml_real" ] || [ -z "$prod_real" ] || [ -z "$hml_inode" ] || [ -z "$prod_inode" ]; then
    printf 'MOUNT=%s RESULT=UNKNOWN_PATH_METADATA\n' "$kind"
    result=2
  elif [ "$hml_real" = "$prod_real" ] || [ "$hml_inode" = "$prod_inode" ]; then
    printf 'MOUNT=%s RESULT=BLOCKED_SHARED_DIRECTORY\n' "$kind"
    result=1
  else
    printf 'MOUNT=%s RESULT=PASS_DISTINCT_DIRECTORY\n' "$kind"
  fi
done

case "$result" in
  0) echo 'TVSUMARE_MOUNT_GATE=PASS_PATH_ISOLATION_ONLY' ;;
  1) echo 'TVSUMARE_MOUNT_GATE=BLOCKED_SHARED_DIRECTORY' ;;
  *) echo 'TVSUMARE_MOUNT_GATE=UNKNOWN' ;;
esac
exit "$result"
