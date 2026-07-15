#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${TVSUMARE_HOMOLOG_DIR:-/srv/tvsumare-homolog}"
BACKUP_DIR="${TVSUMARE_BACKUP_DIR:-/srv/backups/tvsumare}"
BRANCH="${TVSUMARE_BRANCH:-feature/tvsumare-enterprise-3-foundation}"
REPO="${TVSUMARE_REPO:-https://github.com/cschibelsky-star/TVSUMARE_ENTERPRISE.git}"
STAMP="$(date +%Y%m%d_%H%M%S)"

mkdir -p "${BACKUP_DIR}"
if [[ -d "${TARGET_DIR}" ]]; then
  tar -czf "${BACKUP_DIR}/homolog_${STAMP}.tar.gz" -C "$(dirname "${TARGET_DIR}")" "$(basename "${TARGET_DIR}")"
fi

if [[ ! -d "${TARGET_DIR}/.git" ]]; then
  rm -rf "${TARGET_DIR}"
  git clone --branch "${BRANCH}" "${REPO}" "${TARGET_DIR}"
else
  git -C "${TARGET_DIR}" fetch origin
  git -C "${TARGET_DIR}" checkout "${BRANCH}"
  git -C "${TARGET_DIR}" reset --hard "origin/${BRANCH}"
fi

cd "${TARGET_DIR}"
php scripts/validate-flow-package.php
composer install --no-dev --optimize-autoloader --no-interaction

mkdir -p data
chmod -R ug+rwX data

echo "Deploy de homologação concluído em ${TARGET_DIR}. Configure Nginx para apontar homolog.tvsumare.com.br para ${TARGET_DIR}/public."
