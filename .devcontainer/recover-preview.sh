#!/usr/bin/env bash
# Explicit recovery of the isolated Codespaces preview, never production.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" && -f .env && -f compose.preview.yaml ]] || {
  echo "Existing Codespaces preview required."; exit 1;
}
grep -q -- '-8081\.' .env || { echo "Not the isolated 8081 site; refusing."; exit 1; }
umask 077
mkdir -p backups
cp .env "backups/preview-settings-$(date -u +%Y%m%dT%H%M%SZ).env"
export PREVIEW_ORIGIN="https://${CODESPACE_NAME}-8081.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
python3 - <<'PY'
import os, pathlib
p=pathlib.Path(".env")
updates={"SITE_URL":os.environ["PREVIEW_ORIGIN"],"SSL_PROXY":"1","BIND_IP":"127.0.0.1"}
lines=[line for line in p.read_text().splitlines() if line.split("=",1)[0].strip() not in updates]
p.write_text("\n".join(lines+[k+"="+v for k,v in updates.items()])+"\n")
PY
bash .devcontainer/update-preview.sh
d(){ docker compose -p seavan-rc -f compose.preview.yaml "$@"; }
# Reset only the existing, active, local site administrator.
# Strong temporary password; never disable Moodle password policy or login tokens.
export SEAVAN_TEST_PASSWORD="Aa1!$(openssl rand -hex 16)"
d exec -T -e SEAVAN_TEST_PASSWORD -u www-data web php <<'PHP'
<?php
define('CLI_SCRIPT',true);
require '/var/www/html/config.php';
require_once($CFG->libdir.'/authlib.php');
$u=get_admin();
if(!$u || $u->username!=='admin' || $u->auth!=='manual' || $u->deleted || $u->suspended || !$u->confirmed){
    fwrite(STDERR,"Unexpected administrator status. No account changed.\n");exit(1);
}
$p=getenv('SEAVAN_TEST_PASSWORD');$error='';
if(!check_password_policy($p,$error,$u)){fwrite(STDERR,"Generated password rejected by policy.\n");exit(1);}
$DB->set_field('user','password',hash_internal_user_password($p),['id'=>$u->id]);
login_unlock_account($u);
unset_user_preference('auth_forcepasswordchange',$u->id);
\core\session\manager::kill_user_sessions($u->id);
$u=$DB->get_record('user',['id'=>$u->id],'*',MUST_EXIST);
if(!validate_internal_user_password($u,$p)){fwrite(STDERR,"Password verification failed.\n");exit(1);}
echo "Admin password verified in Moodle.\n";
PHP
# Update the private local credential record only after the database accepted it.
python3 - <<'PY'
import os,pathlib
p=pathlib.Path(".env")
lines=[l for l in p.read_text().splitlines() if l.split("=",1)[0].strip()!="ADMIN_PASSWORD"]
p.write_text("\n".join(lines+["ADMIN_PASSWORD="+os.environ["SEAVAN_TEST_PASSWORD"]])+"\n")
PY
if ! python3 tests/preview-login.py --origin "$PREVIEW_ORIGIN" --port 8081; then
  echo "Preview login test FAILED; last web errors:"
  d logs --tail=30 web
  echo "The updated password is in the private .env file. No data was deleted."
  exit 1
fi
printf '\nURL: %s/login/index.php\nUSERNAME: admin\nPASSWORD: %s\n' "$PREVIEW_ORIGIN" "$SEAVAN_TEST_PASSWORD"
echo "Open the above address in a private browser window. Keep port 8081 private."
