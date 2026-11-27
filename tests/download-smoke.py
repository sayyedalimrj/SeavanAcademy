"""Exercise real Moodle archive downloads and the administrator request inbox."""
import html
import http.cookiejar
import io
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
import zipfile

base = 'http://127.0.0.1:8080'
fixture = json.load(open(sys.argv[1], encoding='utf-8'))
env = dict(line.strip().split('=', 1) for line in open('.env') if '=' in line)
media = '/local/seavanmedia/'


def fetch(client, path, query=None, data=None):
    url = base + path
    if query:
        url += '?' + urllib.parse.urlencode(query)
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    try:
        with client.open(url, data=body, timeout=60) as response:
            return response.headers, response.read()
    except urllib.error.HTTPError as error:
        return error.headers, error.read()


def field(page, name):
    return html.unescape(re.search(r'name="' + name + r'"[^>]*value="([^"]+)"', page)[1])


def sign_in(username, password):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    _, login = fetch(client, '/login/index.php')
    fetch(client, '/login/index.php', data={
        'username': username, 'password': password,
        'logintoken': field(login.decode(), 'logintoken'),
    })
    return client


def archive(client, expected, **options):
    headers, content = fetch(client, media + 'bundle.php', **options)
    assert headers.get_content_type() == 'application/zip', content.decode(errors='replace')[:300]
    assert 'attachment' in headers.get('Content-Disposition', '')
    with zipfile.ZipFile(io.BytesIO(content)) as result:
        assert set(result.namelist()) == set(expected), result.namelist()
        assert result.testzip() is None


def reject_zip(client, **options):
    headers, content = fetch(client, media + 'bundle.php', **options)
    assert headers.get_content_type() != 'application/zip' and not content.startswith(b'PK\x03\x04')


admin = sign_in('admin', env['ADMIN_PASSWORD'])
learner = sign_in('ci-learner', 'Ci-Test-Only!2026')
otherfiles = fixture['otherfiles']
onekey, firstfile = next(iter(otherfiles.items()))
_, raw = fetch(admin, media + 'downloads.php', {'id': fixture['other']})
page = raw.decode()
assert 'دانلود یکجای دوره' in page and 'دانلود فایل‌های انتخاب‌شده' in page
fields = {'id': fixture['other'], 'sesskey': field(page, 'sesskey')}
allnames = [file['rel'] for file in otherfiles.values()]
chapter = firstfile['group']
archive(admin, allnames, query=fields | {'mode': 'course'})
archive(admin, [firstfile['rel']], query=fields | {'mode': 'chapter', 'chapter': chapter})
archive(admin, [firstfile['rel']], data=fields | {'mode': 'selected', 'files[]': [onekey, onekey]})
archive(admin, allnames, data=fields | {'mode': 'selected', 'files[]': list(otherfiles)})
archive(admin, allnames, query=fields | {'mode': 'part', 'part': 1, 'revision': fixture['otherrevision']})
reject_zip(admin, data=fields | {'mode': 'selected', 'files[]': [fixture['key']]})
reject_zip(admin, query=fields | {'mode': 'part', 'part': 1, 'revision': '0' * 64})
reject_zip(admin, query={'id': fixture['other'], 'mode': 'course'})

_, raw = fetch(learner, media + 'request.php')
learnerkey = field(raw.decode(), 'sesskey')
_, raw = fetch(learner, media + 'downloads.php', {'id': fixture['course']})
assert 'sa-download-form' not in raw.decode()
reject_zip(learner, query={'id': fixture['course'], 'sesskey': learnerkey, 'mode': 'course'})
reject_zip(learner, data={'id': fixture['course'], 'sesskey': learnerkey, 'mode': 'selected', 'files[]': [fixture['key']]})
reject_zip(learner, query={'id': fixture['other'], 'sesskey': learnerkey, 'mode': 'course'})

fetch(admin, media + 'archive.php', data={'id': fixture['course'], 'sesskey': fields['sesskey'], 'action': 'policy', 'blocked': 0})
_, raw = fetch(learner, media + 'downloads.php', {'id': fixture['course']})
assert 'sa-download-form' in raw.decode()
headers, payload = fetch(learner, media + 'file.php', {'id': fixture['course'], 'file': fixture['key'], 'download': 1})
assert 'attachment' in headers.get('Content-Disposition', '') and payload
fetch(admin, media + 'archive.php', data={'id': fixture['course'], 'sesskey': fields['sesskey'], 'action': 'policy', 'blocked': 1})

subject = 'CI unique training request'
description = 'CI course body for a civil engineering lesson'
_, raw = fetch(learner, media + 'request.php', data={'sesskey': learnerkey, 'title': subject, 'body': description})
assert 'درخواست به مدیر ارسال شد' in raw.decode(), 'Moodle did not confirm submission'
_, raw = fetch(learner, media + 'requests.php')
assert subject not in raw.decode()
_, raw = fetch(admin, media + 'requests.php')
inbox = raw.decode()
assert subject in inbox, 'Submitted request missing from administrator inbox'
assert description in inbox, 'Request details missing from administrator inbox'
card = re.search(r'<article class="sa-admin-panel sa-request-card">([\s\S]+?)</article>', inbox)
assert card, 'Request card missing'
notificationid = field(card[1], 'id')
fetch(admin, media + 'requests.php', data={'sesskey': fields['sesskey'], 'id': notificationid})
_, raw = fetch(admin, media + 'requests.php')
assert subject not in raw.decode()
_, raw = fetch(admin, media + 'requests.php', {'filter': 'all'})
assert subject in raw.decode() and 'خوانده‌شده' in raw.decode()

print('PASS: all download modes, ZIP contents, permission changes, request submission and administrator inbox.')
