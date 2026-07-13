#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REGISTRY="${ROOT_DIR}/config/tvsumare.workflows.production.json"
WORKFLOW_DIR="${ROOT_DIR}/n8n/workflows"
CONTAINER="${N8N_CONTAINER:-vitrine_ia_pro_n8n}"

command -v jq >/dev/null 2>&1 || { echo "ERRO: jq não instalado."; exit 1; }
docker ps --format '{{.Names}}' | grep -qx "${CONTAINER}" || { echo "ERRO: container ${CONTAINER} não está ativo."; exit 1; }

mapfile -t FILES < <(jq -r '.workflows[].file' "${REGISTRY}")

for file in "${FILES[@]}"; do
  source_file="${WORKFLOW_DIR}/${file}"
  [[ -f "${source_file}" ]] || { echo "ERRO: workflow ausente: ${file}"; exit 1; }
  echo "Importando ${file}..."
  docker cp "${source_file}" "${CONTAINER}:/tmp/${file}"
  docker exec "${CONTAINER}" n8n import:workflow --input="/tmp/${file}"
done

echo "Pacote TV Sumaré importado. Os workflows devem permanecer inativos até a validação das credenciais e webhooks."
