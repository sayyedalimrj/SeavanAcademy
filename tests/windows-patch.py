"""Verify the native Windows patch through Moodle's actual learner and administrator UI."""
import json
import os
import re
import sys
from urllib.parse import urlencode

from playwright.sync_api import expect, sync_playwright

with open(sys.argv[1]) as data:
    course = json.load(data)['course']
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
    admin.wait_for_url('**/mod/feedback/view.php?**')
    feedback_url = admin.url
    admin.goto(evaluation_url)
    admin.locator('#instructor').fill('CI Instructor')
    admin.locator('#subject').fill('CI Subject')
    admin.get_by_role('button', name='ساخت فرم ۲۰ سؤالی ارزیابی').click()
    admin.wait_for_url('**/mod/feedback/view.php?**')
    assert admin.url == feedback_url, 'A repeated request duplicated the feedback activity.'

    student = browser.new_page()
    sign_in(student, 'ci-unstarted', 'Ci-Test-Only!2026')
    student.goto(base + '/my/courses.php')
    student.get_by_role('navigation', name='منوی اصلی').get_by_role('link', name='آزمون‌ها').click()
    student.get_by_role('link', name='CI Media Course', exact=True).click()
    expect(student.get_by_role('link', name='CI Moodle Quiz')).to_be_visible()
    student.goto(activities + urlencode({'id': course, 'evaluation': 1}))
    student.get_by_role('link', name=re.compile(r'CI Instructor')).click()
    expect(student.locator('body')).to_contain_text('CI Subject')
    student.locator('a[href*="/mod/feedback/complete.php"]').first.click()
    expect(student.locator('input[type=radio]')).to_have_count(100)
    expect(student.locator('body')).to_contain_text('تسلط و احاطه بر موضوع درس')
    expect(student.locator('body')).to_contain_text('امکان دسترسی به استاد')
    student.evaluate("""() => {
        const selected = new Set();
        for (const input of document.querySelectorAll('input[type=radio]')) {
            if (!selected.has(input.name)) {
                input.click();
                selected.add(input.name);
            }
        }
        if (selected.size !== 20) throw Error('Expected 20 distinct questions');
    }""")
    student.locator('input[type=submit]').last.click()
    expect(student.locator('body')).to_contain_text('ارزیابی شما ثبت شد.')
    admin.goto(evaluation_url)
    admin.get_by_role('link', name='نتایج').click()
    expect(admin.locator('body')).to_contain_text('تسلط و احاطه بر موضوع درس')
    expect(admin.locator('body')).not_to_contain_text('CI Unstarted')
    print('PASS: real Moodle quiz navigation, 20-question submission, duplicate prevention and anonymous results.', flush=True)
