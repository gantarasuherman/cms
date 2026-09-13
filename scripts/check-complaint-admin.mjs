/**
 * Drives a real browser through the complaint and transcript screens.
 *
 * Scroll containment, a dialog's placement and where focus lands after Escape
 * are all behaviour. None of it is visible to a markup grep, and the PHP suite
 * renders no JavaScript.
 *
 *   node scripts/check-complaint-admin.mjs
 */
import puppeteer from 'puppeteer-core';

const BASE = process.env.APP_URL ?? 'http://localhost:8010';
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const report = [];
const check = (label, ok, detail = '') => {
    report.push(ok);
    console.log(`  ${ok ? 'OK  ' : 'GAGAL'}  ${label}${detail ? ` — ${detail}` : ''}`);
};

const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const page = await browser.newPage();
page.on('dialog', (d) => d.accept());
await page.setViewport({ width: 1400, height: 900 });

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle2' });
await page.type('input[name="email"]', process.env.ADMIN_EMAIL ?? 'admin@example.test');
await page.type('input[name="password"]', process.env.ADMIN_PASSWORD ?? 'password');
await page.evaluate(() => {
    const q = document.body.innerText.match(/(\d+)\s*\+\s*(\d+)/);
    const want = String(Number(q[1]) + Number(q[2]));
    const i = [...document.querySelectorAll('input[name="captcha"]')].find((e) => e.value === want);
    document.querySelector(`label[for="${i.id}"]`)?.click() ?? i.click();
});
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('button[type="submit"]')]);

/* -------------------------------------------------------------- transkrip */

await page.goto(`${BASE}/admin/bot/conversations`, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 1200));

// Found by the column's own heading rather than a guessed index: the table
// gains columns, and a hardcoded position quietly picks the wrong one.
const longest = await page.evaluate(() => {
    const headers = [...document.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    const column = headers.indexOf('Pesan');

    return [...document.querySelectorAll('tbody tr')]
        .map((row) => ({
            n: Number(row.children[column]?.textContent.trim() || 0),
            href: row.querySelector('a[href*="/conversations/"]')?.href,
        }))
        .filter((row) => row.href)
        .sort((a, b) => b.n - a.n)[0];
});

check('ada percakapan untuk diperiksa', !!longest?.href, `${longest?.n ?? 0} pesan`);

await page.goto(longest.href, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 700));

const transcript = await page.evaluate(() => {
    const log = document.querySelector('[data-transcript]');
    return {
        box: Math.round(log.clientHeight),
        content: Math.round(log.scrollHeight),
        scrolls: log.scrollHeight > log.clientHeight + 4,
        atBottom: Math.abs(log.scrollHeight - log.clientHeight - log.scrollTop) < 4,
        pageGrew: document.documentElement.scrollHeight > window.innerHeight + 40,
        role: log.getAttribute('role'),
        viewport: window.innerHeight,
    };
});

check('kotak percakapan tidak melebihi layar', transcript.box < transcript.viewport, `${transcript.box}px dari ${transcript.viewport}px`);
check('isinya menggulir di dalam kotak', transcript.scrolls, `${transcript.content}px isi`);
// A chat is read from the bottom: landing on the first "halo" of a long
// conversation means scrolling past everything to reach what just happened.
check('dibuka pada pesan terbaru', transcript.atBottom);
check('halaman tidak ikut memanjang', !transcript.pageGrew);
check('transkrip diumumkan sebagai log', transcript.role === 'log', transcript.role);

/* --------------------------------------------------------------- lightbox */

await page.goto(`${BASE}/admin/complaints`, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 1200));

const complaint = await page.evaluate(() => document.querySelector('tbody a[href*="/complaints/"]')?.href);
await page.goto(complaint, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 400));

const thumbs = await page.evaluate(() => document.querySelectorAll('[data-lightbox]').length);
check('ada lampiran foto', thumbs > 0, `${thumbs} lampiran`);

await page.click('[data-lightbox]');
await new Promise((r) => setTimeout(r, 400));

const box = await page.evaluate(() => {
    const dialog = document.querySelector('[data-lightbox-dialog]');
    const rect = dialog.getBoundingClientRect();
    return {
        open: dialog.open,
        dx: Math.abs((rect.left + rect.width / 2) - window.innerWidth / 2),
        dy: Math.abs((rect.top + rect.height / 2) - window.innerHeight / 2),
        image: dialog.querySelector('[data-lightbox-image]')?.getAttribute('src') ?? '',
        title: dialog.querySelector('[data-lightbox-title]')?.textContent.trim(),
        focused: document.activeElement?.dataset?.lightboxClose !== undefined,
        steppers: !dialog.querySelector('[data-lightbox-prev]').hidden,
    };
});

check('klik lampiran membuka popup', box.open);
check('popup terpusat', box.dx < 2 && box.dy < 2, `meleset ${box.dx.toFixed(1)}, ${box.dy.toFixed(1)}`);
check('gambarnya dimuat', box.image.includes('/attachment/'), box.image.slice(-30));
check('judulnya menjelaskan gambar apa', (box.title ?? '').length > 0, box.title);
check('fokus masuk ke dalam popup', box.focused);
// One picture needs no stepping controls and no "1 / 1".
check('satu gambar tidak diberi tombol arah', !box.steppers);

await page.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 300));

const closed = await page.evaluate(() => ({
    open: document.querySelector('[data-lightbox-dialog]').open,
    focusBack: document.activeElement?.dataset?.lightbox !== undefined,
}));

check('Escape menutup popup', !closed.open);
check('fokus kembali ke lampiran yang diklik', closed.focusBack);

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
