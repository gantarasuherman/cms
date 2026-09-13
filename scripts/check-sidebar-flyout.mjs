/**
 * Drives a real browser against the running app to check the collapsed
 * sidebar's flyouts.
 *
 * This exists because markup and CSS greps kept reporting the feature as
 * present while it was invisible in practice: first clipped by the scrolling
 * <nav>, then held at display:none by Tailwind's layered
 * `[hidden] { display:none !important }`. Neither is detectable without
 * actually laying the page out.
 *
 *   node scripts/check-sidebar-flyout.mjs
 *
 * Needs the app on http://localhost:8010 and Google Chrome installed.
 */
import puppeteer from 'puppeteer-core';

const OUT = process.env.SHOT_DIR ?? '/tmp';
const BASE = process.env.APP_URL ?? 'http://localhost:8010';
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox'],
});

const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });

const report = [];
const check = (label, ok, detail = '') => {
    report.push(ok);
    console.log(`  ${ok ? 'OK  ' : 'GAGAL'}  ${label}${detail ? ` — ${detail}` : ''}`);
};

/* ---------------------------------------------------------------- masuk */

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle2' });

const question = await page.$eval('legend', (el) => el.textContent.trim());
const [, a, b] = question.match(/(\d+)\s*\+\s*(\d+)/);
const answer = String(Number(a) + Number(b));

await page.type('#email', 'admin@example.test');
await page.type('#password', 'password');

// The radios are sr-only, so the visible label is what a person clicks.
await page.evaluate((value) => {
    const input = document.querySelector(`input[name="captcha"][value="${value}"]`);
    document.querySelector(`label[for="${input.id}"]`).click();
}, answer);

await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }),
    page.click('form button[type="submit"]'),
]);

check('masuk ke dashboard', page.url().includes('/admin/dashboard'), page.url());

/* ------------------------------------------------------------- ciutkan */

await page.click('[data-sidebar-collapse]');
await new Promise((r) => setTimeout(r, 400));

const rail = await page.$eval('[data-sidebar]', (el) => el.getBoundingClientRect().width);
check('rail menyempit ke lebar ikon', rail < 100, `${Math.round(rail)}px`);

const labelWidth = await page.$eval('.sidebar-label', (el) => el.getBoundingClientRect().width);
check('label disembunyikan secara visual', labelWidth <= 2, `${Math.round(labelWidth)}px`);

/* --------------------------------------------------------------- hover */

await page.hover('.sidebar-node:has(> .sidebar-subpanel)');
await new Promise((r) => setTimeout(r, 300));

const hover = await page.evaluate(() => {
    const node = document.querySelector('.sidebar-node:has(> .sidebar-subpanel)');
    const panel = node.querySelector(':scope > .sidebar-flyout');
    const box = panel.getBoundingClientRect();
    const railBox = document.querySelector('[data-sidebar]').getBoundingClientRect();
    const painted = document.elementFromPoint(box.left + box.width / 2, box.top + 12);

    return {
        opacity: getComputedStyle(panel).opacity,
        display: getComputedStyle(panel).display,
        left: Math.round(box.left),
        railRight: Math.round(railBox.right),
        width: Math.round(box.width),
        onTop: panel.contains(painted) || painted === panel,
        links: panel.querySelectorAll('a').length,
        title: panel.querySelector('.sidebar-flyout-title')?.textContent.trim(),
    };
});

check('flyout terlihat saat hover', hover.opacity === '1' && hover.display !== 'none' && hover.width > 0,
    `display=${hover.display} opacity=${hover.opacity}`);
check('tidak terpotong dan berada di atas', hover.onTop);
check('menempel di kanan rail', Math.abs(hover.left - hover.railRight) <= 1,
    `left=${hover.left} rail=${hover.railRight}`);
check('memuat sub-menu berjudul', hover.links > 0 && Boolean(hover.title),
    `${hover.links} tautan, judul "${hover.title}"`);

await page.screenshot({ path: `${OUT}/flyout-hover.png` });

/* --------------------------------------------------------------- fokus */

await page.mouse.move(1200, 800);
await new Promise((r) => setTimeout(r, 250));

const buttonId = await page.evaluate(() => {
    const button = document.querySelector('.sidebar-node:has(> .sidebar-subpanel) button');
    button.id ||= 'flyout-probe';
    return button.id;
});

await page.focus(`#${buttonId}`);
await new Promise((r) => setTimeout(r, 250));

const focused = await page.evaluate((id) => {
    const node = document.getElementById(id).closest('.sidebar-node');
    const panel = node.querySelector(':scope > .sidebar-flyout');

    return {
        focusWithin: node.matches(':focus-within'),
        opacity: getComputedStyle(panel).opacity,
        width: Math.round(panel.getBoundingClientRect().width),
    };
}, buttonId);

check('terbuka lewat fokus papan ketik', focused.opacity === '1' && focused.width > 0,
    `focus-within=${focused.focusWithin}`);

await page.screenshot({ path: `${OUT}/flyout-focus.png` });

/* -------------------------------------------------------------- escape */

await page.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 250));

const dismissed = await page.evaluate((id) => {
    const node = document.getElementById(id).closest('.sidebar-node');
    return getComputedStyle(node.querySelector(':scope > .sidebar-flyout')).opacity;
}, buttonId);

// WCAG 1.4.13: dismissible without moving the pointer or the focus.
check('Escape menutup tanpa memindahkan fokus', dismissed === '0', `opacity=${dismissed}`);

await browser.close();

const failed = report.filter((ok) => !ok).length;
console.log(`\n${report.length - failed}/${report.length} pemeriksaan lolos`);
process.exit(failed === 0 ? 0 : 1);
