"""CI-only authorization and concurrent byte-range playback tests."""
import concurrent.futures
import http.cookiejar
import html
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
base = "http://127.0.0.1:8080"
fixture = json.load(open(sys.argv[1]))
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
page = client.open(base + "/login/index.php", timeout=30).read().decode()
token = html.unescape(re.search(r'name="logintoken"[^>]*value="([^"]+)"', page)[1])
client.open(base + "/login/index.php", urllib.parse.urlencode({"username":"ci-learner","password":"Ci-Test-Only!2026","logintoken":token}).encode(), timeout=30).read()
cookie = "; ".join(f"{c.name}={c.value}" for c in jar)
def get(path, extra=None):
    req = urllib.request.Request(base + path, headers={"Cookie":cookie, **(extra or {})})
    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            return response.status, response.headers, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read()
def path(course,key):
    return "/local/seavanmedia/file.php?" + urllib.parse.urlencode({"id":course,"file":key})
mine=path(fixture["course"],fixture["key"])
other=path(fixture["other"],fixture["otherkey"])
status,headers,body=get(mine,{"Range":"bytes=0-15"})
assert status == 206 and len(body)==16, "Enrolled learner cannot stream"
for url in (mine+"&download=1",other):
    status,headers,body=get(url,{"Range":"bytes=0-15"})
    assert status!=206 and "video/" not in headers.get("Content-Type",""), "Unauthorized media returned"
status,headers,body=get("/local/seavanmedia/archive.php")
assert 'class="sa-subnav"' not in body.decode(errors="replace") and 'name="media"' not in body.decode(errors="replace"), "Learner accessed administrator page"
def segment(i):
    status,headers,data=get(mine,{"Range":f"bytes={i*16}-{i*16+15}"})
    assert status==206 and len(data)==16
with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
    list(pool.map(segment,range(32)))
print("Learner course access, download restriction, administrator route and 32 range requests (8 parallel) passed.")
