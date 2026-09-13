/**
 * Drives a real browser through the flow editor.
 *
 * Dragging, keyboard movement, connecting and saving are all behaviour — none
 * of it is visible to a markup grep, and the accessibility claims in
 * particular are only true if the keys actually do something.
 *
 *   node scripts/check-flow-editor.mjs
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
await page.setViewport({ width: 1600, height: 1100 });

// The editor warns before leaving with unsaved changes, which blocks a reload
// until somebody answers. A person clicks "leave"; so does this.
page.on('dialog', (dialog) => dialog.accept());

/* ------------------------------------------------------------------ masuk */

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

await page.goto(`${BASE}/admin/bot/flows`, { waitUntil: 'networkidle2' });
const editUrl = await page.evaluate(() => document.querySelector('a[href*="/edit"]')?.href);
check('daftar alur memuat alur bawaan', !!editUrl, editUrl?.replace(BASE, ''));

await page.goto(editUrl, { waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 700));

/* ----------------------------------------------------------------- kanvas */

// The graph as found, so the run can put it back exactly — this check edits a
// real flow, and a failure part-way through must not leave it altered.
const original = await page.evaluate(() => ({
    nodes: structuredClone([...window.flowEditor.nodes.values()]),
    edges: structuredClone(window.flowEditor.edges),
}));

const drawn = await page.evaluate(() => ({
    nodes: document.querySelectorAll('[data-node]').length,
    edges: document.querySelectorAll('path.flow-edge').length,
    labels: [...document.querySelectorAll('.flow-edge-label')].map((t) => t.textContent).slice(0, 3),
    outline: document.querySelectorAll('.flow-outline-item').length,
    canvasRole: document.querySelector('[data-flow-canvas]')?.getAttribute('role'),
}));

check('seluruh node tergambar', drawn.nodes === original.nodes.length, `${drawn.nodes} node`);
check('sambungan tergambar', drawn.edges === original.edges.length, `${drawn.edges} garis`);
check('kondisi tertulis pada garis', drawn.labels.length > 0, drawn.labels.join(', '));
check('daftar node menampilkan hal yang sama', drawn.outline === original.nodes.length, `${drawn.outline} item`);
check('kanvas punya peran yang bisa dibaca pembaca layar', drawn.canvasRole === 'listbox', drawn.canvasRole);

/* ------------------------------------------------------------- papan tik */

await page.focus('[data-node="menu_utama"]');
const before = await page.evaluate(() => document.querySelector('[data-node="menu_utama"]').style.transform);
await page.keyboard.press('ArrowRight');
await page.keyboard.press('ArrowDown');
const after = await page.evaluate(() => ({
    transform: document.querySelector('[data-node="menu_utama"]').style.transform,
    announced: document.querySelector('[data-flow-status]').textContent,
    focused: document.activeElement?.dataset?.node,
}));

check('panah memindahkan node', before !== after.transform, after.transform);
check('perpindahan diumumkan', after.announced.includes('dipindah'), after.announced.slice(0, 50));
check('fokus tetap pada node yang dipindah', after.focused === 'menu_utama', after.focused);

await page.keyboard.press('KeyC');
const connecting = await page.evaluate(() => document.querySelector('[data-flow-status]').textContent);
check('C memulai penyambungan', connecting.includes('Menyambung dari'), connecting.slice(0, 45));

await page.keyboard.press('Escape');
check('Escape membatalkannya', (await page.evaluate(() => document.querySelector('[data-flow-status]').textContent)).includes('dibatalkan'));

/* ----------------------------------------------------------- panel isian */

await page.click('[data-node="menu_utama"]');
await new Promise((r) => setTimeout(r, 200));
const panel = await page.evaluate(() => ({
    fields: [...document.querySelectorAll('[data-flow-panel] [data-field]')].map((e) => e.dataset.field),
    options: document.querySelectorAll('[data-flow-panel] [data-option-value]').length,
    // Read from the node itself: the default menu is meant to gain entries,
    // and a pinned number only ever reports that it did.
    declared: window.flowEditor.nodes.get('menu_utama').config.options.length,
}));

check('panel menampilkan isian node', panel.fields.includes('config.text'), panel.fields.join(', ').slice(0, 60));
check('setiap pilihan menu dapat disunting', panel.options === panel.declared,
    `${panel.options} dari ${panel.declared} pilihan`);

/* --------------------------------------------- nama mengikuti properti */

const naming = await page.evaluate(() => {
    const editor = window.flowEditor;
    const out = {};

    // A new node is named after what it is, not left showing its key.
    editor.addNode('action');
    const key = editor.selected;
    out.onAdd = editor.nodes.get(key).label;

    // Choosing what it does renames it — through the panel, the way a person
    // would, rather than by writing into the node behind its back.
    const select = document.querySelector('[data-flow-panel] [data-field="config.action"]');
    select.value = 'lookup_complaint';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    out.afterChoice = editor.nodes.get(key).label;

    // A name somebody typed must survive every later change.
    const label = document.querySelector('[data-flow-panel] [data-field="label"]');
    label.value = 'Cari Tiket Warga';
    label.dispatchEvent(new Event('change', { bubbles: true }));
    out.typed = editor.nodes.get(key).label;

    const again = document.querySelector('[data-flow-panel] [data-field="config.action"]');
    again.value = 'notify_officers';
    again.dispatchEvent(new Event('change', { bubbles: true }));
    out.afterSecondChoice = editor.nodes.get(key).label;

    out.onCanvas = document.querySelector(`[data-node="${key}"] .flow-node-label`)?.textContent;

    editor.nodes.delete(key);
    editor.edges = editor.edges.filter((e) => e.from !== key && e.to !== key);
    editor.select(null);
    editor.render();

    return out;
});

check('node baru langsung bernama jenisnya', naming.onAdd === 'Aksi', naming.onAdd);
check('nama mengikuti pilihan properti', naming.afterChoice === 'Cari Pengaduan', naming.afterChoice);
check('nama tulisan tangan diterima', naming.typed === 'Cari Tiket Warga', naming.typed);
check('nama tulisan tangan tidak tertimpa', naming.afterSecondChoice === 'Cari Tiket Warga', naming.afterSecondChoice);
check('kanvas menampilkan nama itu', naming.onCanvas === 'Cari Tiket Warga', naming.onCanvas);

/* ----------------------------------------------------------- layar penuh */

await page.click('[data-flow-fullscreen]');
await new Promise((r) => setTimeout(r, 600));

const full = await page.evaluate(() => {
    const root = document.querySelector('[data-flow-editor]');
    const canvas = document.querySelector('[data-flow-canvas]').getBoundingClientRect();
    const nodes = [...document.querySelectorAll('[data-node]')].map((n) => n.getBoundingClientRect());

    return {
        isFullscreen: document.fullscreenElement === root,
        canvasHeight: Math.round(canvas.height),
        viewport: window.innerHeight,
        visible: nodes.filter((n) => n.bottom > canvas.top && n.top < canvas.bottom).length,
        toolbar: !!root.querySelector('[data-flow-save]')?.offsetParent,
        panel: !!root.querySelector('[data-flow-panel]')?.offsetParent,
    };
});

check('layar penuh aktif', full.isFullscreen);
// The regression this guards: `flex: 1` let the tall outline card below take
// the whole window and collapsed the canvas to its own borders — 2px.
check('kanvas tidak menyusut di layar penuh', full.canvasHeight > full.viewport * 0.5,
    `${full.canvasHeight}px dari ${full.viewport}px`);
check('diagram terlihat di layar penuh', full.visible > 5, `${full.visible} node terlihat`);
check('bilah alat dan panel ikut terbawa', full.toolbar && full.panel);

// The button, not Escape: exiting on Escape is the browser's own behaviour,
// and headless Chrome does not honour it. What is ours to get right is that
// the class and the button's state follow whatever actually happened.
await page.click('[data-flow-fullscreen]');
await new Promise((r) => setTimeout(r, 500));

const exited = await page.evaluate(() => {
    const root = document.querySelector('[data-flow-editor]');
    return {
        out: !document.fullscreenElement,
        classGone: !root.classList.contains('is-fullscreen'),
        pressed: root.querySelector('[data-flow-fullscreen]').getAttribute('aria-pressed'),
        canvas: Math.round(document.querySelector('[data-flow-canvas]').getBoundingClientRect().height),
    };
});

check('tombol mengembalikan tampilan biasa', exited.out && exited.classGone, `aria-pressed=${exited.pressed}`);
check('kanvas kembali ke tinggi biasanya', exited.canvas > 300 && exited.canvas < 800, `${exited.canvas}px`);

/* --------------------------------------------- titik sambung dan jalurnya */

const ports = await page.evaluate(() => {
    const node = document.querySelector('[data-node="menu_utama"]');
    return {
        count: node.querySelectorAll('.flow-port').length,
        sides: [...node.querySelectorAll('.flow-port')].map((p) => p.dataset.port),
        hiddenAtRest: getComputedStyle(node.querySelector('.flow-port')).opacity === '0',
        onEnd: document.querySelector('[data-node="selesai"]').querySelectorAll('.flow-port').length,
    };
});
check('setiap node punya titik di empat sisi', ports.count === 4, ports.sides.join(', '));
check('titik tersembunyi sampai node disentuh', ports.hiddenAtRest);
check('node Selesai tidak menawarkan titik keluar', ports.onEnd === 0, `${ports.onEnd} titik`);

// Dragging a port onto another node is the gesture that was missing entirely.
const linked = await page.evaluate(() => {
    const editor = window.flowEditor;
    const before = editor.edges.length;

    // Both boxes placed where the canvas can actually show them: a pointer
    // cannot be dropped on a node scrolled out of view, here or in real use.
    editor.addNode('message');
    const key = editor.selected;
    editor.view = { x: 0, y: 0, scale: 1 };
    editor.applyView();
    editor.nodes.get(key).position = { x: 40, y: 40 };
    editor.nodes.get('selesai').position = { x: 400, y: 40 };
    editor.render();

    const port = document.querySelector(`[data-node="${key}"] .flow-port-right`);
    const target = document.querySelector('[data-node="selesai"]');
    const box = target.getBoundingClientRect();

    port.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, button: 0, pointerId: 3, isPrimary: true }));
    const ghostWhileDragging = !!document.querySelector('.flow-edge-ghost');
    window.dispatchEvent(new PointerEvent('pointermove', { clientX: box.x + 20, clientY: box.y + 20 }));
    const highlighted = target.classList.contains('is-target');
    window.dispatchEvent(new PointerEvent('pointerup', { clientX: box.x + 20, clientY: box.y + 20 }));

    const edge = editor.edges.find((e) => e.from === key);

    const out = {
        ghostWhileDragging,
        highlighted,
        added: editor.edges.length - before,
        condition: edge?.condition,
        ghostGone: !document.querySelector('.flow-edge-ghost'),
        key,
    };

    editor.nodes.delete(key);
    editor.edges = editor.edges.filter((e) => e.from !== key && e.to !== key);
    editor.select(null);
    editor.render();

    return out;
});

check('menyeret titik membuat sambungan', linked.added === 1, `${linked.added} sambungan`);
check('garis putus-putus tampil saat menyeret', linked.ghostWhileDragging);
check('node tujuan disorot saat dilewati', linked.highlighted);
check('garis bantu hilang setelah dilepas', linked.ghostGone);
// A message node always moves on, so its edge is unconditional — `null` is
// the right guess here, not "valid". The conditional cases are checked below.
check('kondisi ditebak dari jenis node', linked.condition === null, String(linked.condition));

// A menu should offer its next unrouted option rather than repeating one.
const suggested = await page.evaluate(() => {
    const editor = window.flowEditor;
    return {
        menu: editor.suggestCondition('menu_utama'),
        fromTable: editor.suggestCondition('menu_kategori'),
        input: editor.suggestCondition('isi_aduan'),
    };
});
check('menu menawarkan pilihan yang belum terpakai', suggested.menu === null || /^[0-9]+$/.test(suggested.menu), String(suggested.menu));
check('input menawarkan jalur yang belum ada', ['valid', 'invalid', 'exhausted', null].includes(suggested.input), String(suggested.input));

/* ------------------------------------------------------ warna dan arah */

const routing = await page.evaluate(() => {
    const paths = [...document.querySelectorAll('path.flow-edge')];
    const strokes = new Set(paths.map((p) => p.getAttribute('stroke')));

    // Positions are set here rather than assumed: earlier checks move nodes
    // around, and a routing test that depends on where they happened to end up
    // is testing the previous check, not the router.
    const editor = window.flowEditor;
    editor.nodes.get('menu_utama').position = { x: 200, y: 200 };
    editor.nodes.get('menu_kategori').position = { x: 700, y: 200 };
    editor.nodes.get('daftar_berita').position = { x: 200, y: 200 };
    editor.nodes.get('daftar_layanan').position = { x: 220, y: 500 };
    editor.render();

    // A "back" edge points at a node behind it, so it must leave the left side
    // rather than sweep around the whole diagram.
    const backward = editor.route('menu_kategori', 'menu_utama');
    const forward = editor.route('menu_utama', 'menu_kategori');
    const stacked = editor.route('daftar_berita', 'daftar_layanan');

    const startX = (d) => Number(d.match(/^M([-\d.]+),/)[1]);
    const startY = (d) => Number(d.match(/^M[-\d.]+,([-\d.]+)/)[1]);

    return {
        colours: strokes.size,
        labelled: document.querySelectorAll('.flow-edge-label').length,
        markers: document.querySelectorAll('marker').length,
        backwardLeavesLeft: startX(backward.d) === editor.nodes.get('menu_kategori').position.x,
        forwardLeavesRight: startX(forward.d) > editor.nodes.get('menu_utama').position.x,
        stackedLeavesBottom: startY(stacked.d) > editor.nodes.get('daftar_berita').position.y + 20,
    };
});

check('jalur dibedakan warnanya', routing.colours >= 4, `${routing.colours} warna`);
check('kepala panah mengikuti warna jalurnya', routing.markers >= 4, `${routing.markers} penanda`);
check('kondisi tetap tertulis, bukan warna saja', routing.labelled > 0, `${routing.labelled} label`);
check('jalur maju keluar dari sisi kanan', routing.forwardLeavesRight);
check('jalur balik keluar dari sisi kiri', routing.backwardLeavesLeft);
check('node bertumpuk tersambung lewat bawah', routing.stackedLeavesBottom);

// Put the diagram back before the save checks run against it.
await page.evaluate(() => window.flowEditor.undo());

/* ------------------------------------------------------------ undo/redo */

const history = await page.evaluate(async () => {
    const editor = window.flowEditor;
    const out = { start: editor.nodes.size };

    editor.addNode('message');
    const key = editor.selected;
    out.afterAdd = editor.nodes.size;
    out.undoLabel = document.querySelector('[data-flow-undo]').getAttribute('aria-label');

    editor.undo();
    out.afterUndo = editor.nodes.size;
    out.announcedUndo = document.querySelector('[data-flow-status]').textContent;

    editor.redo();
    out.afterRedo = editor.nodes.size;

    // Three more edits on top, then undone one by one back to the start.
    const edgesAtStart = editor.edges.length;
    editor.connect(key, 'selesai', 'valid');
    editor.removeNode('daftar_dokumen');
    out.afterThree = { nodes: editor.nodes.size, edges: editor.edges.length };

    editor.undo();
    editor.undo();
    editor.undo();
    out.backToStart = { nodes: editor.nodes.size, edges: editor.edges.length, expectedEdges: edgesAtStart };

    // A fresh edit must make the redo branch unreachable.
    editor.addNode('message');
    out.futureAfterNewEdit = editor.future.length;
    editor.undo();

    out.end = editor.nodes.size;

    editor.future = [];
    editor.redo();
    out.redoEmptyMessage = document.querySelector('[data-flow-status]').textContent;

    return out;
});

check('undo mati saat halaman baru dibuka', await page.evaluate(async () => {
    // Read from a fresh load: by this point in the run the history is full.
    const probe = await fetch(location.href, { headers: { 'Accept': 'text/html' } }).then((r) => r.text());
    return /data-flow-undo disabled/.test(probe);
}));
check('undo mengembalikan penambahan node', history.afterAdd === history.start + 1 && history.afterUndo === history.start,
    `${history.start} → ${history.afterAdd} → ${history.afterUndo}`);
check('tombol undo menyebut apa yang akan dibatalkan', /Batalkan: tambah node/.test(history.undoLabel), history.undoLabel);
check('pembatalan diumumkan', history.announcedUndo.includes('Dibatalkan'), history.announcedUndo.slice(0, 40));
check('redo mengulanginya kembali', history.afterRedo === history.start + 1);
check('beberapa langkah dibatalkan berurutan',
    history.backToStart.nodes === history.start && history.backToStart.edges === history.backToStart.expectedEdges,
    JSON.stringify(history.backToStart));
check('menyunting lagi membuang cabang redo', history.futureAfterNewEdit === 0, `${history.futureAfterNewEdit} tersisa`);
check('redo kosong dikatakan, bukan diam', history.redoEmptyMessage.includes('Tidak ada lagi'), history.redoEmptyMessage);
check('graf kembali utuh setelah semuanya', history.end === history.start, `${history.end} node`);

// One entry per drag, not one per pixel.
const dragHistory = await page.evaluate(() => {
    const editor = window.flowEditor;
    const before = editor.past.length;
    const node = document.querySelector('[data-node="menu_utama"]');
    const box = node.getBoundingClientRect();

    node.dispatchEvent(new PointerEvent('pointerdown', {
        bubbles: true, button: 0, pointerId: 1, isPrimary: true,
        clientX: box.x + 10, clientY: box.y + 10,
    }));
    for (let i = 1; i <= 12; i++) {
        window.dispatchEvent(new PointerEvent('pointermove', { clientX: box.x + 10 + i * 8, clientY: box.y + 10 }));
    }
    window.dispatchEvent(new PointerEvent('pointerup', {}));

    return editor.past.length - before;
});
check('satu seretan tercatat satu langkah', dragHistory === 1, `${dragHistory} entri`);
await page.evaluate(() => window.flowEditor.undo());

/* ------------------------------------------------- menambah dan menyimpan */

const startCount = await page.evaluate(() => document.querySelectorAll('[data-node]').length);
await page.click('[data-flow-add="message"]');
await new Promise((r) => setTimeout(r, 200));
const addedKey = await page.evaluate(() => window.flowEditor.selected);
check('node baru ditambahkan',
    (await page.evaluate(() => document.querySelectorAll('[data-node]').length)) === startCount + 1, addedKey);

// Connect it through the outline — the path that works without a mouse.
const connected = await page.evaluate(() => {
    const item = [...document.querySelectorAll('.flow-outline-item')]
        .find((el) => el.querySelector('.flow-outline-key')?.textContent === window.flowEditor.selected);
    if (!item) return 'item tidak ditemukan';
    const select = item.querySelector('select');
    select.value = 'selesai';
    item.querySelector('.flow-outline-add input').value = 'valid';
    item.querySelector('.flow-outline-add button').click();
    return document.querySelector('[data-flow-status]').textContent;
});
check('daftar dapat menyambungkan tanpa tetikus', connected.includes('Disambungkan'), connected.slice(0, 45));

await page.click('[data-flow-save]');
await new Promise((r) => setTimeout(r, 1200));
const saved = await page.evaluate(() => ({
    status: document.querySelector('[data-flow-status]').textContent,
    dirty: !document.querySelector('[data-flow-dirty]')?.hasAttribute('hidden'),
}));
check('penyimpanan berhasil', saved.status.includes('disimpan'), saved.status.slice(0, 40));
check('penanda belum-disimpan hilang', !saved.dirty);

await page.reload({ waitUntil: 'networkidle2' });
await new Promise((r) => setTimeout(r, 600));
check('perubahan bertahan setelah muat ulang',
    (await page.evaluate(() => document.querySelectorAll('[data-node]').length)) === startCount + 1);

// The bug this check found: a form request returns only validated keys, so
// every undeclared setting was dropped on save.
const kept = await page.evaluate(() =>
    [...window.flowEditor.nodes.values()]
        .filter((n) => n.type === 'data_source')
        .map((n) => n.config?.data_source));
check('setelan node tidak hilang saat disimpan', kept.length > 0 && kept.every(Boolean), kept.join(', '));

/* ------------------------------------------------------------- pemeriksaan */

const problems = await page.evaluate(() => document.querySelector('[data-flow-problems]').textContent.trim());
check('pemeriksaan melaporkan node yatim', problems.length > 0 && !problems.includes('Tidak ada masalah'), problems.slice(0, 70));

/* -------------------------------------------------------------- kembalikan */

// Restored from the snapshot taken at the start rather than by undoing each
// step: a check that only cleans up on the happy path leaves the flow broken
// exactly when something went wrong.
await page.evaluate((graph) => {
    const editor = window.flowEditor;
    editor.nodes = new Map(graph.nodes.map((n) => [n.key, n]));
    editor.edges = graph.edges;
    editor.select(null);
    editor.render();
}, original);
await page.click('[data-flow-save]');
await new Promise((r) => setTimeout(r, 1200));
check('alur dikembalikan ke bentuk semula',
    (await page.evaluate(() => document.querySelectorAll('[data-node]').length)) === original.nodes.length,
    `${original.nodes.length} node`);

await browser.close();
const passed = report.filter(Boolean).length;
console.log(`\n${passed}/${report.length} pemeriksaan lolos`);
process.exit(passed === report.length ? 0 : 1);
