"""Browser-level checks for the genuine Moodle Video.js integration."""
import pathlib
from playwright.sync_api import sync_playwright

base = "http://127.0.0.1:8080"
env = dict(line.strip().split("=", 1) for line in pathlib.Path(".env").read_text().splitlines() if "=" in line)
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.on('pageerror', lambda error: print('Browser error:', error))
    page.on('console', lambda msg: print('Browser console:', msg.text) if msg.type == 'error' else None)
    page.goto(base + "/login/index.php", wait_until="domcontentloaded")
    page.locator("#username").fill("admin")
    page.locator("#password").fill(env["ADMIN_PASSWORD"])
    page.locator("#loginbtn").click()
    page.wait_for_url(lambda url: "/login/index.php" not in url, timeout=60000)
    page.goto(base + "/course/index.php?q=02+-+Browser", wait_until="domcontentloaded")
    result = page.locator(".sa-session-result").filter(has_text="02 - Browser").first
    result.wait_for(timeout=60000)
    result.click()
    page.locator('[data-seavan-engine="videojs"]').wait_for(timeout=60000)
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
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 2"), "Mobile page overflows horizontally"
    assert page.locator(".sam-player-wrap").bounding_box()["width"] > 300
    assert not page.locator(".sam-extras").evaluate("(e) => e.open")
    browser.close()
print("Video.js, actual WebM playback, captions, progress, session search and native fallback checked.")
