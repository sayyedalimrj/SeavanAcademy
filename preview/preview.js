'use strict';

const courses = [
    { title: 'آشنایی با اصول متره و برآورد', category: 'دفتر فنی', progress: 42, lessons: ['معرفی دوره و ساختار ریزمتره', 'خواندن نقشه و برداشت مقادیر', 'تنظیم شیت ریزمتره'] },
    { title: 'اکسل کاربردی برای امور مهندسی', category: 'نرم‌افزار', progress: 20, lessons: ['آشنایی با محیط اکسل', 'فرمول‌های کاربردی', 'گزارش صورت‌وضعیت'] },
    { title: 'مدیریت پروژه‌های عمرانی', category: 'مدیریت پروژه', progress: 0, lessons: ['مبانی برنامه‌ریزی', 'ساختار شکست کار', 'کنترل پیشرفت پروژه'] },
    { title: 'مستندسازی و بایگانی پروژه', category: 'دفتر فنی', progress: null, lessons: ['ساختار پرونده پروژه', 'گردش و شماره‌گذاری اسناد'] },
    { title: 'آشنایی با AutoCAD', category: 'نرم‌افزار', progress: null, lessons: ['محیط ترسیم', 'لایه‌بندی نقشه'] },
    { title: 'ارتباط مؤثر در محیط کار', category: 'مهارت فردی', progress: 100, lessons: ['گفت‌وگوی حرفه‌ای', 'بازخورد سازنده'] },
];

const groups = [
    ['دوره‌ها و فایل‌ها', '▤', [['archive', 'مدیریت آرشیو', 'هاردها، بارگذاری و مجوز دانلود'], ['courses', 'دوره‌ها و دسته‌بندی', 'فهرست دوره‌ها و ثبت آموزش جدید']]],
    ['کارکنان و دسترسی', '♙', [['staff', 'کارکنان و گروه‌ها', 'کاربران، نقش‌ها و گروه‌های آموزشی'], ['import', 'ورود گروهی از Excel', 'پیش‌نمایش فرم ورود کارکنان']]],
    ['آماده‌سازی رسانه', '▷', [['media', 'سلامت کتابخانه', 'بررسی وضعیت فایل‌های آموزشی'], ['queue', 'صف پردازش', 'کارهای رسانه‌ای در انتظار و انجام‌شده']]],
    ['گزارش و نگهداری', '◴', [['reports', 'گزارش یادگیری', 'نمونه پیشرفت کارکنان'], ['health', 'سلامت سرور', 'وضعیت سرویس‌ها و پشتیبان‌گیری']]],
];

const main = document.querySelector('#main');
const number = new Intl.NumberFormat('fa-IR');
const fmt = value => number.format(value);
const themeButton = document.querySelector('#theme');
const setDark = dark => {
    document.body.classList.toggle('sa-dark', dark);
    themeButton.setAttribute('aria-pressed', String(dark));
};
try { setDark(localStorage.getItem('seavan-preview-dark') === '1'); } catch {}
themeButton.onclick = () => {
    const dark = !document.body.classList.contains('sa-dark');
    setDark(dark);
    try { localStorage.setItem('seavan-preview-dark', dark ? '1' : '0'); } catch {}
};

function courseCard(course) {
    const href = '#course/' + courses.indexOf(course);
    return `<article class="seavan-course">
        <a class="seavan-course-cover" href="${href}" aria-label="${course.title}">
            <span class="sa-cover-fallback"><span class="sa-cover-brand">SEAVAN ACADEMY</span><span class="sa-cover-category">${course.category}</span></span>
            <span class="seavan-course-symbol" aria-hidden="true">▷</span>
        </a>
        <div class="sa-course-body">
            <span class="sa-category-label">${course.category}</span>
            <h3><a href="${href}">${course.title}</a></h3>
            <span class="seavan-course-label">${fmt(course.lessons.length)} جلسه ${course.progress === null ? '' : '· ' + fmt(course.progress) + '٪ مشاهده‌شده'}</span>
            ${course.progress === null ? '' : `<progress class="seavan-progress" max="100" value="${course.progress}" aria-label="پیشرفت دوره"></progress>`}
            <a class="btn btn-outline-primary sa-course-open" href="${href}">مشاهده دوره</a>
        </div>
    </article>`;
}

function showLibrary(learning) {
    const source = learning ? courses.filter(course => course.progress !== null) : courses;
    main.innerHTML = `<div class="seavan-shell">
        <section class="sa-welcome"><div class="sa-hero-art" aria-hidden="true"></div><span class="sa-kicker">SEAVAN ACADEMY</span>
        <h1>${learning ? 'یادگیری من' : 'آکادمی سیوان'}</h1><p>${learning ? 'آموزش‌های اختصاص‌یافته و پیشرفت مشاهده جلسات' : 'آموزش‌های داخلی شرکت سیوان تدبیر تجارت'}</p></section>
        ${learning ? `<section class="seavan-resume"><div class="sa-resume-thumb" aria-hidden="true">▷</div><div class="seavan-resume-copy"><span class="seavan-eyebrow">ادامه تماشا</span><h3>${courses[0].title}</h3><p class="seavan-note">${courses[0].lessons[1]} · ۴۲٪ مشاهده‌شده</p></div><a class="btn btn-primary" href="#course/0/1">ادامه جلسه</a></section>` : ''}
        <div class="sa-title-row"><h2 class="seavan-section">${learning ? 'دوره‌های من' : 'کتابخانه آموزش‌ها'}</h2><a class="sa-subtle-link" href="#request">درخواست آموزش</a></div>
        <form class="seavan-search" role="search">
            <input class="form-control" type="search" aria-label="جست‌وجوی آموزش" placeholder="نام دوره یا جلسه را بنویسید…">
            <select class="custom-select" aria-label="دسته‌بندی آموزش"><option value="">همه دسته‌ها</option>${[...new Set(source.map(c => c.category))].map(category => `<option>${category}</option>`).join('')}</select>
            <button class="btn btn-primary">جست‌وجو</button>
        </form>
        <div class="seavan-grid" id="courses" aria-live="polite"></div>
    </div>`;

    const form = main.querySelector('form');
    const [search, category] = form.querySelectorAll('input,select');
    const normalize = value => value.trim().replaceAll('ي', 'ی').replaceAll('ك', 'ک').replace(/[\s‌]+/g, ' ').toLocaleLowerCase('fa');
    function filterCourses() {
        const query = normalize(search.value);
        const matching = source.filter(course => (!category.value || course.category === category.value) &&
            normalize([course.title, ...course.lessons].join(' ')).includes(query));
        document.querySelector('#courses').innerHTML = matching.map(courseCard).join('') ||
            '<p class="seavan-empty">دوره‌ای با این مشخصات پیدا نشد.</p>';
    }
    form.onsubmit = event => { event.preventDefault(); filterCourses(); };
    search.oninput = category.onchange = filterCourses;
    filterCourses();
}

function showCourse(id, selected) {
    const course = courses[id];
    const lesson = course?.lessons[selected];
    if (!lesson) return showLibrary(false);
    main.innerHTML = `<div class="sam">
        <div class="sam-coursehead"><a class="sam-crumb" href="#library">بازگشت به کتابخانه</a><h1 class="sam-course-title">${course.title}</h1></div>
        <div class="sam-layout">
            <section class="sam-main">
                <div class="preview-media"><video controls playsinline preload="metadata" src="assets/sample.mp4" poster="assets/sample.jpg" aria-label="ویدیوی نمونه جلسه"></video></div>
                <div class="sam-tools sam-playtools preview-player-tools">
                    <label>سرعت <select class="custom-select" id="speed" aria-label="سرعت پخش"><option value="1">۱×</option><option value="1.25">۱٫۲۵×</option><option value="1.5">۱٫۵×</option><option value="2">۲×</option></select></label>
                    <button class="btn btn-outline-primary" data-seek="-10" type="button">۱۰ ثانیه عقب</button>
                    <button class="btn btn-outline-primary" data-seek="10" type="button">۱۰ ثانیه جلو</button>
                </div>
                <h2 class="sam-title">${lesson}</h2>
                <p class="sam-muted">ویدیوی نمونه برای دیدن کنترل‌های پخش</p>
                <div class="sam-tools">
                    ${selected > 0 ? `<a class="btn btn-outline-primary" href="#course/${id}/${selected - 1}">جلسه قبلی</a>` : ''}
                    ${selected + 1 < course.lessons.length ? `<a class="btn btn-primary" href="#course/${id}/${selected + 1}">جلسه بعدی</a>` : ''}
                </div>
            </section>
            <aside class="sam-side" aria-label="محتوای دوره">
                <div class="sam-side-header"><h2 class="seavan-section">محتوای دوره</h2><span class="sam-muted">${fmt(course.lessons.length)} جلسه</span></div>
                <input type="search" class="form-control" id="lesson-search" aria-label="جست‌وجوی جلسات" placeholder="جست‌وجو در جلسات…">
                <details class="sam-chapter" open><summary>فصل اول · ${fmt(course.lessons.length)} جلسه</summary>
                    ${course.lessons.map((title, i) => `<a class="sam-lesson${i === selected ? ' active' : ''}" href="#course/${id}/${i}" ${i === selected ? 'aria-current="page"' : ''}>${title}<small>${fmt(i + 1)}</small></a>`).join('')}
                </details>
            </aside>
        </div>
    </div>`;
    const video = main.querySelector('video');
    main.querySelector('#speed').onchange = event => { video.playbackRate = Number(event.target.value); };
    main.querySelectorAll('[data-seek]').forEach(button => {
        button.onclick = () => { if (Number.isFinite(video.duration)) video.currentTime = Math.max(0, Math.min(video.duration, video.currentTime + Number(button.dataset.seek))); };
    });
    main.querySelector('#lesson-search').oninput = event => {
        const query = event.target.value.trim();
        main.querySelectorAll('.sam-lesson').forEach(link => { link.hidden = !link.textContent.includes(query); });
    };
}

function showManagement(section) {
    const item = groups.flatMap(group => group[2]).find(entry => entry[0] === section);
    if (!item) {
        main.innerHTML = `<h1 class="sa-page-title">مرکز مدیریت</h1><p class="seavan-note">دوره‌ها، دسترسی‌ها و وضعیت سامانه</p><div class="seavan-actions">${groups.map(([title, icon, links]) =>
            `<section class="sa-admin-panel"><div class="sa-admin-heading"><span class="sa-group-icon" aria-hidden="true">${icon}</span><h3>${title}</h3></div>${links.map(([key, label, description]) =>
                `<a class="sa-admin-link" href="#manage/${key}"><span>${label}</span><small>${description}</small></a>`).join('')}</section>`).join('')}</div>`;
        return;
    }

    const tables = {
        archive: [['مسیر نمونه', 'محتوا', 'وضعیت'], [['آرشیو دفتر فنی', '۳ دوره', 'در دسترس'], ['آرشیو آموزش عمومی', '۲ دوره', 'در دسترس']]],
        courses: [['دوره', 'دسته‌بندی', 'جلسه'], courses.map(course => [course.title, course.category, fmt(course.lessons.length)])],
        staff: [['نام نمایشی', 'واحد', 'نقش'], [['همکار نمونه', 'دفتر فنی', 'فراگیر'], ['مدیر نمونه', 'آموزش', 'مدیر آموزش']]],
        media: [['فایل', 'قالب', 'وضعیت'], [['معرفی دوره متره', 'MP4', 'آماده پخش'], ['فرمول‌های کاربردی', 'MP4', 'نیازمند بررسی']]],
        queue: [['عملیات', 'آموزش', 'وضعیت'], [['تولید کاور', 'اصول متره و برآورد', 'انجام‌شده'], ['تبدیل کم‌حجم', 'اکسل کاربردی', 'در انتظار']]],
        reports: [['دوره', 'واحد', 'پیشرفت'], [['اصول متره و برآورد', 'دفتر فنی', '۴۲٪'], ['اکسل کاربردی', 'دفتر فنی', '۲۰٪']]],
        health: [['سرویس', 'آخرین بررسی', 'وضعیت'], [['آرشیو دوره‌ها', 'امروز', 'در دسترس'], ['پشتیبان‌گیری', 'امروز', 'موفق']]],
    };
    const table = tables[section];
    const content = table ? `<div class="table-responsive" tabindex="0"><table class="preview-table"><thead><tr>${table[0].map(heading => `<th scope="col">${heading}</th>`).join('')}</tr></thead><tbody>${table[1].map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` :
        '<section class="sa-form-panel"><form><label for="staff-file">فایل کارکنان</label><input class="form-control" id="staff-file" type="file" accept=".xlsx,.csv" required><button class="btn btn-primary preview-action">بررسی نمونه</button><p class="preview-muted" role="status"></p></form></section>';
    main.innerHTML = `<a class="sa-subtle-link preview-back" href="#manage">بازگشت به مرکز مدیریت</a><h1 class="sa-page-title">${item[1]}</h1><p class="seavan-note preview-caption">${item[2]} · نمونه</p>${content}`;
    if (!table) wireDemoForm('فایل برای نمایش فرم انتخاب شد.');
}

function wireDemoForm(message) {
    const form = main.querySelector('form');
    form.onsubmit = event => {
        event.preventDefault();
        form.querySelector('[role="status"]').textContent = message;
    };
}

function showRequest() {
    main.innerHTML = `<a class="sa-subtle-link preview-back" href="#library">بازگشت به آموزش‌ها</a><h1 class="sa-page-title">درخواست آموزش</h1>
        <section class="sa-form-panel"><form><label for="topic">عنوان آموزش</label><input id="topic" class="form-control" required maxlength="120">
        <label for="reason">دلیل درخواست</label><textarea id="reason" class="form-control" rows="4" maxlength="400" required></textarea>
        <button class="btn btn-primary preview-action">ارسال درخواست نمونه</button><p class="preview-muted" role="status"></p></form></section>`;
    wireDemoForm('فرم نمونه تکمیل شد.');
}

function showLogin() {
    main.innerHTML = `<div class="pagelayout-login preview-login-form"><div class="seavan-login-wrapper">
        <div class="seavan-login-story"><div class="seavan-login-logo"><img src="assets/company-logo.svg" alt="سیوان"></div><span class="seavan-login-kicker">SEAVAN ACADEMY</span><h1>آکادمی سیوان</h1><p>سامانه آموزش داخلی شرکت سیوان تدبیر تجارت</p></div>
        <div class="login-container"><h2 class="login-heading">ورود به آکادمی</h2><p>نمای فرم ورود با اطلاعات نمونه</p>
            <label for="username">نام کاربری</label><input id="username" class="form-control" value="کاربر نمونه" readonly>
            <label for="password">رمز عبور</label><input id="password" class="form-control" type="password" placeholder="••••••••" readonly>
            <a href="#library" id="loginbtn" class="btn btn-primary">ورود به پیش‌نمایش</a>
        </div></div></div>`;
}

function render(moveFocus) {
    const [page, detail, lesson] = location.hash.slice(1).split('/');
    const nav = ['learning', 'manage', 'login'].includes(page) ? page : 'library';
    document.querySelector('#frame').classList.toggle('sa-management', nav === 'manage');
    document.querySelector('#brand-label').textContent = nav === 'manage' ? 'مرکز مدیریت' : 'آکادمی';
    document.querySelectorAll('[data-nav]').forEach(link => {
        if (link.dataset.nav === nav) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });

    if (page === 'course') showCourse(Number(detail), Number(lesson || 0));
    else if (page === 'manage') showManagement(detail);
    else if (page === 'request') showRequest();
    else if (page === 'login') showLogin();
    else showLibrary(page === 'learning');

    if (moveFocus) {
        main.focus({ preventScroll: true });
        window.scrollTo(0, 0);
    }
}

window.addEventListener('hashchange', () => render(true));
render(false);
