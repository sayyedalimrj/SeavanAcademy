"""Verify the native Windows patch through Moodle's actual learner and administrator UI."""
import json
import os
import re
import sys
from urllib.parse import urlencode

from playwright.sync_api import expect, sync_playwright

with open(sys.argv[1]) as data:
    fixture = json.load(data)
    course = fixture['course']
    other = fixture['other']
base = 'http://127.0.0.1:8080'
origin = base + '/local/seavanmedia'
activities = origin + '/quizzes.php?'


def sign_in(page, user, password):
    page.goto(base + '/login/index.php', wait_until='networkidle')
    page.locator('#username').fill(user)
    page.locator('#password').fill(password)
    page.locator('#loginbtn').click()
    page.wait_for_url(lambda url: '/login/index.php' not in url, wait_until='domcontentloaded')


with sync_playwright() as runner:
    browser = runner.chromium.launch()
    admin = browser.new_page()
    sign_in(admin, 'admin', os.environ['SEAVAN_TEST_PASSWORD'])
    admin.goto(base + '/local/seavan/index.php?view=manage')
    expect(admin.get_by_role('link', name=re.compile('مدیریت آزمون‌ها'))).to_be_visible()
    admin.get_by_role('link', name=re.compile('دوره‌ها و ثبت‌نام مودل')).click()
    course_row = admin.get_by_role('row').filter(has_text='CI Media Course')
    expect(course_row.get_by_role('link', name='شرکت‌کنندگان و ثبت‌نام')).to_have_attribute('href', re.compile('/user/index.php'))
    course_row.get_by_role('link', name='CI Media Course', exact=True).click()
    expect(admin).to_have_url(re.compile('/course/view.php'))
    expect(admin.get_by_text('Chapter 1', exact=True).first).to_be_visible()
    expect(admin.get_by_role('link', name=re.compile('02 - Browser')).first).to_be_visible()
    print('PASS: Windows patch links existing media folders, sections and activities to native Moodle.', flush=True)

    admin.goto(origin + '/report.php')
    expect(admin.get_by_role('link', name='Other Course', exact=True)).to_be_visible()
    admin.get_by_role('link', name='CI Media Course', exact=True).click()
    summary = admin.locator('.sa-watching-summary')
    unst = summary.get_by_role('row').filter(has_text='CI Unstarted')
    expect(unst.get_by_role('cell', name='0٪')).to_be_visible()
    expect(summary.get_by_role('row').filter(has_text='CI Learner').get_by_role('cell', name=re.compile(r'^[1-9]'))).to_be_visible()
    unst.get_by_role('link', name='CI Unstarted').click()
    zeros = admin.get_by_role('cell', name='0٪', exact=True)
    expect(zeros).to_have_count(2)
    print('PASS: administrator sees all enrolled learners and unstarted sessions at zero percent.', flush=True)

    admin.goto(activities + urlencode({'id': course, 'manage': 1}))
    expect(admin.get_by_role('link', name='افزودن آزمون')).to_be_visible()
    expect(admin.get_by_role('link', name='CI Moodle Quiz')).to_be_visible()
    evaluation_url = activities + urlencode({'id': course, 'manage': 1, 'evaluation': 1})
    admin.goto(evaluation_url)
    admin.locator('#instructor').fill('CI Instructor')
    admin.locator('#subject').fill('CI Subject')
    admin.get_by_role('button', name='ساخت فرم ۲۰ سؤالی ارزیابی').click()
    try:
        admin.wait_for_url('**/mod/feedback/view.php?**', timeout=8000)
    except Exception:
        print(admin.locator('#region-main').inner_text()[:2500], flush=True)
        raise
    feedback_url = admin.url
    admin.goto(evaluation_url)
    admin.locator('#instructor').fill('CI Instructor')
    admin.locator('#subject').fill('CI Subject')
    admin.get_by_role('button', name='ساخت فرم ۲۰ سؤالی ارزیابی').click()
    try:
        admin.wait_for_url('**/mod/feedback/view.php?**', timeout=8000)
    except Exception:
        print(admin.locator('#region-main').inner_text()[:2500], flush=True)
        raise
    assert admin.url == feedback_url, 'A repeated request duplicated the feedback activity.'

    student = browser.new_page()
    sign_in(student, 'ci-unstarted', 'Ci-Test-Only!2026')
    student.goto(base + '/my/courses.php')
    student.get_by_role('navigation', name='منوی اصلی').get_by_role('link', name='آزمون‌ها').click()
    student.get_by_role('link', name='CI Media Course', exact=True).click()
    expect(student.get_by_role('link', name='CI Moodle Quiz')).to_be_visible()
    student.goto(origin + '/enrolrequests.php?' + urlencode({'id': other}))
    student.get_by_role('button', name='ارسال درخواست عضویت').click()
    expect(student.get_by_text('در انتظار بررسی', exact=True)).to_be_visible()
    admin.goto(origin + '/enrolrequests.php?manage=1')
    requestrow = admin.get_by_role('row').filter(has_text='Other Course').filter(has_text='CI Unstarted')
    requestrow.get_by_role('button', name='پذیرش و ثبت‌نام').click()
    expect(requestrow.get_by_text('تأیید شد', exact=True)).to_be_visible()
    admin.goto(base + '/user/index.php?' + urlencode({'id': other}))
    expect(admin.get_by_role('row').filter(has_text='CI Unstarted')).to_be_visible()
    student.goto(origin + '/enrolrequests.php?' + urlencode({'id': other}))
    expect(student.get_by_role('link', name='ورود به دوره')).to_be_visible()
    print('PASS: learner requests access, and approval creates a native Moodle participant.', flush=True)

    admin.goto(origin + '/checkpoints.php?' + urlencode({'id': course}))
    admin.locator('#sa-cp-session').select_option(label='Chapter 1/02 - Browser.webm')
    admin.locator('#sa-cp-activity').select_option(label='CI Moodle Quiz')
    admin.locator('#sa-cp-timing').select_option('during')
    admin.locator('#sa-cp-seconds').fill('2')
    admin.get_by_role('button', name='ثبت زمان نمایش').click()
    admin.locator('#sa-cp-session').select_option(label='Chapter 1/02 - Browser.webm')
    admin.locator('#sa-cp-activity').select_option(label='CI Moodle Quiz')
    admin.get_by_role('button', name='ثبت زمان نمایش').click()
    student.goto(origin + '/view.php?' + urlencode({'id': course, 'file': fixture['webmkey'], 'native': 1}))
    player = student.locator('#sam-player')
    player.evaluate('(video) => video.play()')
    mid = student.locator('[data-timing="during"]')
    expect(mid.get_by_role('link', name='CI Moodle Quiz')).to_be_visible(timeout=12000)
    assert player.evaluate('(video) => video.paused'), 'The checkpoint did not pause the video'
    player.evaluate('(video) => { video.currentTime = video.duration - 0.4; return video.play(); }')
    expect(student.locator('[data-timing="after"]').get_by_role('link', name='CI Moodle Quiz')).to_be_visible(timeout=12000)
    print('PASS: native quiz links appear at the configured video timestamp and on completion.', flush=True)

    student.goto(activities + urlencode({'id': course, 'evaluation': 1}))
    student.get_by_role('link', name=re.compile(r'CI Instructor')).click()
    expect(student.locator('body')).to_contain_text('CI Subject')
    student.locator('a[href*="/mod/feedback/complete.php"]').first.click()
    expect(student.locator('input[type=radio][name^=multichoicerated_]')).to_have_count(100)
    expect(student.locator('body')).to_contain_text('تسلط و احاطه بر موضوع درس')
    expect(student.locator('body')).to_contain_text('امکان دسترسی به استاد')
    student.evaluate("""() => {
        const selected = new Set();
        for (const input of document.querySelectorAll('input[type=radio][name^=multichoicerated_]')) {
            if (!selected.has(input.name)) {
                input.click();
                selected.add(input.name);
            }
        }
        if (selected.size !== 20) throw Error('Expected 20 distinct questions');
    }""")
    student.locator('input[name=savevalues]').click()
    expect(student.locator('body')).to_contain_text('ارزیابی شما ثبت شد.')
    admin.goto(evaluation_url)
    admin.get_by_role('link', name='نتایج').click()
    expect(admin.locator('body')).to_contain_text('تسلط و احاطه بر موضوع درس')
    expect(admin.locator('body')).not_to_contain_text('CI Unstarted')
    student.goto(origin + '/request.php?contact=1')
    student.locator('#sam-request-title').fill('CI message to manager')
    student.locator('#sam-request-body').fill('CI native notification')
    student.get_by_role('button', name='ارسال پیام').click()
    admin.goto(origin + '/requests.php?filter=all')
    expect(admin.locator('#region-main')).to_contain_text('CI message to manager')
    expect(admin.locator('#region-main')).to_contain_text('CI native notification')
    print('PASS: message reaches the manager via native Moodle notifications.', flush=True)
    print('PASS: real Moodle quiz navigation, 20-question submission, duplicate prevention and anonymous results.', flush=True)
