/**
 * Drives a real browser through the admin carousel screens.
 *
 * The PHPUnit suite runs on SQLite and never renders JavaScript, so the
 * DataTable, the drag-and-drop reorder and the preview page are only ever
 * proven here — against the live stack, real MySQL and the built assets.
 *
 *   node scripts/check-carousel-admin.mjs
 */
import puppeteer from 'puppeteer-core';

const BASE = process.env.APP_URL ?? 'http://localhost:8010';
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const USER = process.env.ADMIN_EMAIL ?? 'admin@example.test';
const PASS = process.env.ADMIN_PASSWORD ?? 'password';

const report = [];
const check = (label, ok, detail = '') => {
    report.push(ok);
    console.log(`  ${ok ? 'OK  ' : 'GAGAL'}  ${label}${detail ? ` — ${detail}` : ''}`);
};

const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 1000 });

/* ---------------------------------------------------------------- masuk */

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle2' });
await page.type('input[name="email"]', USER);
await page.type('input[name="password"]', PASS);

// The captcha offers four choices; read the sum from the question and pick it.
// Clicking the label, not the input, is what a visitor actually does — the
// radio itself is sr-only.
const solved = await page.evaluate(() => {
    const question = document.body.innerText.match(/(\d+)\s*\+\s*(\d+)/);
    if (!question) return null;
    const want = String(Number(question[1]) + Number(question[2]));
    const input = [...document.querySelectorAll('input[name="captcha"]')].find((el) => el.value === want);
    if (!input) return null;
    document.querySelector(`label[for="${input.id}"]`)?.click() ?? input.click();
    return input.checked ? want : null;
});
check('captcha terjawab', solved !== null, solved ? `pilih ${solved}` : 'pertanyaan tidak ditemukan');

await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('button[type="submit"]'),
]);
check('masuk sebagai admin', !page.url().includes('/login'), page.url().replace(BASE, ''));
if (page.url().includes('/login')) {
    console.log(`\n  Gagal masuk. Setel ADMIN_EMAIL / ADMIN_PASSWORD bila kredensial berbeda.`);
    await browser.close();
    process.exit(1);
}

/* ------------------------------------------------------------- daftar */

await page.goto(`${BASE}/admin/settings/carousel`, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 1200)); // DataTable fetches after load

const table = await page.evaluate(() => {
    const rows = [...document.querySelectorAll('tbody tr')];
    return {
        rows: rows.length,
        first: rows[0]?.innerText.replace(/\s+/g, ' ').trim().slice(0, 80) ?? '',
        thumbs: document.querySelectorAll('tbody img').length,
        headers: [...document.querySelectorAll('thead th')].map((th) => th.innerText.trim()),
    };
});
check('DataTable terisi dari server', table.rows === 4, `${table.rows} baris`);
check('kolom sesuai rancangan', table.headers.length >= 7, table.headers.join(' | '));
check('pratinjau gambar tampil', table.thumbs === 4, `${table.thumbs} thumbnail`);

/* -------------------------------------------------------------- urutan */

// Driven through the control an editor actually uses, so this covers the JS
// wiring as well as the endpoint.
const order = () => page.evaluate(
    () => [...document.querySelectorAll('[data-sortable-item]')].map((el) => el.dataset.id)
);

const before = await order();
check('daftar urut tampil', before.length === 4, `${before.length} item`);

await page.click('[data-sortable-item]:first-child [data-sortable-down]');
await new Promise((r) => setTimeout(r, 800));

const after = await order();
check('tombol turun menukar posisi', after[0] === before[1] && after[1] === before[0], after.slice(0, 2).join(' → '));

const status = await page.$eval('[data-sortable-status]', (el) => el.textContent.trim());
check('perubahan dilaporkan ke pembaca layar', status.length > 0, status || 'kosong');

await page.reload({ waitUntil: 'networkidle2' });
const persisted = await order();
check('urutan bertahan setelah muat ulang', persisted.join() === after.join(), persisted.slice(0, 2).join(' → '));

// Put it back, so running this twice does not keep shuffling the live site.
await page.click('[data-sortable-item]:first-child [data-sortable-down]');
await new Promise((r) => setTimeout(r, 800));
const restored = await order();
check('urutan semula dipulihkan', restored.join() === before.join(), restored.slice(0, 2).join(' → '));

/* -------------------------------------------------------------- borang */

await page.goto(`${BASE}/admin/settings/carousel/create`, { waitUntil: 'networkidle2' });
const form = await page.evaluate(() => {
    const names = [...document.querySelectorAll('form [name]')].map((el) => el.name);
    return { names, missing: ['category', 'title', 'description', 'alt_text', 'button_text', 'link', 'image'].filter((n) => !names.includes(n)) };
});
check('borang memuat seluruh medan hero', form.missing.length === 0, form.missing.join(', ') || 'lengkap');

/* ------------------------------------------------------------ pratinjau */

await page.goto(`${BASE}/admin/settings/carousel/preview`, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 500));
const preview = await page.evaluate(() => {
    const hero = document.querySelector('[data-hero]');
    return {
        present: !!hero,
        slides: hero?.querySelectorAll('[data-hero-slide]').length ?? 0,
        height: hero ? Math.round(hero.getBoundingClientRect().height) : 0,
    };
});
check('pratinjau memakai komponen yang sama', preview.present, `${preview.slides} slide`);
check('pratinjau berukuran wajar', preview.height > 400, `${preview.height}px`);

/* ---------------------------------------------------------------- akhir */

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
