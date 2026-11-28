"""Exercise the packaged protected media policy using the actual Moodle browser UI."""
import json
import sys
from urllib.parse import parse_qs, urlencode, urlsplit
from playwright.sync_api import expect, sync_playwright

base = 'http://127.0.0.1:8080'
fixture = json.load(open(sys.argv[1]))
settings = dict(line.strip().split('=', 1) for line in open('.env') if '=' in line)
origin = f'{base}/local/seavanmedia'
course = fixture['course']
identity = {'id': course, 'file': fixture['webmkey']}
assert identity['file'], 'Expected a WebM fixture supported by headless Chromium.'


def sign_in(page, username, password):
    page.goto(base + '/login/index.php', wait_until='networkidle')
    page.locator('#username').fill(username)
    page.locator('#password').fill(password)
    page.locator('#loginbtn').click()
    page.wait_for_url(lambda url: '/login/index.php' not in url, wait_until='domcontentloaded')


with sync_playwright() as p:
    browser = p.chromium.launch()
    learner = browser.new_page()
    sign_in(learner, 'ci-learner', 'Ci-Test-Only!2026')
    learner.goto(origin + '/view.php?' + urlencode(identity | {'native': 1}))
    player = learner.locator('#sam-player')
    expect(player).to_have_attribute('controlslist', 'nodownload noremoteplayback')
    expect(learner.locator('.sam-watermark')).to_contain_text('Learner')
    stream = learner.locator('#sam-player source').get_attribute('src')
    token = {key: values[0] for key, values in parse_qs(urlsplit(stream).query).items()}
    assert token['ticket'] and token['until'], 'Stream did not use a playback ticket'
    assert learner.request.get(stream, headers={'Sec-Fetch-Dest': 'document'}).status == 404
    assert learner.request.get(stream, headers={'Sec-Fetch-Site': 'cross-site'}).status == 404
    response = learner.request.get(stream, headers={'Range': 'bytes=0-15'})
    assert response.status == 206 and len(response.body()) == 16 and 'no-store' in response.headers['cache-control'], (
        'The signed stream did not return a protected byte range', response.status
    )
    learner.wait_for_function("document.querySelector('#sam-player').readyState >= 1")
    player.evaluate("(video) => video.play()")
    learner.wait_for_function("document.querySelector('#sam-player').currentTime > 0.2")
    print('PASS: actual protected WebM playback and session-bound byte ranges.', flush=True)
    assert learner.request.get(origin + '/file.php?' + urlencode(identity)).status == 404
    assert 'video/' not in learner.request.get(stream + '&download=1').headers.get('content-type', '')
    expect(learner.get_by_role('link', name='دانلود دوره', exact=True)).to_have_count(0)

    learner.goto(origin + '/quizzes.php?' + urlencode({'id': course}))
    quiz = learner.get_by_role('link', name='CI Moodle Quiz')
    expect(quiz).to_be_visible()
    quiz.click()
    assert '/mod/quiz/view.php' in learner.url
    expect(learner.get_by_text('CI Moodle Quiz', exact=True).first).to_be_visible()
    learner.locator('.quizstartbuttondiv button').click()
    learner.wait_for_url('**/mod/quiz/attempt.php?**')
    expect(learner.locator('.que.truefalse')).to_be_visible()
    print('PASS: enrolled learner started a native Moodle quiz with a real question.', flush=True)

    admin = browser.new_page()
    sign_in(admin, 'admin', settings['ADMIN_PASSWORD'])
    assert admin.request.get(stream, headers={'Range': 'bytes=0-15'}).status == 404
    response = admin.request.get(origin + '/file.php?' + urlencode(identity | {'download': 1}))
    assert 'video/' not in response.headers.get('content-type', '')
    admin.goto(origin + '/report.php?' + urlencode({'course': course}))
    expect(admin.get_by_role('columnheader', name='مدت مشاهده')).to_be_visible()
    expect(admin.get_by_text('CI Learner', exact=True)).to_be_visible()
    watched = admin.get_by_role('cell', name='0:00:09', exact=True)
    expect(watched).to_be_visible()
    browser.close()
print('PASS: protected playback, blocked direct downloads and cross-session links, native Moodle quiz and learner viewing report.')
