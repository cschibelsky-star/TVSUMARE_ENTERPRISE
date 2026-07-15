#!/usr/bin/env bash
set -euo pipefail

MASTER_API_URL="${VITRINE_MASTER_API_URL:-https://app.vitrineiapro.com.br/api}"
TVSUMARE_API_URL="${TVSUMARE_API_URL:-https://tvsumare.com.br/api}"
FLOW_URL="${N8N_WEBHOOK_URL:-https://automacoes.vitrineiapro.com.br}"
MASTER_TOKEN="${VITRINE_MASTER_API_TOKEN:-}"
TVSUMARE_TOKEN="${TVSUMARE_API_TOKEN:-}"

failures=0

request() {
  local name="$1"
  local method="$2"
  local url="$3"
  local token="$4"
  local body="${5:-}"
  local code

  if [[ -n "$body" ]]; then
    code=$(curl -sS -o "/tmp/${name}.json" -w "%{http_code}" -X "$method" \
      -H "Authorization: Bearer ${token}" \
      -H "Content-Type: application/json" \
      --data "$body" "$url" || true)
  else
    code=$(curl -sS -o "/tmp/${name}.json" -w "%{http_code}" -X "$method" \
      -H "Authorization: Bearer ${token}" "$url" || true)
  fi

  if [[ "$code" =~ ^2 ]]; then
    echo "[OK] ${name} (${code})"
  else
    echo "[ERRO] ${name} (${code}) — $(cat "/tmp/${name}.json" 2>/dev/null || true)"
    failures=$((failures + 1))
  fi
}

request "tvsumare_health" GET "${TVSUMARE_API_URL}/health/status.php" "$TVSUMARE_TOKEN"
request "flow_callback" POST "${MASTER_API_URL}/flow/events/callback" "$MASTER_TOKEN" '{"event":"SMOKE_TEST","company_id":1,"request_id":"smoke-test-001","timestamp":"2026-07-12T12:00:00-03:00","payload":{"source":"integration-smoke-test"}}'
request "flow_telemetry" POST "${MASTER_API_URL}/flow/telemetry" "$MASTER_TOKEN" '{"company_id":1,"workflow_id":"WF-SMOKE","execution_id":"EXEC-SMOKE","trace_id":"TRACE-SMOKE","correlation_id":"CORR-SMOKE","status":"completed","duration_ms":1,"payload":{"source":"integration-smoke-test"}}'
request "n8n_health" GET "${FLOW_URL%/}/healthz" ""

if (( failures > 0 )); then
  echo "Smoke test concluído com ${failures} falha(s)."
  exit 1
fi

echo "Smoke test de integração concluído com sucesso."
