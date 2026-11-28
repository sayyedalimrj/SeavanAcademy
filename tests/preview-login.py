#!/usr/bin/env python3
"""Exercise Moodle's actual login through the HTTP backend of an HTTPS proxy.
No authentication bypass, no password/token printed. Forwarded browser cookies
are transported only to loopback; external redirects are refused.
"""
import argparse
import http.client
import os
import sys
from html.parser import HTMLParser
from http.cookies import SimpleCookie
from urllib.parse import urlsplit, urljoin, urlencode

class Login(HTMLParser):
    def __init__(self):
        super().__init__()
        self.token = None
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "input" and attrs.get("name") == "logintoken":
            self.token = attrs.get("value")

p = argparse.ArgumentParser()
p.add_argument("--origin", required=True)
p.add_argument("--port", type=int, required=True)
p.add_argument("--username", default="admin")
p.add_argument("--expect-denied", action="store_true")
a = p.parse_args()
origin = a.origin.rstrip("/")
host = urlsplit(origin).netloc
if urlsplit(origin).scheme != "https" or not host:
    sys.exit("Expected canonical HTTPS origin")
password = os.environ.get("SEAVAN_TEST_PASSWORD")
if not password:
    sys.exit("Missing password for login test")
cookies = {}
secure_session = False

def get(url, data=None):
    global secure_session
    for _ in range(12):
        target = urlsplit(urljoin(origin + "/", url))
        if target.scheme != "https" or target.netloc != host:
            raise RuntimeError("Redirect left configured HTTPS origin: " + target.netloc)
        path = target.path or "/"
        if target.query:
            path += "?" + target.query
        headers = {"Host": "127.0.0.1:"+str(a.port),
                   "X-Forwarded-Host": host, "X-Forwarded-Proto": "https",
                   "User-Agent": "Seavan-Preview-Login-Check/1.0",
                   "Cookie": "; ".join(k+"="+v for k,v in cookies.items())}
        if data is not None:
            headers.update({"Content-Type":"application/x-www-form-urlencoded","Origin":origin,"Referer":origin+"/login/index.php"})
        c = http.client.HTTPConnection("127.0.0.1", a.port, timeout=45)
        c.request("POST" if data is not None else "GET", path, data, headers)
        response = c.getresponse()
        status = response.status
        location = response.getheader("Location")
        for key, value in response.getheaders():
            if key.lower() != "set-cookie":
                continue
            parsed = SimpleCookie()
            parsed.load(value)
            for name, morsel in parsed.items():
                if morsel["max-age"] == "0" or not morsel.value:
                    cookies.pop(name, None)
                else:
                    cookies[name] = morsel.value
                if name == "MoodleSessionSeavanPreviewV2" and morsel.value:
                    secure_session = bool(morsel["secure"] and morsel["httponly"] and not morsel["domain"] and morsel["path"] == "/")
        body = response.read().decode("utf-8", errors="replace")
        c.close()
        print(("POST" if data is not None else "GET") + " " + target.path + " -> " + str(status), flush=True)
        if status in (301,302,303,307,308) and location:
            url = urljoin(target.geturl(), location)
            if status not in (307,308):
                data = None
            continue
        if status != 200:
            raise RuntimeError("HTTP " + str(status) + " at " + target.path)
        return body
    raise RuntimeError("Redirect loop")

login = Login()
login.feed(get("/login/index.php"))
if not login.token:
    sys.exit("FAIL: native Moodle login token missing")
if not secure_session:
    sys.exit("FAIL: isolated secure session cookie missing")
get("/login/index.php", urlencode({"username":a.username,"password":password,"logintoken":login.token}).encode())
page = get("/local/seavan/index.php?view=manage")
if a.expect_denied:
    denied = Login()
    denied.feed(page)
    if not denied.token or 'class="sa-admin-panel"' in page:
        sys.exit("FAIL: the old password was not rejected by Moodle")
    print("PASS: Moodle rejected the old password")
else:
    if 'class="sa-main-nav"' not in page or 'class="sa-admin-panel"' not in page:
        sys.exit("FAIL: login did not reach the authenticated management page")
    print("PASS: HTTPS proxy-mode login, CSRF token, secure cookie, session persistence and admin page")
