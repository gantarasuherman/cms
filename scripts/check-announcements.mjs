/**
 * Drives a real browser through the announcement modal and its ticker.
 *
 * Every claim here is about timing, focus or geometry — the modal opening once,
 * the strip travelling, focus returning after Escape. None of it is visible to
 * a markup grep.
 *
 *   node scripts/check-announcements.mjs
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
await page.setViewport({ width: 1440, height: 900 });

const state = () => page.evaluate(() => {
    const root = document.querySelector('[data-announcements]');
    const modal = root?.querySelector('[data-announcement-modal]');
    const items = [...root.querySelectorAll('[data-ticker-item]')];
    const visible = items.findIndex((i) => !i.hasAttribute('aria-hidden'));
    const track = root.querySelector('[data-ticker-track]');
    return {
        open: modal?.open ?? false,
        items: items.length,
        visible,
        shift: track ? Math.round(parseFloat((track.style.transform.match(/-?[\d.]+/) ?? [0])[0])) : null,
        focused: document.activeElement?.tagName + (document.activeElement?.dataset?.announcementOpen !== undefined ? '[ticker]' : ''),
        tabbableHidden: items
            .filter((_, i) => i !== visible)
            .flatMap((i) => [...i.querySelectorAll('button')])
            .filter((b) => b.getAttribute('tabindex') !== '-1').length,
    };
});

/* ------------------------------------------------------- kunjungan pertama */

await page.goto(BASE, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 400));

let s = await state();
check('modal terbuka pada kunjungan pertama', s.open);
check('tiga pengumuman masuk ke strip', s.items === 3, `${s.items} baris`);
check('baris tersembunyi keluar dari urutan Tab', s.tabbableHidden === 0);

const heading = await page.evaluate(() => document.activeElement?.tagName);
check('fokus masuk ke dalam modal', ['H2', 'BUTTON'].includes(heading), heading);

/* --------------------------------------------------- strip di atas navbar */

const above = await page.evaluate(() => {
    const strip = document.querySelector('[data-ticker]').getBoundingClientRect();
    const header = document.querySelector('header').getBoundingClientRect();
    return { strip: Math.round(strip.top), header: Math.round(header.top), h: Math.round(strip.height) };
});
check('strip berada di atas navbar', above.strip < above.header, `strip ${above.strip}px, navbar ${above.header}px`);
check('tinggi strip wajar', above.h > 30 && above.h < 70, `${above.h}px`);

/* -------------------------------------------------- modal benar di tengah */

const placement = await page.evaluate(() => {
    const d = document.querySelector('[data-announcement-modal]');
    const r = d.getBoundingClientRect();
    const badge = document.querySelector('[data-ticker] span.rounded-full')
        ?? document.querySelector('[data-announcement-panel]:not([hidden]) .rounded-full');
    const cta = document.querySelector('header a.rounded-full') ?? document.querySelector('[data-ticker-toggle]');
    const radius = (el) => el ? parseFloat(getComputedStyle(el).borderTopLeftRadius) : 0;
    return {
        dx: Math.abs((r.left + r.width / 2) - innerWidth / 2),
        dy: Math.abs((r.top + r.height / 2) - innerHeight / 2),
        // Preflight zeroes every margin, which is what un-centres a dialog.
        margin: getComputedStyle(d).marginLeft,
        badgeRadius: radius(badge),
        badgeHeight: badge ? Math.round(badge.getBoundingClientRect().height) : 0,
    };
});
check('modal terpusat mendatar', placement.dx < 1, `meleset ${placement.dx.toFixed(1)}px`);
check('modal terpusat menegak', placement.dy < 1, `meleset ${placement.dy.toFixed(1)}px`);
check('label pengumuman membulat penuh', placement.badgeRadius >= placement.badgeHeight / 2, `radius ${placement.badgeRadius}px pada tinggi ${placement.badgeHeight}px`);

/* ------------------------------------------- tutup, strip harus tetap ada */

await page.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 300));
s = await state();
check('Escape menutup modal', !s.open);
check('strip tetap ada setelah modal ditutup', (await page.$('[data-ticker]')) !== null);

/* ------------------------------------------------------ gerak bawah ke atas */

const first = (await state()).visible;
await new Promise((r) => setTimeout(r, 5600));
s = await state();
check('strip berganti sendiri', s.visible !== first, `baris ${first} → ${s.visible}`);
check('bergeser ke atas, bukan menyamping', s.shift < 0, `translateY(${s.shift}px)`);

/* ------------------------------------------------------------------ jeda */

await page.click('[data-ticker-toggle]');
const paused = await page.$eval('[data-ticker-toggle]', (b) => b.getAttribute('aria-pressed'));
const atPause = (await state()).visible;
await new Promise((r) => setTimeout(r, 5600));
check('tombol jeda benar-benar menghentikan', paused === 'true' && (await state()).visible === atPause, `aria-pressed=${paused}`);

await page.click('[data-ticker-toggle]');
check('menekan lagi melanjutkan', (await page.$eval('[data-ticker-toggle]', (b) => b.getAttribute('aria-pressed'))) === 'false');

/* -------------------------------------------- buka lagi dari strip + fokus */

await page.click('[data-ticker-item]:not([aria-hidden]) [data-announcement-open]');
await new Promise((r) => setTimeout(r, 300));
check('strip dapat membuka modal kembali', (await state()).open);

await page.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 300));
const returned = await page.evaluate(() => document.activeElement?.dataset?.announcementOpen !== undefined);
check('fokus kembali ke baris yang membukanya', returned);

/* -------------------------------------------- mengikuti tombol tindakan */

// Reset this browser's memory so the modal opens again for this leg.
await page.evaluate(() => localStorage.clear());
await page.goto(BASE, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 400));
check('modal terbuka lagi setelah ingatan dibersihkan', (await state()).open);

const headingHasRing = await page.evaluate(() => {
    const h = document.querySelector('[data-announcement-panel]:not([hidden]) [data-announcement-heading]');
    const cs = getComputedStyle(h);
    return { ring: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0, focusable: h.hasAttribute('tabindex') };
});
check('judul modal tanpa garis fokus', !headingHasRing.ring && !headingHasRing.focusable);
check('fokus tetap mendarat pada kendali', await page.evaluate(() => document.activeElement?.tagName) === 'BUTTON');

await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('[data-announcement-panel]:not([hidden]) [data-announcement-action]'),
]);
check('tombol tindakan berpindah halaman', !page.url().endsWith('/'), page.url().replace(BASE, ''));

await new Promise((r) => setTimeout(r, 400));
const arrived = await state();
check('modal tidak ikut terbawa ke halaman tujuan', !arrived.open);
check('strip tetap ada di halaman tujuan', arrived.items > 0, `${arrived.items} baris`);

await page.goto(BASE, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 400));
check('mengikuti tombol dihitung sebagai sudah dibaca', !(await state()).open);

/* ------------------------------------------------- kunjungan kedua: diam */

await page.reload({ waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 500));
s = await state();
check('modal tidak muncul lagi pada kunjungan berikutnya', !s.open);
check('pengumuman tetap terbaca di strip', s.items === 3);

/* -------------------------------------------------------------- navbar */

const nav = await page.evaluate(() => {
    const header = document.querySelector('header');
    const logo = header.querySelector('a');
    const form = header.querySelector('form[role="search"]');
    const menu = header.querySelector('nav[aria-label="Navigasi utama"]');
    const box = (el) => el?.getBoundingClientRect();
    return {
        order: [box(logo)?.left, box(form)?.left, box(menu)?.left].map((n) => Math.round(n)),
        pill: Math.round(getComputedStyle(form).borderRadius.replace('px', '')),
        button: Math.round(box(form.querySelector('button[type="submit"]')).width),
        filter: !!form.querySelector('select[name="jenis"]'),
        chevrons: header.querySelectorAll('nav button[aria-expanded]').length,
        logo: Math.round(box(header.querySelector('a img, a span'))?.height ?? 0),
    };
});
check('logo cukup besar di dalam navbar', nav.logo >= 40 && nav.logo <= 56, `${nav.logo}px`);
check('urutan navbar: logo, pencarian, menu', nav.order[0] < nav.order[1] && nav.order[1] < nav.order[2], nav.order.join(' < '));
check('kolom pencarian berbentuk pil', nav.pill >= 20, `radius ${nav.pill}px`);
check('tombol bundar di ujung kolom', nav.button >= 28 && nav.button <= 40, `${nav.button}px`);
check('filter jenis menyatu di dalam kolom', nav.filter);
check('menu bertingkat punya penanda buka', nav.chevrons > 0, `${nav.chevrons} dropdown`);

/* ----------------------------------------------------- gerak dikurangi */

const reduced = await browser.newPage();
await reduced.setViewport({ width: 1440, height: 900 });
await reduced.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
await reduced.goto(BASE, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 5600));
const still = await reduced.evaluate(() => {
    const items = [...document.querySelectorAll('[data-ticker-item]')];
    return {
        moving: !!document.querySelector('[data-ticker-track]')?.style.transform,
        allReadable: items.every((i) => !i.hasAttribute('aria-hidden')),
    };
});
check('reduced-motion: strip tidak bergerak sendiri', !still.moving);
check('reduced-motion: semua baris tetap terbaca', still.allReadable);

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
