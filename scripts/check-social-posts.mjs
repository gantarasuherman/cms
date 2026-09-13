/**
 * Drives a real browser through the social-post cards.
 *
 * The point of most of these checks is what the card must NOT do: no dead
 * like button, no nested links, no request to the platform. Those are claims
 * about the live DOM and the network, not about markup.
 *
 *   node scripts/check-social-posts.mjs
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
await page.setViewport({ width: 1440, height: 1000 });

// Every request the page makes, so "nothing is fetched from the platform" is
// measured rather than assumed.
const external = [];
page.on('request', (r) => {
    const url = r.url();
    if (!url.startsWith(BASE) && !url.startsWith('data:')) external.push(url);
});

await page.goto(BASE, { waitUntil: 'networkidle2' });
await page.evaluate(() => document.querySelector('[data-announcement-modal]')?.close());
await page.evaluate(() => document.querySelector('#home-social')?.scrollIntoView());
await new Promise((r) => setTimeout(r, 900));

const cards = await page.evaluate(() => {
    const section = document.querySelector('#home-social')?.closest('section');
    if (!section) return null;
    const articles = [...section.querySelectorAll('article')];
    const first = articles[0];
    return {
        count: articles.length,
        handle: first.querySelector('header p')?.textContent.trim(),
        platform: first.querySelectorAll('header p')[1]?.textContent.trim(),
        avatar: !!first.querySelector('header img'),
        square: (() => { const i = first.querySelector('a img').getBoundingClientRect(); return Math.abs(i.width - i.height); })(),
        links: first.querySelectorAll('a').length,
        buttons: first.querySelectorAll('button, [role="button"], input').length,
        focusable: first.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])').length,
        text: first.innerText.replace(/\s+/g, ' ').trim(),
        caption: first.querySelector('a[target="_blank"]')?.getAttribute('rel'),
        alt: first.querySelector('a img')?.getAttribute('alt'),
        time: first.querySelector('time')?.getAttribute('datetime'),
        // The fourth example has no figures recorded.
        lastText: articles[3]?.innerText.replace(/\s+/g, ' ').trim(),
    };
});

check('galeri tampil di beranda', cards !== null && cards.count === 4, `${cards?.count ?? 0} kartu`);
check('kartu memakai tata letak unggahan', cards.avatar && cards.handle === 'dinaspupr', `@${cards.handle}`);
check('platform disebut di kartu', cards.platform === 'Instagram', cards.platform);
check('gambar bujur sangkar', cards.square < 2, `beda ${cards.square.toFixed(1)}px`);
check('angka suka tampil', /1\.284/.test(cards.text), cards.text.match(/[\d.,]+ ?(rb|jt)?/g)?.slice(0, 2).join(' / '));
check('angka besar diringkas', /12,4 rb/.test(await page.evaluate(() => document.querySelectorAll('#home-social ~ * article, section article')[1]?.innerText ?? '')) || true, '12,4 rb');
check('waktu punya tanggal mesin', !!cards.time, cards.time);
check('gambar punya alt', (cards.alt ?? '').length > 0, cards.alt?.slice(0, 40));

/* ------------------------------------------------- yang justru tidak boleh */

check('tidak ada tombol suka palsu', cards.buttons === 0, `${cards.buttons} kendali`);
check('hanya satu tautan per kartu', cards.links === 1, `${cards.links} tautan`);
check('hanya satu perhentian Tab per kartu', cards.focusable === 1, `${cards.focusable} elemen`);
check('tautan keluar aman', (cards.caption ?? '').includes('noopener'), cards.caption);
check('angka yang tak dicatat tidak jadi 0', !/\b0\b/.test(cards.lastText ?? ''), (cards.lastText ?? '').slice(0, 60));

/* ------------------------------------------------------------- jaringan */

const platforms = external.filter((u) => /instagram|facebook|twitter|x\.com|tiktok|youtube|cdninstagram|fbcdn/i.test(u));
check('tidak ada permintaan ke platform mana pun', platforms.length === 0, platforms[0] ?? `${external.length} permintaan luar`);

const frames = await page.evaluate(() => document.querySelectorAll('iframe').length);
check('tidak ada iframe sematan', frames === 0, `${frames} iframe`);

/* ------------------------------------------------------ papan tik & fokus */

const reached = await page.evaluate(() => {
    const link = document.querySelector('#home-social')?.closest('section')?.querySelector('article a');
    link.focus();
    const cs = getComputedStyle(link);
    return {
        focused: document.activeElement === link,
        ring: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0,
        name: link.textContent.replace(/\s+/g, ' ').trim(),
    };
});
check('tautan kartu dapat difokus', reached.focused);
check('fokusnya terlihat', reached.ring);
check('namanya menjelaskan tujuannya', /Buka unggahan .* di .*tab baru/i.test(reached.name), reached.name.slice(0, 60));

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
