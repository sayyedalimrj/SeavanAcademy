#!/usr/bin/env bash
# Recover admin login for the existing private Codespaces preview.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" && -f .env && -f compose.preview.yaml ]] || {
    echo 'Existing Codespaces preview required.'
    exit 1
}
grep -Eq '^SITE_URL=https?://[^/]+-8081\.' .env || { echo 'Expected the isolated 8081 preview.'; exit 1; }

umask 077
mkdir -p backups
exec 9>backups/.preview-recovery.lock
flock -n 9 || { echo 'Preview login recovery is already running.'; exit 1; }

export PREVIEW_ORIGIN="https://${CODESPACE_NAME}-8081.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
# Export the canonical values so inherited variables cannot override .env.
export SITE_URL="$PREVIEW_ORIGIN" SSL_PROXY=1 BIND_IP=127.0.0.1
d(){ docker compose -p seavan-rc -f compose.preview.yaml "$@"; }

web_origin="$(d exec -T web printenv SITE_URL 2>/dev/null || true)"
web_proxy="$(d exec -T web printenv SSL_PROXY 2>/dev/null || true)"
if [[ "$web_origin" != "$PREVIEW_ORIGIN" || "$web_proxy" != 1 ]] ||
        ! d exec -T web grep -q SeavanPreviewV2 /var/www/html/config.php ||
        ! d exec -T web test -f /var/www/html/theme/seavan/classes/output/core_renderer.php; then
    # Upgrade only when the running web service lacks the current HTTPS configuration.
    if ! d exec -T web true 2>/dev/null; then
        d up -d --no-build web
    fi
    bash .devcontainer/update-preview.sh
fi

for attempt in {1..20}; do
    if curl --noproxy '*' -fsS --max-time 8 http://127.0.0.1:8081/login/index.php -o /dev/null; then
        break
    fi
    if [[ "$attempt" == 20 ]]; then
        echo 'The preview login page is unavailable. No password was changed.'
        exit 1
    fi
    sleep 2
done

cp .env "backups/preview-settings-$(date -u +%Y%m%dT%H%M%SZ).env"
export SEAVAN_TEST_PASSWORD="Aa1!$(openssl rand -hex 16)"
pending="$(mktemp .env.preview.XXXXXX)"
trap 'rm -f -- "$pending"' EXIT
python3 - "$pending" <<'PY'
import os
import pathlib
import sys

settings = {
    "ADMIN_PASSWORD": os.environ["SEAVAN_TEST_PASSWORD"],
    "SITE_URL": os.environ["PREVIEW_ORIGIN"],
    "SSL_PROXY": "1",
    "BIND_IP": "127.0.0.1",
}
lines = pathlib.Path(".env").read_text().splitlines()
lines = [line for line in lines if line.split("=", 1)[0].strip() not in settings]
lines += [f"{key}={value}" for key, value in settings.items()]
pathlib.Path(sys.argv[1]).write_text("\n".join(lines) + "\n")
PY

d exec -T -e SEAVAN_TEST_PASSWORD -u www-data web php < .devcontainer/reset-preview-admin.php
mv -f -- "$pending" .env

if ! python3 tests/preview-login.py --origin "$PREVIEW_ORIGIN" --port 8081; then
    echo 'The password was saved to the private .env file. The web login test failed.'
    exit 1
fi
printf '\nURL: %s/login/index.php\nUSERNAME: admin\nPASSWORD: %s\n' "$PREVIEW_ORIGIN" "$SEAVAN_TEST_PASSWORD"
