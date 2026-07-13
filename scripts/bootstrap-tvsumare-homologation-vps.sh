#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/cschibelsky-star/TVSUMARE_ENTERPRISE.git"
BRANCH="feature/tvsumare-enterprise-3-foundation"
APP_DIR="/srv/tvsumare-enterprise"
HOMOLOG_DOMAIN="homolog.tvsumare.com.br"
N8N_DOMAIN="automacoes.vitrineiapro.com.br"

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    echo "Execute como root: sudo $0" >&2
    exit 1
  fi
}

install_packages() {
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y git nginx certbot python3-certbot-nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl unzip curl jq
}

sync_repository() {
  if [[ -d "${APP_DIR}/.git" ]]; then
    git -C "${APP_DIR}" fetch origin "${BRANCH}"
    git -C "${APP_DIR}" checkout "${BRANCH}"
    git -C "${APP_DIR}" reset --hard "origin/${BRANCH}"
  else
    rm -rf "${APP_DIR}"
    git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_DIR}"
  fi

  chown -R www-data:www-data "${APP_DIR}"
  chmod +x "${APP_DIR}"/scripts/*.sh
}

install_nginx_sites() {
  cp "${APP_DIR}/infra/nginx/${HOMOLOG_DOMAIN}.conf" "/etc/nginx/sites-available/${HOMOLOG_DOMAIN}"
  cp "${APP_DIR}/infra/nginx/${N8N_DOMAIN}.conf" "/etc/nginx/sites-available/${N8N_DOMAIN}"
  ln -sfn "/etc/nginx/sites-available/${HOMOLOG_DOMAIN}" "/etc/nginx/sites-enabled/${HOMOLOG_DOMAIN}"
  ln -sfn "/etc/nginx/sites-available/${N8N_DOMAIN}" "/etc/nginx/sites-enabled/${N8N_DOMAIN}"
  nginx -t
  systemctl reload nginx
}

issue_certificates() {
  certbot --nginx --non-interactive --agree-tos --redirect \
    --email "${CERTBOT_EMAIL:?Defina CERTBOT_EMAIL}" \
    -d "${HOMOLOG_DOMAIN}"

  certbot --nginx --non-interactive --agree-tos --redirect \
    --email "${CERTBOT_EMAIL}" \
    -d "${N8N_DOMAIN}"
}

validate_dns() {
  local server_ip
  server_ip="$(curl -fsS https://api.ipify.org)"
  for domain in "${HOMOLOG_DOMAIN}" "${N8N_DOMAIN}"; do
    local resolved
    resolved="$(getent ahostsv4 "${domain}" | awk 'NR==1{print $1}')"
    if [[ -z "${resolved}" || "${resolved}" != "${server_ip}" ]]; then
      echo "DNS de ${domain} ainda não aponta para ${server_ip}. Resolvido: ${resolved:-nenhum}" >&2
      exit 2
    fi
  done
}

run_application_deploy() {
  cd "${APP_DIR}"
  php scripts/validate-flow-package.php
  ./scripts/deploy-tvsumare-homologation.sh
}

run_checks() {
  cd "${APP_DIR}"
  ./scripts/tvsumare-homologation-check.sh
}

require_root
install_packages
sync_repository
validate_dns
install_nginx_sites
issue_certificates
run_application_deploy
run_checks

echo "Homologação disponível em https://${HOMOLOG_DOMAIN}"
echo "n8n disponível em https://${N8N_DOMAIN}"
