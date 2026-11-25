import http.cookiejar
import html
import pathlib
import re
import urllib.parse
import urllib.request

base = 'http://127.0.0.1:8080'
env = dict(line.strip().split('=', 1) for line in open('.env') if '=' in line)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path):
    return opener.open(urllib.parse.urljoin(base, path), timeout=90)
def read(path):
    return get(path).read().decode('utf-8')
def attribute(page, name):
    m = re.search(r'\b' + re.escape(name) + r'="([^"]+)"', page)
    assert m, 'Missing attribute: ' + name
    return html.unescape(m.group(1))
login = read('/login/index.php')
token = re.search(r'name="logintoken"[^>]*value="([^"]+)"', login)
assert token, 'Moodle login token missing'
data = urllib.parse.urlencode({'username':'admin','password':env['ADMIN_PASSWORD'],'logintoken':html.unescape(token.group(1))}).encode()
opener.open(base + '/login/index.php', data, timeout=90).read()
catalog = read('/local/seavan/index.php?view=catalog')
assert 'CI Media Course' in catalog, 'Folder import did not create course'
link = re.search(r'href="([^"]*/local/seavanmedia/view\.php\?id=\d+[^"]*)"',catalog)
assert link, 'Native catalog player link not found'
viewurl = html.unescape(link.group(1))
page = read(viewurl)
assert 'sam-player' in page and '01 - Sample' in page
key = attribute(page, 'data-file')
course = attribute(page,'data-course')
sesskey = attribute(page,'data-sesskey')
endpoint = attribute(page,'data-endpoint')
fileurl = base + '/local/seavanmedia/file.php?' + urllib.parse.urlencode({'id':course,'file':key})
req = urllib.request.Request(fileurl, headers={'Range':'bytes=0-15'})
with opener.open(req,timeout=90) as response:
    assert response.status == 206, 'Byte range not supported'
    assert len(response.read()) == 16
with opener.open(fileurl+'&download=1',timeout=90) as response:
    assert 'attachment' in response.headers.get('Content-Disposition','')
    assert response.read()
with urllib.request.urlopen(fileurl,timeout=90) as anon:
    assert '/login/' in anon.geturl(), 'Anonymous media access was not denied'
data = urllib.parse.urlencode({'id':course,'file':key,'sesskey':sesskey,'duration':'20','position':'10','ranges':'[[0,5],[8,10]]'}).encode()
import json
with opener.open(endpoint,data,timeout=90) as response:
    assert json.load(response)['percent'] == 35
page = read(viewurl)
assert float(attribute(page,'data-position')) == 10
print('Folder import, authenticated file serving, byte range, download and playback position API checks passed.')
