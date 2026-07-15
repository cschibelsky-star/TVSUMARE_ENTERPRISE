#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-infra/n8n/docker-compose.production.yml}"
ENV_FILE="${ENV_FILE:-infra/n8n/.env}"
WORKFLOW_DIR="${WORKFLOW_DIR:-n8n/workflows}"
CONTAINER_SERVICE="${CONTAINER_SERVICE:-n8n}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Arquivo de ambiente ausente: $ENV_FILE"
  exit 1
fi

if [[ ! -d "$WORKFLOW_DIR" ]]; then
  echo "Diretório de workflows ausente: $WORKFLOW_DIR"
  exit 1
fi

count=0
for file in "$WORKFLOW_DIR"/*.json; do
  [[ -e "$file" ]] || continue
  echo "Importando $(basename "$file")"
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T "$CONTAINER_SERVICE" \
    n8n import:workflow --input="/opt/vitrine-flow/workflows/$(basename "$file")"
  count=$((count + 1))
done

echo "Importação concluída: ${count} workflow(s)."
echo "Os workflows permanecem inativos até homologação e ativação explícita."
