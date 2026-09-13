import puppeteer from 'puppeteer-core';
const OUT = process.env.SHOT_DIR ?? '/tmp';
const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: 'new', args: ['--no-sandbox'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 1000 });
await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
await page.goto(process.env.URL ?? 'http://localhost:8010/', { waitUntil: 'networkidle2' });
await new Promise(r => setTimeout(r, 600));

const info = await page.evaluate(() => {
    const b = getComputedStyle(document.body);
    const h1 = document.querySelector('h1');
    const h2 = document.querySelector('h2');
    const btn = document.querySelector('.sarab-btn-primary');
    const read = (el) => el ? (({ fontFamily, fontSize, fontWeight, lineHeight }) =>
        ({ fontFamily: fontFamily.split(',')[0], fontSize, fontWeight, lineHeight }))(getComputedStyle(el)) : null;
    return {
        body: read(document.body),
        h1: read(h1), h2: read(h2), button: read(btn),
        sections: document.querySelectorAll('.sarab-section, .sarab').length,
        loadedInter: document.fonts && [...document.fonts].some(f => f.family === 'Inter' && f.status === 'loaded'),
    };
});
console.log(JSON.stringify(info, null, 2));
await page.screenshot({ path: `${OUT}/home.png` });
await page.screenshot({ path: `${OUT}/home-full.png`, fullPage: true });
await browser.close();
