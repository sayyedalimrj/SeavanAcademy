#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
: "${CODESPACE_NAME:?Run this inside GitHub Codespaces}"
: "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:?Codespaces port domain is missing}"
url="https://${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
if [ ! -f .env ]; then
  umask 077
  printf 'BIND_IP=127.0.0.1\nSITE_URL=%s\nADMIN_EMAIL=admin@example.invalid\nADMIN_PASSWORD=Aa1!%s\nDB_PASSWORD=%s\nDB_ROOT_PASSWORD=%s\nSSL_PROXY=1\n' "$url" "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" > .env
fi
docker compose up -d --build
docker compose ps -a
printf '\nReal Moodle: %s/login/index.php\n' "$url"
printf 'Username: admin\nInitial password is stored in .env (private).\n'
printf 'Keep port 8080 private. Do not upload employee data or company videos to this test environment.\n'
