#!/bin/sh
set -eu
base=$(CDPATH= cd "$(dirname "$0")/.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
mkdir -p "$tmp/mockbin"
for d in data uploads videos logs; do mkdir -p "$tmp/hml/$d" "$tmp/prod/$d"; done
cat > "$tmp/mockbin/docker" <<'MOCK'
#!/bin/sh
[ "$1" = inspect ] && [ "$2" = --format ] || exit 10
fmt=$3
container=$4
case "$fmt" in
  *com.docker.compose.service*)
    case "$container" in
      tvsumare_web) echo web ;;
      tvsumare_web_prod) echo web_prod ;;
      *) exit 11 ;;
    esac
    exit 0 ;;
esac
case "$fmt" in
  */var/www/html/data*) d=data ;;
  */var/www/html/uploads*) d=uploads ;;
  */var/www/html/videos*) d=videos ;;
  */var/www/html/logs*) d=logs ;;
  *) exit 12 ;;
esac
case "$container" in
  tvsumare_web) printf '%s/%s\n' "$TVS_TEST_HML" "$d" ;;
  tvsumare_web_prod)
    if [ "${TVS_TEST_MISSING:-}" = "$d" ]; then exit 0; fi
    printf '%s/%s\n' "$TVS_TEST_PROD" "$d" ;;
  *) exit 13 ;;
esac
MOCK
chmod +x "$tmp/mockbin/docker"
export TVS_TEST_HML="$tmp/hml" TVS_TEST_PROD="$tmp/prod"
export PATH="$tmp/mockbin:$PATH"
output=$(sh "$base/tools/tvsumare_mount_gate.sh")
printf '%s\n' "$output" | grep -q 'TVSUMARE_MOUNT_GATE=PASS_PATH_ISOLATION_ONLY'
TVS_TEST_PROD="$tmp/hml"; export TVS_TEST_PROD
if output=$(sh "$base/tools/tvsumare_mount_gate.sh"); then echo 'FAIL: shared mounts must not pass'; exit 1; else
  printf '%s\n' "$output" | grep -q 'TVSUMARE_MOUNT_GATE=BLOCKED_SHARED_DIRECTORY'
fi
TVS_TEST_PROD="$tmp/prod"; TVS_TEST_MISSING=logs; export TVS_TEST_PROD TVS_TEST_MISSING
if output=$(sh "$base/tools/tvsumare_mount_gate.sh"); then echo 'FAIL: missing mount must not pass'; exit 1; else
  printf '%s\n' "$output" | grep -q 'TVSUMARE_MOUNT_GATE=UNKNOWN'
fi
echo 'TVSUMARE_MOUNT_GATE_TEST=PASS'
