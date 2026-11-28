"""Use the native Moodle login and password-change forms on a fresh package install."""
import html
import os
import http.cookiejar
import re
import urllib.parse
import urllib.request

base = 'http://127.0.0.1:8080'
admin_password = os.getenv('SEAVAN_TEST_PASSWORD')
if admin_password is None:
    with open('.env') as env_file:
        admin_password = dict(line.strip().split('=', 1) for line in env_file if '=' in line)['ADMIN_PASSWORD']


def field(page, name):
    return html.unescape(re.search(r'name="' + re.escape(name) + r'"[^>]*value="([^"]*)"', page)[1])


def login(password):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    url = base + '/login/index.php'
    login_page = client.open(url, timeout=45).read().decode()
    payload = {'username': 'admin', 'password': password, 'logintoken': field(login_page, 'logintoken')}
    return client, client.open(url, urllib.parse.urlencode(payload).encode(), timeout=45)


client, response = login('admin')
assert '/login/change_password.php' in response.geturl(), 'The first administrator login must force a password change.'
page = response.read().decode()
form = '_qf__login_change_password_form'
data = {'password': 'admin', 'newpassword1': admin_password, 'newpassword2': admin_password,
        'id': field(page, 'id'), 'sesskey': field(page, 'sesskey'), form: field(page, form)}
client.open(base + '/login/change_password.php', urllib.parse.urlencode(data).encode(), timeout=45).read()
_, rejected = login('admin')
assert '/login/index.php' in rejected.geturl(), 'Temporary password still works after rotation.'
client, _ = login(admin_password)
page = client.open(base + '/local/seavan/index.php?view=manage', timeout=45).read().decode()
assert 'sa-admin-panel' in page, 'Administrator cannot reach management after password rotation.'
print('PASS: local first login, mandatory password change, old password rejection and access after rotation.')
