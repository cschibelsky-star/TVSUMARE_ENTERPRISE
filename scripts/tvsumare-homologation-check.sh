#!/usr/bin/env bash
set -euo pipefail

: "${TVSUMARE_HOMOLOG_URL:=https://homolog.tvsumare.com.br}"
: "${TVSUMARE_API_URL:=${TVSUMARE_HOMOLOG_URL}/api}"
: "${N8N_URL:=https://automacoes.vitrineiapro.com.br}"

failures=0
check() {
  local name="$1"; shift
  echo "[TESTE] ${name}"
  if "$@"; then echo "[OK] ${name}"; else echo "[FALHA] ${name}"; failures=$((failures+1)); fi
}

check "Home de homologação responde" curl -fsS --max-time 20 "${TVSUMARE_HOMOLOG_URL}/"
check "Admin de homologação responde" curl -fsS --max-time 20 "${TVSUMARE_HOMOLOG_URL}/admin/"
check "Health API responde" curl -fsS --max-time 20 "${TVSUMARE_API_URL}/health/status.php"
check "n8n responde" curl -fsS --max-time 20 "${N8N_URL}/healthz"

if [[ -n "${TVSUMARE_API_TOKEN:-}" ]]; then
  check "Monitoramento autenticado" curl -fsS --max-time 20 \
    -H "Authorization: Bearer ${TVSUMARE_API_TOKEN}" \
    -H "Content-Type: application/json" \
    -d '{"source":"homologation","status":"ok"}' \
    "${TVSUMARE_API_URL}/automation/monitoring-log.php"
else
  echo "[AVISO] TVSUMARE_API_TOKEN ausente; teste autenticado ignorado."
fi

if (( failures > 0 )); then
  echo "Homologação técnica reprovada: ${failures} falha(s)."
  exit 1
fi

echo "Homologação técnica básica aprovada. Continuar com testes editoriais, HeyGen, TV Play e Meta."
