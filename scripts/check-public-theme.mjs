/**
 * Drives a real browser through the admin-set public theme.
 *
 * What matters here is that a colour saved in the admin actually repaints the
 * public site — computed styles, not markup. A grep would happily confirm the
 * variable is printed while every element still rendered teal.
 *
 *   node scripts/check-public-theme.mjs
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

/* -------------------------------------------------- tombol cari di tengah */

await page.goto(BASE, { waitUntil: 'networkidle2' });

const search = await page.evaluate(() => {
    const form = document.querySelector('main form[role="search"]');
    const mid = (el) => { const r = el.getBoundingClientRect(); return r.top + r.height / 2; };
    const segments = [...form.querySelectorAll(':scope > div')].filter((d) => d.offsetWidth > 20);
    return {
        drift: Math.abs(mid(form.querySelector('button[type="submit"]')) - mid(segments[0])),
        label: form.querySelector('label')?.textContent.trim(),
    };
});
check('tombol cari sejajar tengah dengan ruas di sebelahnya', search.drift < 1, `selisih ${search.drift.toFixed(2)}px`);

/* ------------------------------------------------------ nama situs hilang */

const header = await page.evaluate(() => {
    const el = document.querySelector('header');
    const brand = el.querySelector('a');
    const hidden = brand.querySelector('.sr-only');
    const name = brand.querySelector('.sr-only')?.textContent.trim().split(' — ')[0] ?? '';

    // Any element that both contains the name and occupies real width would be
    // showing it. sr-only clips to 1px, so it never qualifies.
    const showing = [...el.querySelectorAll('*')].filter(
        (n) => !n.children.length && n.textContent.includes(name) && n.getBoundingClientRect().width > 10
    );

    return {
        showing: showing.length,
        accessibleName: brand.textContent.trim(),
        srOnlyWidth: hidden ? Math.round(hidden.getBoundingClientRect().width) : null,
    };
});
check('nama situs tidak lagi tampil di navbar', header.showing === 0, `${header.showing} elemen menampilkannya`);
check('tautan beranda tetap punya nama untuk pembaca layar', header.accessibleName.includes('Dynamic CMS'), header.accessibleName);
check('nama itu benar-benar disembunyikan secara visual', header.srOnlyWidth === 1, `lebar ${header.srOnlyWidth}px`);

/* ------------------------------------------------------------ warna dasar */

const paint = () => page.evaluate(() => {
    const hex = (rgb) => '#' + rgb.match(/\d+/g).slice(0, 3).map((n) => (+n).toString(16).padStart(2, '0')).join('');
    const styles = getComputedStyle(document.documentElement);
    const btn = document.querySelector('main form[role="search"] button[type="submit"]');
    const link = document.querySelector('header nav a');
    return {
        teal700: styles.getPropertyValue('--color-teal-700').trim(),
        button: hex(getComputedStyle(btn).backgroundColor),
        buttonInk: hex(getComputedStyle(btn).color),
        font: getComputedStyle(document.body).fontFamily.split(',')[0].replace(/["']/g, ''),
        linkFocusRing: getComputedStyle(link).outlineColor,
        footer: (() => {
            const el = document.querySelector('footer');
            const heading = el.querySelector('h2');
            const ratio = (a, b) => {
                const lum = (rgb) => {
                    const [r, g, b2] = rgb.match(/\d+/g).slice(0, 3)
                        .map((n) => { const c = n / 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; });
                    return 0.2126 * r + 0.7152 * g + 0.0722 * b2;
                };
                const [x, y] = [lum(a), lum(b)];
                return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
            };
            const bg = getComputedStyle(el).backgroundColor;
            return {
                bg: hex(bg),
                heading: +ratio(bg, getComputedStyle(heading).color).toFixed(2),
                body: +ratio(bg, getComputedStyle(el).color).toFixed(2),
            };
        })(),
    };
});

const before = await paint();
check('slider dan tombol memakai warna tersimpan', before.button === '#02468b', before.button);
check('huruf mengikuti pilihan admin', before.font === 'Inter', before.font);

/* --------------------------------------- ubah tema dari admin, lalu ulangi */

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle2' });
await page.type('input[name="email"]', USER);
await page.type('input[name="password"]', PASS);
await page.evaluate(() => {
    const q = document.body.innerText.match(/(\d+)\s*\+\s*(\d+)/);
    const want = String(Number(q[1]) + Number(q[2]));
    const input = [...document.querySelectorAll('input[name="captcha"]')].find((el) => el.value === want);
    document.querySelector(`label[for="${input.id}"]`)?.click() ?? input.click();
});
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('button[type="submit"]')]);

await page.goto(`${BASE}/admin/settings/appearance`, { waitUntil: 'networkidle2' });
check('layar tampilan terbuka', page.url().includes('appearance'), page.url().replace(BASE, ''));

// A deliberately pale colour: white lettering on it would fail WCAG, so this
// also proves the ink is derived rather than assumed.
const PALE = '#f4c430';
await page.evaluate((hex) => {
    const text = document.querySelector('input[name="primary_color"]');
    text.value = hex;
    text.dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('select[name="font_family"]').value = 'serif';
    // A dark footer: every text colour on it has to move, not stay slate.
    const footer = document.querySelector('input[name="footer_color"]');
    footer.value = '#0F172A';
    footer.dispatchEvent(new Event('input', { bubbles: true }));
}, PALE);

const swatch = await page.$eval('[data-color-picker]', (el) => el.value);
check('kotak warna mengikuti isian hex', swatch === PALE, swatch);

await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('form[action*="appearance"] button[type="submit"]'),
]);

await page.goto(BASE, { waitUntil: 'networkidle2' });
const after = await paint();

check('warna baru mengecat situs publik', after.button === PALE, after.button);
check('seluruh gradasi ikut berubah', after.teal700 === PALE, after.teal700);
check('huruf baru diterapkan', after.font !== 'Inter', after.font);

// #f4c430 against white is 1.7:1; against #0b1b2b it is 11.9:1.
check('tulisan di atas warna pucat jadi gelap, bukan putih', after.buttonInk === '#0b1b2b', after.buttonInk);
check('warna footer mengikuti admin', after.footer.bg === '#0f172a', after.footer.bg);
check('judul footer tetap terbaca di latar gelap', after.footer.heading >= 4.5, `${after.footer.heading}:1`);
check('teks footer tetap terbaca di latar gelap', after.footer.body >= 4.5, `${after.footer.body}:1`);

/* ------------------------------------------------------------- kembalikan */

await page.goto(`${BASE}/admin/settings/appearance`, { waitUntil: 'networkidle2' });
await page.evaluate(() => {
    const text = document.querySelector('input[name="primary_color"]');
    text.value = '#02468B';
    text.dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('select[name="font_family"]').value = 'inter';
    const footer = document.querySelector('input[name="footer_color"]');
    footer.value = '#F8FAFC';
    footer.dispatchEvent(new Event('input', { bubbles: true }));
});
await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('form[action*="appearance"] button[type="submit"]'),
]);

await page.goto(BASE, { waitUntil: 'networkidle2' });
const restored = await paint();
check('tema semula dipulihkan', restored.button === '#02468b' && restored.font === 'Inter', `${restored.button}, ${restored.font}`);

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
