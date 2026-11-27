"""Browser-level checks for the genuine Moodle Video.js integration."""
import pathlib
from playwright.sync_api import TimeoutError as PlaywrightTimeout, sync_playwright

base = "http://127.0.0.1:8080"
shots = pathlib.Path("ui-screenshots")
shots.mkdir(exist_ok=True)
env = dict(line.strip().split("=", 1) for line in pathlib.Path(".env").read_text().splitlines() if "=" in line)
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.on('pageerror', lambda error: print('Browser error:', error))
    page.on('console', lambda msg: print('Browser console:', msg.text) if msg.type == 'error' else None)
    page.goto(base + "/login/index.php", wait_until="domcontentloaded")
    page.screenshot(path=str(shots / "01-login.png"), full_page=True)
    page.locator("#username").fill("admin")
    password = page.locator("#password")
    original_input = password.element_handle()
    password.fill(env["ADMIN_PASSWORD"])
    toggle = page.locator("#sa-login-password-toggle")
    toggle.wait_for(state="attached")
    if not toggle.is_visible():
        page.set_viewport_size({"width": 390, "height": 844})
    assert original_input.evaluate("(element) => element === document.querySelector('#password')"), "Login replaced the password input"
    toggle.click()
    assert password.get_attribute("type") == "text"
    toggle.click()
    assert password.get_attribute("type") == "password"
    assert password.input_value() == env["ADMIN_PASSWORD"], "Login cleared the password"
    page.set_viewport_size({"width": 1280, "height": 900})
    try:
        with page.expect_response(lambda response: response.request.method == "POST" and
                                  response.url == base + "/login/index.php", timeout=15000) as submitted:
            page.locator("#loginbtn").click()
        print("Browser login POST returned", submitted.value.status)
        page.wait_for_url(lambda url: "/login/index.php" not in url, wait_until="domcontentloaded", timeout=30000)
    except PlaywrightTimeout as error:
        page.screenshot(path=str(shots / "01-login-after-submit.png"), full_page=True)
        notice = page.locator("#loginerrormessage")
        if notice.is_visible():
            raise AssertionError("Moodle browser login failed: " + notice.inner_text().strip()) from error
        raise AssertionError("Moodle browser login did not complete at " + page.url.split("?")[0]) from error
    page.goto(base + "/course/index.php", wait_until="networkidle")
    page.locator(".seavan-course").first.wait_for()
    assert page.locator(".sa-main-nav").count() == 1
    assert page.locator(".sa-main-nav a").count() == 3
    assert page.locator(".seavan-grid").evaluate("(e) => getComputedStyle(e).display") == "grid"
    page.screenshot(path=str(shots / "02-library.png"), full_page=True)
    page.locator("#sa-dark").click()
    assert page.locator("body.sa-dark").count() == 1
    page.screenshot(path=str(shots / "02-library-dark.png"), full_page=True)
    page.locator("#sa-dark").click()
    page.goto(base + "/local/seavan/index.php?view=manage", wait_until="networkidle")
    assert page.locator(".sa-admin-panel").count() == 4
    page.screenshot(path=str(shots / "03-management.png"), full_page=True)
    for name, path, selector in [
        ("learning", "/my/courses.php", ".seavan-shell"),
        ("archive", "/local/seavanmedia/archive.php", ".sa-subnav"),
        ("upload", "/local/seavanmedia/archive.php?section=upload", "form[enctype='multipart/form-data']"),
        ("versions", "/local/seavanmedia/archive.php?section=versions", "select[name='course']"),
        ("duplicates", "/local/seavanmedia/archive.php?section=duplicates", ".sa-subnav"),
        ("queue", "/local/seavanmedia/queue.php", ".sa-metrics"),
        ("health", "/local/seavanmedia/health.php", ".sa-metrics"),
        ("server", "/local/seavanmedia/server.php", ".sa-info"),
        ("employees", "/local/seavanmedia/employees.php", ".sa-form-panel"),
        ("request", "/local/seavanmedia/request.php", ".sa-form-panel"),
        ("report", "/local/seavanmedia/report.php", ".seavan-shell"),
        ("sync", "/local/seavanmedia/sync.php", ".seavan-shell"),
    ]:
        page.goto(base + path, wait_until="domcontentloaded")
        page.locator(selector).first.wait_for(timeout=30000)
        assert page.locator(".sa-main-nav").count() == 1, name
        page.screenshot(path=str(shots / (name + ".png")), full_page=True)
        page.set_viewport_size({"width":390,"height":844})
        page.screenshot(path=str(shots / (name + "-mobile.png")), full_page=True)
        assert page.evaluate("document.documentElement.scrollWidth <= innerWidth + 2"), name + " mobile overflow"
        page.set_viewport_size({"width":1280,"height":900})
    page.goto(base + "/course/index.php?q=02+-+Browser", wait_until="domcontentloaded")
    result = page.locator(".sa-session-result").filter(has_text="02 - Browser").first
    result.wait_for(timeout=60000)
    result.click()
    page.locator('.sam-player-wrap').wait_for()
    page.screenshot(path=str(shots / '04-player-before-init.png'), full_page=True)
    page.locator('[data-seavan-engine="videojs"]').wait_for(timeout=60000)
    page.screenshot(path=str(shots / '04-player.png'), full_page=True)
    media = page.locator(".sam-player-wrap video")
    page.wait_for_function("document.querySelector('.sam-player-wrap video').readyState >= 1", timeout=60000)
    assert media.evaluate("(v) => v.duration") > 5
    assert page.evaluate("""() => new Promise((resolve, reject) => require(['media_videojs/video-lazy'], mod => { const api = typeof mod === 'function' ? mod : mod.default; resolve(api.getPlayer('sam-player').textTracks().length); }, reject))""") >= 1
    media.evaluate("(v) => {v.muted=true; return v.play();}")
    page.wait_for_function("document.querySelector('.sam-player-wrap video').currentTime > 2", timeout=15000)
    with page.expect_response(lambda r: "/local/seavanmedia/progress.php" in r.url and r.request.method == "POST", timeout=15000) as saved:
        media.evaluate("(v) => v.pause()")
    assert saved.value.ok
    assert saved.value.json()["percent"] > 0
    page.goto(page.url + "&native=1", wait_until="domcontentloaded")
    page.locator("#sam-player").wait_for()
    page.wait_for_function("document.querySelector('#sam-player').controls && !document.querySelector('#sam-player').classList.contains('video-js')")
    assert page.locator('[data-seavan-engine="videojs"]').count() == 0
    page.set_viewport_size({"width":390,"height":844})
    page.goto(page.url.replace("&native=1",""), wait_until="domcontentloaded")
    page.locator('[data-seavan-engine="videojs"]').wait_for(timeout=60000)
    page.screenshot(path=str(shots / "05-mobile.png"), full_page=True)
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 2"), "Mobile page overflows horizontally"
    assert page.locator(".sam-player-wrap").bounding_box()["width"] > 300
    assert not page.locator(".sam-extras").evaluate("(e) => e.open")
    browser.close()
print("Video.js, actual WebM playback, captions, progress, session search and native fallback checked.")
