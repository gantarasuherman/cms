/**
 * Drives a real browser against the hero slider.
 *
 * Autoplay, pausing, focus handling and reduced motion are all timing and
 * layout behaviour — none of it is visible to a markup grep.
 *
 *   node scripts/check-hero-slider.mjs
 */
import puppeteer from 'puppeteer-core';

const OUT = process.env.SHOT_DIR ?? '/tmp';
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
await page.goto(BASE, { waitUntil: 'networkidle2' });

// A first-time visitor meets the announcement modal, which covers the hero and
// swallows every pointer event aimed at it. Dismiss it the way they would.
await page.evaluate(() => document.querySelector('[data-announcement-modal]')?.close());
await new Promise((r) => setTimeout(r, 200));

// Somewhere on the page that is definitely not the hero, computed rather than
// assumed: the strip above the header moves everything down.
const away = await page.evaluate(() => {
    const hero = document.querySelector('[data-hero]').getBoundingClientRect();
    return { x: Math.round(hero.left + hero.width / 2), y: Math.round(hero.bottom + 40) };
});

// aria-hidden flips synchronously in show(); opacity spends 700ms in between,
// so reading opacity right after a click reports the slide that is fading out.
const SETTLE = 900;

const state = () => page.evaluate(() => {
    const hero = document.querySelector('[data-hero]');
    const slides = [...hero.querySelectorAll('[data-hero-slide]')];
    const active = slides.findIndex((s) => !s.hasAttribute('aria-hidden'));
    return {
        count: slides.length,
        active,
        counter: hero.querySelector('[data-hero-current]')?.textContent.trim(),
        height: Math.round(hero.getBoundingClientRect().height),
        hiddenMarked: slides.filter((s) => s.hasAttribute('aria-hidden')).length,
        tabbableOffscreen: slides
            .filter((_, i) => i !== active)
            .flatMap((s) => [...s.querySelectorAll('a, button')])
            .filter((el) => el.getAttribute('tabindex') !== '-1').length,
    };
});

/* ---------------------------------------------------------------- layout */

const first = await state();
check('empat slide dimuat', first.count === 4, `${first.count} slide`);
check('tinggi hero dalam rentang desktop', first.height >= 500 && first.height <= 650, `${first.height}px`);
check('hanya slide aktif yang terlihat', first.hiddenMarked === first.count - 1);
check('tautan slide tersembunyi keluar dari urutan Tab', first.tabbableOffscreen === 0);
check('penghitung menunjukkan 01', first.counter === '01', first.counter);

const imgs = await page.evaluate(() => [...document.querySelectorAll('[data-hero-image]')]
    .map((i) => ({ priority: i.getAttribute('fetchpriority'), loading: i.getAttribute('loading'), alt: i.alt })));
check('slide pertama diprioritaskan', imgs[0].priority === 'high', `fetchpriority=${imgs[0].priority}`);
check('slide lain dimuat malas', imgs.slice(1).every((i) => i.loading === 'lazy'));
check('setiap gambar punya alt', imgs.every((i) => typeof i.alt === 'string' && i.alt.length > 0));

/* ------------------------------------------------------------- autoplay */

await page.mouse.move(away.x, away.y);
await new Promise((r) => setTimeout(r, 7000)); // one interval plus slack
const auto = await state();
check('berganti sendiri', auto.active === 1, `slide ${auto.active + 1}, penghitung ${auto.counter}`);

/* ----------------------------------------------------- pause saat hover */

await page.hover('[data-hero]');
const beforeHover = (await state()).active;
await new Promise((r) => setTimeout(r, 7000));
const afterHover = (await state()).active;
check('berhenti saat kursor di atasnya', beforeHover === afterHover, `tetap di slide ${afterHover + 1}`);

await page.mouse.move(away.x, away.y);

/* ------------------------------------------------------------- kendali */

await page.click('[data-hero-next]');
await new Promise((r) => setTimeout(r, SETTLE));
const afterNext = await state();
check('tombol berikutnya bekerja', afterNext.active === (afterHover + 1) % 4, `slide ${afterNext.active + 1}`);

await page.click('[data-hero-prev]');
await new Promise((r) => setTimeout(r, SETTLE));
check('tombol sebelumnya bekerja', (await state()).active === afterHover);

await page.click('[data-hero-dot="3"]');
await new Promise((r) => setTimeout(r, SETTLE));
const afterDot = await state();
check('indikator melompat ke slide', afterDot.active === 3 && afterDot.counter === '04', afterDot.counter);

/* ------------------------------------------------------------- keyboard */

await page.focus('[data-hero-next]');
await page.keyboard.press('ArrowLeft');
await new Promise((r) => setTimeout(r, SETTLE));
check('panah kiri memundurkan', (await state()).active === 2);

/* --------------------------------------------------- kendali jeda nyata */

await page.mouse.move(away.x, away.y);
await page.click('[data-hero-play]');
const pausedState = await page.evaluate(() => document.querySelector('[data-hero-play]').getAttribute('aria-pressed'));
const beforePause = (await state()).active;
await new Promise((r) => setTimeout(r, 7000));
check('tombol jeda benar-benar menghentikan', (await state()).active === beforePause, `aria-pressed=${pausedState}`);

await browser.close();

/* ------------------------------------------------- prefers-reduced-motion */

const reduced = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const rpage = await reduced.newPage();
await rpage.setViewport({ width: 1440, height: 900 });
await rpage.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
await rpage.goto(BASE, { waitUntil: 'networkidle2' });

const before = await rpage.evaluate(() => [...document.querySelectorAll('[data-hero-slide]')]
    .findIndex((s) => !s.hasAttribute('aria-hidden')));
await new Promise((r) => setTimeout(r, 8000));
const after = await rpage.evaluate(() => [...document.querySelectorAll('[data-hero-slide]')]
    .findIndex((s) => !s.hasAttribute('aria-hidden')));

check('reduced-motion: tidak berputar sendiri', before === after, `slide ${after + 1}`);

await rpage.screenshot({ path: `${OUT}/hero-reduced.png` });
await reduced.close();

const failed = report.filter((ok) => !ok).length;
console.log(`\n${report.length - failed}/${report.length} pemeriksaan lolos`);
process.exit(failed === 0 ? 0 : 1);
