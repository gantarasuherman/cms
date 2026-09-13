/**
 * The visual flow editor.
 *
 * Nodes are HTML elements positioned over an SVG layer that draws the edges.
 * Not pure SVG: an HTML node can be focused, described and styled with the
 * rest of the admin panel, and a diagram nobody can reach with a keyboard is a
 * diagram half the people who need it cannot edit.
 *
 * Everything the canvas does is also available as a plain list (the Outline
 * panel), because dragging boxes is one view of the graph, not the only way to
 * describe it. The canvas and the list edit the same state.
 */

const GRID = 20;
const NODE_WIDTH = 210;
const NODE_HEIGHT = 68;

/**
 * A colour per kind of route.
 *
 * Colour is never the only signal: every edge also carries its condition as
 * text beside it, so a reader who cannot tell these hues apart loses nothing
 * (WCAG 1.4.1). The colour is there to make a busy diagram scannable.
 */
const EDGE_COLOURS = {
    valid: '#059669',       // jalan yang benar
    invalid: '#e11d48',     // jawaban ditolak
    exhausted: '#d97706',   // menyerah setelah berulang kali
    back: '#64748b',        // kembali ke menu
    category: '#7c3aed',    // pilihan dari tabel
    _option: '#2563eb',     // salah satu angka pada menu
    _plain: '#94a3b8',      // tanpa syarat
};

const edgeColour = (condition) => {
    if (!condition) return EDGE_COLOURS._plain;
    if (EDGE_COLOURS[condition]) return EDGE_COLOURS[condition];

    // A menu option is whatever the administrator typed as its value.
    return EDGE_COLOURS._option;
};

const uid = () => 'n' + Math.random().toString(36).slice(2, 8);

class FlowEditor {
    constructor(root) {
        this.root = root;
        this.canvas = root.querySelector('[data-flow-canvas]');
        this.layer = root.querySelector('[data-flow-nodes]');
        this.svg = root.querySelector('[data-flow-edges]');
        this.panel = root.querySelector('[data-flow-panel]');
        this.status = root.querySelector('[data-flow-status]');
        this.outline = root.querySelector('[data-flow-outline]');

        this.meta = JSON.parse(root.dataset.flowMeta);
        const graph = JSON.parse(root.dataset.flowGraph);

        this.nodes = new Map(graph.nodes.map((n) => [n.key, structuredClone(n)]));
        this.edges = graph.edges.map((e) => ({ ...e, id: uid() }));

        this.selected = null;
        this.connecting = null;
        this.dirty = false;
        this.view = { x: 0, y: 0, scale: 1 };

        // Undo history. Capped because a diagram is small but a long editing
        // session is not, and an unbounded stack of clones is a slow leak.
        this.past = [];
        this.future = [];
        this.historyLimit = 60;

        this.wire();
        this.render();
    }

    /* ----------------------------------------------------------- plumbing */

    wire() {
        this.root.querySelectorAll('[data-flow-add]').forEach((button) => {
            button.addEventListener('click', () => this.addNode(button.dataset.flowAdd));
        });

        this.root.querySelector('[data-flow-save]')?.addEventListener('click', () => this.save());
        this.root.querySelector('[data-flow-fit]')?.addEventListener('click', () => this.fit());
        this.root.querySelector('[data-flow-undo]')?.addEventListener('click', () => this.undo());
        this.root.querySelector('[data-flow-redo]')?.addEventListener('click', () => this.redo());
        this.root.querySelector('[data-flow-fullscreen]')?.addEventListener('click', () => this.toggleFullscreen());

        // The button's wording has to follow the actual state, which Escape can
        // change without going through the button.
        document.addEventListener('fullscreenchange', () => this.syncFullscreen());

        // On the document, not the canvas: Ctrl+Z has to work while the caret
        // is in the property panel, which is where most edits are made. A text
        // field's own undo is left alone — the browser handles that, and
        // stealing it would be worse than not having ours.
        document.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'z' && event.key.toLowerCase() !== 'y') return;

            const editing = document.activeElement?.closest('input, textarea, select');

            if (editing && document.activeElement.value !== document.activeElement.defaultValue) return;

            const redo = event.key.toLowerCase() === 'y' || event.shiftKey;
            redo ? this.redo() : this.undo();
            event.preventDefault();
        });

        this.canvas.addEventListener('pointerdown', (event) => {
            // Panning starts on the background — or anywhere at all with the
            // middle button or space held, which is what people reach for when
            // the canvas is crowded and there is no bare patch to grab.
            // The nodes layer is transparent to the pointer, so a press on
            // bare canvas arrives as the canvas, the svg, or that layer
            // depending on where exactly it landed.
            const onBackground = event.target === this.canvas
                || event.target === this.svg
                || event.target === this.layer;

            if (event.button === 1 || (this.spaceHeld && event.button === 0)) {
                event.preventDefault();
                this.startPan(event);
                return;
            }

            if (onBackground && event.button === 0) {
                this.select(null);
                this.startPan(event);
            }
        });

        // Space turns the whole canvas into a grab surface, as in every other
        // drawing tool. Ignored while typing, or it would eat spaces.
        document.addEventListener('keydown', (event) => {
            if (event.code !== 'Space' || document.activeElement?.closest('input, textarea, select')) return;
            if (!this.root.contains(document.activeElement)) return;

            this.spaceHeld = true;
            this.canvas.classList.add('is-panning');
            event.preventDefault();
        });

        document.addEventListener('keyup', (event) => {
            if (event.code !== 'Space') return;

            this.spaceHeld = false;
            this.canvas.classList.remove('is-panning');
        });

        // Wheel zoom, because a flow outgrows one screen quickly.
        this.canvas.addEventListener('wheel', (event) => {
            if (!event.ctrlKey && !event.metaKey) return;

            event.preventDefault();
            this.zoomAt(event.clientX, event.clientY, event.deltaY < 0 ? 1.1 : 1 / 1.1);
        }, { passive: false });

        this.canvas.addEventListener('keydown', (event) => this.onKey(event));

        // A half-drawn flow is normal; losing it to a stray click is not.
        window.addEventListener('beforeunload', (event) => {
            if (this.dirty) event.preventDefault();
        });
    }

    announce(message) {
        if (this.status) this.status.textContent = message;
    }

    /* ------------------------------------------------------------- history */

    snapshot() {
        return {
            nodes: structuredClone([...this.nodes.values()]),
            edges: structuredClone(this.edges),
            selected: this.selected,
        };
    }

    /**
     * Records the state as it is now, before something changes it.
     *
     * Called at the top of every mutating action rather than after, so undo
     * restores what was on screen a moment ago rather than the result of the
     * change being undone.
     */
    pushHistory(label) {
        this.past.push({ ...this.snapshot(), label });

        if (this.past.length > this.historyLimit) this.past.shift();

        // A new edit makes the redo branch unreachable, exactly as in any
        // other editor: keeping it would let Redo jump to a state that never
        // followed from what is now on screen.
        this.future = [];
        this.syncHistoryButtons();
    }

    restore(state) {
        this.nodes = new Map(state.nodes.map((n) => [n.key, n]));
        this.edges = state.edges;
        this.selected = state.selected && this.nodes.has(state.selected) ? state.selected : null;
        this.markDirty();
        this.render();
        this.syncHistoryButtons();
    }

    undo() {
        if (!this.past.length) {
            this.announce('Tidak ada lagi yang bisa dibatalkan.');
            return;
        }

        const previous = this.past.pop();
        this.future.push({ ...this.snapshot(), label: previous.label });
        this.restore(previous);
        this.announce(`Dibatalkan: ${previous.label}.`);
    }

    redo() {
        if (!this.future.length) {
            this.announce('Tidak ada lagi yang bisa diulangi.');
            return;
        }

        const next = this.future.pop();
        this.past.push({ ...this.snapshot(), label: next.label });
        this.restore(next);
        this.announce(`Diulangi: ${next.label}.`);
    }

    syncHistoryButtons() {
        const undo = this.root.querySelector('[data-flow-undo]');
        const redo = this.root.querySelector('[data-flow-redo]');

        undo?.toggleAttribute('disabled', this.past.length === 0);
        redo?.toggleAttribute('disabled', this.future.length === 0);

        // The name says what will be undone, so somebody can tell whether they
        // want to before pressing it.
        if (undo) {
            undo.setAttribute('aria-label', this.past.length
                ? `Batalkan: ${this.past[this.past.length - 1].label}`
                : 'Batalkan (tidak ada yang bisa dibatalkan)');
        }

        if (redo) {
            redo.setAttribute('aria-label', this.future.length
                ? `Ulangi: ${this.future[this.future.length - 1].label}`
                : 'Ulangi (tidak ada yang bisa diulangi)');
        }
    }

    markDirty() {
        this.dirty = true;
        this.root.querySelector('[data-flow-save]')?.removeAttribute('disabled');
        this.root.querySelector('[data-flow-dirty]')?.removeAttribute('hidden');
    }

    /* -------------------------------------------------------------- nodes */

    /**
     * A name derived from what the node actually does.
     *
     * A canvas of boxes all reading "Aksi" is a canvas nobody can read, so the
     * name follows the choice that matters most for each type — the action, the
     * data source, the kind of answer being asked for.
     */
    autoLabel(node) {
        const type = this.meta.types[node.type]?.label ?? node.type;
        const config = node.config ?? {};

        switch (node.type) {
            case 'action':
                // The short name, not the dropdown's sentence: a node box is
                // about 210px wide and would clip the sentence to nonsense.
                return this.meta.actionNames[config.action] ?? type;
            case 'data_source':
                return this.meta.dataSources[config.data_source] ?? type;
            case 'input':
                return config.input ? `Minta ${this.meta.inputKinds[config.input] ?? config.input}` : type;
            case 'menu':
                return config.options_from ? 'Menu dari Kategori' : type;
            default:
                return type;
        }
    }

    addNode(type) {
        this.pushHistory(`tambah node ${this.meta.types[type]?.label ?? type}`);

        let key = type;
        let n = 2;
        while (this.nodes.has(key)) key = `${type}_${n++}`;

        const centre = this.canvasCentre();
        const node = {
            key,
            type,
            label: null,
            config: type === 'menu' ? { options: [{ value: '1', label: 'Pilihan pertama' }] } : {},
            position: { x: centre.x, y: centre.y },
        };

        // Named the moment it appears, so a new box says what it is rather
        // than showing its key until somebody fills the name in.
        node.label = this.autoLabel(node);

        this.nodes.set(key, node);

        this.markDirty();
        this.render();
        this.select(key);
        this.announce(`Node ${node.label} ditambahkan sebagai ${key}.`);
    }

    removeNode(key) {
        const node = this.nodes.get(key);

        if (node?.type === 'start') {
            this.announce('Node Mulai tidak dapat dihapus — setiap alur membutuhkannya.');
            return;
        }

        this.pushHistory(`hapus node ${node.label || key}`);

        this.nodes.delete(key);
        this.edges = this.edges.filter((e) => e.from !== key && e.to !== key);
        this.select(null);
        this.markDirty();
        this.render();
        this.announce(`Node ${key} dan sambungannya dihapus.`);
    }

    select(key) {
        this.selected = key;
        this.layer.querySelectorAll('[data-node]').forEach((el) => {
            el.classList.toggle('is-selected', el.dataset.node === key);
            el.setAttribute('aria-selected', String(el.dataset.node === key));
        });
        this.renderPanel();
    }

    /* -------------------------------------------------------------- edges */

    connect(from, to, condition = null) {
        if (from === to) {
            this.announce('Node tidak dapat disambungkan ke dirinya sendiri.');
            return;
        }

        const exists = this.edges.some((e) => e.from === from && e.to === to && (e.condition ?? null) === condition);

        if (exists) {
            this.announce('Sambungan itu sudah ada.');
            return;
        }

        this.pushHistory(`sambungkan ${from} ke ${to}`);
        this.edges.push({ id: uid(), from, to, condition, label: null });
        this.markDirty();
        this.render();
        this.announce(`Disambungkan: ${from} ke ${to}.`);
    }

    disconnect(id) {
        const edge = this.edges.find((e) => e.id === id);
        this.pushHistory(edge ? `hapus sambungan ${edge.from} ke ${edge.to}` : 'hapus sambungan');
        this.edges = this.edges.filter((e) => e.id !== id);
        this.markDirty();
        this.render();
        this.announce('Sambungan dihapus.');
    }

    /* ------------------------------------------------------------ drawing */

    render() {
        this.renderNodes();
        this.renderEdges();
        this.renderOutline();
        this.renderPanel();
    }

    renderNodes() {
        this.layer.textContent = '';

        for (const node of this.nodes.values()) {
            const el = document.createElement('div');
            el.className = 'flow-node';
            el.dataset.node = node.key;
            el.dataset.type = node.type;
            el.tabIndex = 0;
            el.setAttribute('role', 'option');
            el.setAttribute('aria-selected', String(this.selected === node.key));
            el.style.transform = `translate(${node.position.x}px, ${node.position.y}px)`;

            const outgoing = this.edges.filter((e) => e.from === node.key).length;
            const incoming = this.edges.filter((e) => e.to === node.key).length;

            el.setAttribute(
                'aria-label',
                `${node.label || node.key}, jenis ${this.meta.types[node.type]?.label ?? node.type}, ` +
                `${incoming} masuk, ${outgoing} keluar. Tekan Enter untuk mengubah, C untuk menyambungkan, Delete untuk menghapus.`,
            );

            el.innerHTML = `
                <span class="flow-node-type">${this.escape(this.meta.types[node.type]?.label ?? node.type)}</span>
                <span class="flow-node-label">${this.escape(node.label || node.key)}</span>
                <span class="flow-node-key">${this.escape(node.key)}</span>`;

            // Ports on all four sides, so a connection can leave and arrive
            // from whichever direction the two boxes actually sit in.
            if (node.type !== 'end') {
                for (const side of ['right', 'bottom', 'left', 'top']) {
                    const port = document.createElement('span');
                    port.className = `flow-port flow-port-${side}`;
                    port.dataset.port = side;
                    port.setAttribute('aria-hidden', 'true');
                    port.addEventListener('pointerdown', (event) => this.startLink(event, node.key));
                    el.appendChild(port);
                }
            }

            el.addEventListener('pointerdown', (event) => this.startDrag(event, node.key));
            el.addEventListener('focus', () => this.select(node.key));
            el.addEventListener('dblclick', () => this.panel?.querySelector('input, select, textarea')?.focus());

            this.layer.appendChild(el);
        }
    }

    renderEdges() {
        this.svg.textContent = '';

        this.svg.appendChild(document.createElementNS('http://www.w3.org/2000/svg', 'defs'));

        for (const edge of this.edges) {
            if (!this.nodes.has(edge.from) || !this.nodes.has(edge.to)) continue;

            const route = this.route(edge.from, edge.to);
            const colour = edgeColour(edge.condition);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', route.d);
            path.setAttribute('class', 'flow-edge');
            path.setAttribute('stroke', colour);
            path.setAttribute('marker-end', `url(#${this.marker(colour)})`);
            this.svg.appendChild(path);

            if (edge.condition) {
                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', String(route.mid.x));
                text.setAttribute('y', String(route.mid.y - 6));
                text.setAttribute('class', 'flow-edge-label');
                text.setAttribute('fill', colour);
                text.setAttribute('text-anchor', 'middle');
                text.textContent = edge.condition;
                this.svg.appendChild(text);
            }
        }
    }

    /**
     * Where a connection leaves and arrives.
     *
     * Chosen from where the two boxes actually sit: a node below gets a line
     * out of the bottom, a node behind gets one out of the left. Always
     * leaving right and arriving left — which is what a fixed layout does —
     * draws a loop back through the diagram for every "kembali ke menu" edge,
     * and those are the edges a flow has most of.
     */
    anchorOf(key, side) {
        const node = this.nodes.get(key);
        const el = this.layer.querySelector(`[data-node="${CSS.escape(key)}"]`);
        const height = el?.offsetHeight || NODE_HEIGHT;

        switch (side) {
            case 'left': return { x: node.position.x, y: node.position.y + height / 2, dx: -1, dy: 0 };
            case 'top': return { x: node.position.x + NODE_WIDTH / 2, y: node.position.y, dx: 0, dy: -1 };
            case 'bottom': return { x: node.position.x + NODE_WIDTH / 2, y: node.position.y + height, dx: 0, dy: 1 };
            default: return { x: node.position.x + NODE_WIDTH, y: node.position.y + height / 2, dx: 1, dy: 0 };
        }
    }

    route(fromKey, toKey) {
        const from = this.nodes.get(fromKey);
        const to = this.nodes.get(toKey);

        const dx = to.position.x - from.position.x;
        const dy = to.position.y - from.position.y;

        let out = 'right';
        let into = 'left';

        if (dx > NODE_WIDTH * 0.4) {
            // Comfortably ahead: straight across.
            out = 'right';
            into = 'left';
        } else if (dx < -NODE_WIDTH * 0.4) {
            // Behind — a return edge. Leaving left and arriving right keeps it
            // short instead of sweeping around the whole diagram.
            out = 'left';
            into = 'right';
        } else {
            // Stacked: down the column, or up it.
            out = dy >= 0 ? 'bottom' : 'top';
            into = dy >= 0 ? 'top' : 'bottom';
        }

        const a = this.anchorOf(fromKey, out);
        const b = this.anchorOf(toKey, into);

        const reach = Math.max(50, Math.hypot(b.x - a.x, b.y - a.y) / 2.2);
        const c1 = { x: a.x + a.dx * reach, y: a.y + a.dy * reach };
        const c2 = { x: b.x + b.dx * reach, y: b.y + b.dy * reach };

        return {
            d: `M${a.x},${a.y} C${c1.x},${c1.y} ${c2.x},${c2.y} ${b.x},${b.y}`,
            // The midpoint of the curve itself, so a label sits on the line
            // rather than floating where the straight line would have been.
            mid: {
                x: (a.x + 3 * c1.x + 3 * c2.x + b.x) / 8,
                y: (a.y + 3 * c1.y + 3 * c2.y + b.y) / 8,
            },
        };
    }

    /** One arrow head per colour; SVG markers cannot inherit a stroke. */
    marker(colour) {
        const id = 'flow-arrow-' + colour.replace('#', '');

        if (!this.svg.querySelector(`#${id}`)) {
            const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
            marker.setAttribute('id', id);
            marker.setAttribute('viewBox', '0 0 10 10');
            marker.setAttribute('refX', '9');
            marker.setAttribute('refY', '5');
            marker.setAttribute('markerWidth', '6');
            marker.setAttribute('markerHeight', '6');
            marker.setAttribute('orient', 'auto-start-reverse');
            marker.innerHTML = `<path d="M0,0 L10,5 L0,10 z" fill="${colour}" />`;
            this.svg.querySelector('defs')?.appendChild(marker);
        }

        return id;
    }

    /* ------------------------------------------------------------ outline */

    /**
     * The same graph as a list of controls.
     *
     * This is not a fallback bolted on: connecting boxes by dragging is
     * impossible with a keyboard alone and invisible to a screen reader, so
     * the outline is how the editor is operable at all for some people. It
     * edits the same state the canvas does.
     */
    renderOutline() {
        if (!this.outline) return;

        this.outline.textContent = '';

        for (const node of this.nodes.values()) {
            const item = document.createElement('li');
            item.className = 'flow-outline-item';

            const heading = document.createElement('div');
            heading.className = 'flow-outline-head';
            // The key is shown, not just the label: edges are addressed by key,
            // so somebody typing a condition needs to be able to read it.
            heading.innerHTML =
                `<strong>${this.escape(node.label || node.key)}</strong>` +
                `<code class="flow-outline-key">${this.escape(node.key)}</code>` +
                `<span class="flow-outline-type">${this.escape(this.meta.types[node.type]?.label ?? node.type)}</span>`;
            item.appendChild(heading);

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'flow-outline-action';
            edit.textContent = 'Ubah';
            edit.addEventListener('click', () => {
                this.select(node.key);
                this.panel?.querySelector('input, select, textarea')?.focus();
            });
            heading.appendChild(edit);

            const list = document.createElement('ul');
            list.className = 'flow-outline-edges';

            for (const edge of this.edges.filter((e) => e.from === node.key)) {
                const row = document.createElement('li');
                row.innerHTML = `<span>${this.escape(edge.condition ?? 'selalu')} →
                    ${this.escape(this.nodes.get(edge.to)?.label ?? edge.to)}</span>`;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'flow-outline-action';
                remove.textContent = 'Hapus';
                remove.setAttribute('aria-label', `Hapus sambungan ${edge.condition ?? 'selalu'} ke ${edge.to}`);
                remove.addEventListener('click', () => this.disconnect(edge.id));
                row.appendChild(remove);

                list.appendChild(row);
            }

            // Adding a connection without a mouse: pick the target and the
            // condition from real form controls.
            const adder = document.createElement('li');
            adder.className = 'flow-outline-add';

            const target = document.createElement('select');
            target.setAttribute('aria-label', `Sambungkan ${node.label || node.key} ke`);
            target.innerHTML = '<option value="">Sambungkan ke…</option>' +
                [...this.nodes.values()]
                    .filter((n) => n.key !== node.key)
                    .map((n) => `<option value="${this.escape(n.key)}">${this.escape(n.label || n.key)} (${this.escape(n.key)})</option>`)
                    .join('');

            const condition = document.createElement('input');
            condition.type = 'text';
            condition.placeholder = 'kondisi (valid, 1, back…)';
            condition.setAttribute('aria-label', 'Kondisi sambungan, kosongkan untuk tanpa syarat');

            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'flow-outline-action';
            add.textContent = 'Tambah';
            add.addEventListener('click', () => {
                if (!target.value) return;
                this.connect(node.key, target.value, condition.value.trim() || null);
            });

            adder.append(target, condition, add);
            list.appendChild(adder);
            item.appendChild(list);
            this.outline.appendChild(item);
        }
    }

    /* -------------------------------------------------------- interaction */

    startDrag(event, key) {
        if (event.button !== 0) return;

        const node = this.nodes.get(key);
        const start = { x: event.clientX, y: event.clientY, nx: node.position.x, ny: node.position.y };
        let moved = false;

        try {
            // Keeps events flowing when the cursor leaves the box. Only an
            // optimisation — the listeners below are on `window` — so a
            // refusal here must not abort the drag, which is what an unguarded
            // call does when the pointer id is not one the element owns.
            event.target.setPointerCapture?.(event.pointerId);
        } catch {
            /* dragging still works without it */
        }

        event.stopPropagation();

        const move = (e) => {
            const dx = (e.clientX - start.x) / this.view.scale;
            const dy = (e.clientY - start.y) / this.view.scale;

            if (!moved && Math.hypot(dx, dy) < 3) return;

            if (!moved) {
                // Once, at the moment a click becomes a drag — not on every
                // pointermove, which would fill the history with one entry per
                // pixel and make Undo useless.
                this.pushHistory(`pindahkan ${node.label || key}`);
                moved = true;
            }

            // Snapped to a grid so a diagram drawn by hand still lines up;
            // holding Alt lets somebody place a node exactly where they want.
            node.position.x = e.altKey ? Math.round(start.nx + dx) : Math.round((start.nx + dx) / GRID) * GRID;
            node.position.y = e.altKey ? Math.round(start.ny + dy) : Math.round((start.ny + dy) / GRID) * GRID;

            const el = this.layer.querySelector(`[data-node="${CSS.escape(key)}"]`);
            if (el) el.style.transform = `translate(${node.position.x}px, ${node.position.y}px)`;
            this.renderEdges();
        };

        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (moved) this.markDirty();
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    /**
     * Drags a new connection out of a node.
     *
     * The condition is worked out on drop rather than asked for: a menu with
     * options 1–3 where 1 is already routed offers 2, an input offers "valid".
     * Guessing wrong costs one edit in the list below; asking every time costs
     * a dialog on every connection.
     */
    startLink(event, from) {
        event.stopPropagation();
        event.preventDefault();

        const ghost = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        ghost.setAttribute('class', 'flow-edge flow-edge-ghost');
        this.svg.appendChild(ghost);

        this.canvas.classList.add('is-linking');
        this.announce('Tarik ke node tujuan, lalu lepaskan.');

        const origin = this.anchorOf(from, 'right');

        const move = (e) => {
            const point = this.toCanvas(e.clientX, e.clientY);
            ghost.setAttribute('d', `M${origin.x},${origin.y} L${point.x},${point.y}`);

            const over = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-node]');
            this.layer.querySelectorAll('[data-node]').forEach((el) =>
                el.classList.toggle('is-target', el === over && el.dataset.node !== from));
        };

        const up = (e) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            ghost.remove();
            this.canvas.classList.remove('is-linking');
            this.layer.querySelectorAll('[data-node]').forEach((el) => el.classList.remove('is-target'));

            const target = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-node]');

            if (!target || target.dataset.node === from) {
                this.announce('Penyambungan dibatalkan.');
                return;
            }

            this.connect(from, target.dataset.node, this.suggestCondition(from));
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    /** The condition a new edge out of this node most likely wants. */
    suggestCondition(from) {
        const node = this.nodes.get(from);
        const taken = new Set(this.edges.filter((e) => e.from === from).map((e) => e.condition));

        if (node.type === 'menu') {
            if (node.config?.options_from) return taken.has('category') ? 'back' : 'category';

            const free = (node.config?.options ?? []).find((o) => !taken.has(String(o.value)));

            return free ? String(free.value) : (node.config?.include_back && !taken.has('back') ? 'back' : null);
        }

        if (['input', 'data_source', 'action'].includes(node.type)) {
            if (!taken.has('valid')) return 'valid';
            if (!taken.has('invalid')) return 'invalid';

            return taken.has('exhausted') ? null : 'exhausted';
        }

        return null;
    }

    /** Canvas coordinates from a screen point, accounting for pan and zoom. */
    toCanvas(clientX, clientY) {
        const box = this.canvas.getBoundingClientRect();

        return {
            x: (clientX - box.left - this.view.x) / this.view.scale,
            y: (clientY - box.top - this.view.y) / this.view.scale,
        };
    }

    startPan(event) {
        const start = { x: event.clientX, y: event.clientY, vx: this.view.x, vy: this.view.y };

        const move = (e) => {
            this.view.x = start.vx + (e.clientX - start.x);
            this.view.y = start.vy + (e.clientY - start.y);
            this.applyView();
        };

        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    /** Zooms around a point, so the thing under the pointer stays under it. */
    zoomAt(clientX, clientY, factor) {
        const before = this.toCanvas(clientX, clientY);
        this.view.scale = Math.min(2, Math.max(0.35, this.view.scale * factor));
        const after = this.toCanvas(clientX, clientY);

        this.view.x += (after.x - before.x) * this.view.scale;
        this.view.y += (after.y - before.y) * this.view.scale;
        this.applyView();
        this.announce(`Perbesaran ${Math.round(this.view.scale * 100)}%.`);
    }

    async toggleFullscreen() {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                // The whole editor, not just the canvas: the toolbar and the
                // property panel are what make the extra room worth having.
                await this.root.requestFullscreen();
            }
        } catch (error) {
            this.announce('Layar penuh tidak tersedia: ' + error.message);
        }
    }

    syncFullscreen() {
        const active = document.fullscreenElement === this.root;
        const button = this.root.querySelector('[data-flow-fullscreen]');

        this.root.classList.toggle('is-fullscreen', active);
        button?.setAttribute('aria-pressed', String(active));
        button?.setAttribute('aria-label', active ? 'Keluar dari layar penuh' : 'Tampilkan satu layar penuh');

        this.announce(active ? 'Layar penuh. Tekan Escape untuk keluar.' : 'Kembali ke tampilan biasa.');
    }

    applyView() {
        const transform = `translate(${this.view.x}px, ${this.view.y}px) scale(${this.view.scale})`;
        this.layer.style.transform = transform;
        this.svg.style.transform = transform;
    }

    /**
     * Every canvas gesture, from the keyboard.
     *
     * Arrow keys move the selected node, C starts a connection, Enter opens
     * its properties, Delete removes it. Without this the editor would need a
     * mouse, and the outline below would be the only way in for anybody who
     * does not use one.
     */
    onKey(event) {
        const key = this.selected;

        if (this.connecting) {
            if (event.key === 'Escape') {
                this.connecting = null;
                this.announce('Penyambungan dibatalkan.');
                event.preventDefault();
            } else if (event.key === 'Enter' && key) {
                const from = this.connecting;
                this.connecting = null;
                this.connect(from, key);
                event.preventDefault();
            }
            return;
        }

        if (!key) return;

        const node = this.nodes.get(key);
        const step = event.shiftKey ? GRID * 5 : GRID;

        if (event.key.startsWith('Arrow')) {
            // A run of arrow presses on one node is one move, not twelve:
            // undoing a nudge should not take twelve presses.
            const last = this.past[this.past.length - 1];
            const signature = `geser ${key}`;

            if (!last || last.label !== signature || Date.now() - (this.lastNudge ?? 0) > 1500) {
                this.pushHistory(signature);
            }

            this.lastNudge = Date.now();
        }

        switch (event.key) {
            case 'ArrowUp': node.position.y -= step; break;
            case 'ArrowDown': node.position.y += step; break;
            case 'ArrowLeft': node.position.x -= step; break;
            case 'ArrowRight': node.position.x += step; break;
            case 'Delete':
            case 'Backspace':
                this.removeNode(key);
                event.preventDefault();
                return;
            case 'c':
            case 'C':
                this.connecting = key;
                this.announce(`Menyambung dari ${node.label || key}. Pindah ke node tujuan dengan Tab, lalu tekan Enter. Escape untuk batal.`);
                event.preventDefault();
                return;
            case 'Enter':
                this.panel?.querySelector('input, select, textarea')?.focus();
                event.preventDefault();
                return;
            default:
                return;
        }

        event.preventDefault();
        this.markDirty();
        this.renderNodes();
        this.renderEdges();
        this.layer.querySelector(`[data-node="${CSS.escape(key)}"]`)?.focus();
        this.announce(`${node.label || key} dipindah ke ${node.position.x}, ${node.position.y}.`);
    }

    canvasCentre() {
        const box = this.canvas.getBoundingClientRect();

        return {
            x: Math.round((box.width / 2 - this.view.x) / this.view.scale / GRID) * GRID,
            y: Math.round((box.height / 2 - this.view.y) / this.view.scale / GRID) * GRID,
        };
    }

    fit() {
        const nodes = [...this.nodes.values()];
        if (!nodes.length) return;

        const minX = Math.min(...nodes.map((n) => n.position.x));
        const minY = Math.min(...nodes.map((n) => n.position.y));

        this.view = { x: 40 - minX, y: 40 - minY, scale: 1 };
        this.applyView();
        this.announce('Tampilan disetel ulang.');
    }

    /* --------------------------------------------------------------- panel */

    renderPanel() {
        if (!this.panel) return;

        const node = this.selected ? this.nodes.get(this.selected) : null;

        if (!node) {
            this.panel.innerHTML =
                '<p class="text-sm text-muted-foreground">Pilih sebuah node untuk mengubah isinya, ' +
                'atau gunakan daftar di bawah kanvas.</p>';
            return;
        }

        const field = (label, control, hint = '') =>
            `<div class="mb-4"><label class="mb-1.5 block text-sm font-medium">${this.escape(label)}</label>${control}` +
            (hint ? `<p class="mt-1 text-xs text-muted-foreground">${this.escape(hint)}</p>` : '') + '</div>';

        const input = (name, value, attrs = '') =>
            `<input data-field="${name}" value="${this.escape(value ?? '')}" ${attrs}
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">`;

        const select = (name, options, value) =>
            `<select data-field="${name}" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">` +
            Object.entries(options).map(([k, v]) =>
                `<option value="${this.escape(k)}"${k === value ? ' selected' : ''}>${this.escape(v)}</option>`).join('') +
            '</select>';

        const derived = (node.label ?? '') === this.autoLabel(node);

        let html = field('Nama', input('label', node.label),
            derived
                ? 'Mengikuti pilihan di bawah. Ketik sendiri bila ingin nama tetap.'
                : 'Nama pilihan Anda sendiri; tidak akan tertimpa oleh perubahan di bawah.');
        html += field('Kunci', input('key', node.key, node.type === 'start' ? 'readonly' : ''),
            'Dipakai sambungan. Huruf kecil, angka, dan garis bawah.');

        if (node.type !== 'start') {
            html += field('Teks yang dikirim',
                `<textarea data-field="config.text" rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">${this.escape(node.config?.text ?? '')}</textarea>`,
                'Boleh memakai {site_name}, {ticket}, {category}, {status}.');
        }

        if (node.type === 'input') {
            html += field('Jenis masukan', select('config.input', this.meta.inputKinds, node.config?.input ?? 'text'));
        }

        if (node.type === 'action') {
            html += field('Aksi', select('config.action', this.meta.actions, node.config?.action ?? ''));
        }

        if (node.type === 'data_source') {
            html += field('Sumber data', select('config.data_source', this.meta.dataSources, node.config?.data_source ?? ''));
        }

        if (node.type === 'ai') {
            const chosen = new Set(node.config?.sources ?? []);

            html += field('Boleh menjawab dari',
                `<div class="space-y-1.5">${Object.entries(this.meta.dataSources).map(([slug, name]) => `
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" data-ai-source="${this.escape(slug)}"
                               ${chosen.has(slug) ? 'checked' : ''}
                               class="h-4 w-4 rounded border-input">
                        ${this.escape(name)}
                    </label>`).join('')}</div>`,
                'Hanya isi ini yang boleh dipakai menjawab. Tidak dicentang sama sekali berarti semuanya.');

            html += field('Watak jawaban',
                `<textarea data-field="config.persona" rows="3"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">${this.escape(node.config?.persona ?? '')}</textarea>`,
                'Siapa yang sedang menjawab, dan nada bicaranya. Tidak menambah pengetahuan — jawaban tetap hanya dari sumber di atas.');

            html += field('Batas giliran', input('config.max_turns', node.config?.max_turns ?? 8, 'type="number" min="1" max="20"'),
                'Setelah sekian tanya-jawab, percakapan ditutup. Menjaga biaya dan mencegah obrolan tak berujung.');

            html += field('Kalimat penutup', input('config.closing_message', node.config?.closing_message),
                'Dikirim saat pengguna membalas "selesai".');
        }

        if (node.type === 'menu') {
            html += field('Pilihan',
                `<div data-options class="space-y-2">${(node.config?.options ?? []).map((o, i) => `
                    <div class="flex gap-2">
                        <input data-option-value="${i}" value="${this.escape(o.value)}" aria-label="Nilai pilihan ${i + 1}"
                               class="w-16 rounded-md border border-input bg-background px-2 py-1.5 text-sm">
                        <input data-option-label="${i}" value="${this.escape(o.label)}" aria-label="Teks pilihan ${i + 1}"
                               class="flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm">
                        <button type="button" data-option-remove="${i}" aria-label="Hapus pilihan ${i + 1}"
                                class="rounded-md px-2 text-muted-foreground hover:bg-accent">×</button>
                    </div>`).join('')}</div>
                 <button type="button" data-option-add class="mt-2 text-sm font-medium text-primary">+ Tambah pilihan</button>`,
                'Nilainya dipakai sebagai kondisi sambungan.');
        }

        if (['menu', 'input', 'data_source'].includes(node.type)) {
            html += field('Pesan bila salah', input('config.invalid_message', node.config?.invalid_message));
            html += field('Bila gagal berulang', select('config.on_invalid', this.meta.retryBehaviours, node.config?.on_invalid ?? 'repeat'));
            html += field('Batas percobaan', input('config.max_retries', node.config?.max_retries ?? 3, 'type="number" min="1" max="10"'));
        }

        html += `<button type="button" data-node-remove
            class="mt-2 w-full rounded-md border border-destructive/40 px-3 py-2 text-sm font-medium text-destructive hover:bg-destructive/10"
            ${node.type === 'start' ? 'disabled' : ''}>Hapus node ini</button>`;

        this.panel.innerHTML = html;
        this.wirePanel(node);
    }

    wirePanel(node) {
        this.panel.querySelectorAll('[data-field]').forEach((el) => {
            el.addEventListener('change', () => {
                const path = el.dataset.field;
                const value = el.type === 'number' ? Number(el.value) : el.value;

                if (path === 'key') {
                    this.rename(node.key, value.trim());
                    return;
                }

                this.pushHistory(`ubah ${path.replace('config.', '')} pada ${node.label || node.key}`);

                // Whether the name is still the derived one, judged before the
                // change lands. A name somebody typed themselves must survive
                // every later property change; a derived one should follow it.
                const wasDerived = (node.label ?? '') === this.autoLabel(node);

                if (path.startsWith('config.')) {
                    node.config = node.config ?? {};
                    node.config[path.slice(7)] = value === '' ? null : value;
                } else {
                    node[path] = value;
                }

                if (path !== 'label' && wasDerived) {
                    const derived = this.autoLabel(node);

                    if (derived !== node.label) {
                        node.label = derived;
                        this.announce(`Nama node mengikuti pilihan: ${derived}.`);
                        this.renderPanel();
                    }
                }

                this.markDirty();
                this.renderNodes();
                this.renderOutline();
            });
        });

        this.panel.querySelector('[data-option-add]')?.addEventListener('click', () => {
            this.pushHistory(`tambah pilihan pada ${node.label || node.key}`);
            node.config = node.config ?? {};
            node.config.options = [...(node.config.options ?? [])];
            node.config.options.push({ value: String(node.config.options.length + 1), label: '' });
            this.markDirty();
            this.renderPanel();
        });

        this.panel.querySelectorAll('[data-option-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                this.pushHistory(`hapus pilihan pada ${node.label || node.key}`);
                node.config.options.splice(Number(button.dataset.optionRemove), 1);
                this.markDirty();
                this.renderPanel();
            });
        });

        this.panel.querySelectorAll('[data-option-value], [data-option-label]').forEach((el) => {
            el.addEventListener('change', () => {
                this.pushHistory(`ubah pilihan pada ${node.label || node.key}`);
                const index = Number(el.dataset.optionValue ?? el.dataset.optionLabel);
                const key = el.dataset.optionValue !== undefined ? 'value' : 'label';
                node.config.options[index][key] = el.value;
                this.markDirty();
                this.renderOutline();
            });
        });

        this.panel.querySelectorAll('[data-ai-source]').forEach((box) => {
            box.addEventListener('change', () => {
                this.pushHistory(`ubah sumber pada ${node.label || node.key}`);
                node.config = node.config ?? {};
                node.config.sources = [...this.panel.querySelectorAll('[data-ai-source]:checked')]
                    .map((el) => el.dataset.aiSource);
                this.markDirty();
                this.renderOutline();
            });
        });

        this.panel.querySelector('[data-node-remove]')?.addEventListener('click', () => this.removeNode(node.key));
    }

    /** Renaming a node has to carry its edges with it, or the graph breaks. */
    rename(from, to) {
        if (!to || from === to) return;

        if (!/^[a-z0-9_]+$/.test(to)) {
            this.announce('Kunci hanya boleh huruf kecil, angka, dan garis bawah.');
            this.renderPanel();
            return;
        }

        if (this.nodes.has(to)) {
            this.announce(`Kunci "${to}" sudah dipakai node lain.`);
            this.renderPanel();
            return;
        }

        this.pushHistory(`ubah kunci ${from} menjadi ${to}`);

        const node = this.nodes.get(from);
        node.key = to;

        const reordered = new Map();
        for (const [key, value] of this.nodes) reordered.set(key === from ? to : key, value);
        this.nodes = reordered;

        this.edges.forEach((edge) => {
            if (edge.from === from) edge.from = to;
            if (edge.to === from) edge.to = to;
        });

        this.selected = to;
        this.markDirty();
        this.render();
        this.announce(`Kunci diubah menjadi ${to}.`);
    }

    /* ---------------------------------------------------------------- save */

    async save() {
        const button = this.root.querySelector('[data-flow-save]');
        button?.setAttribute('disabled', 'disabled');
        this.announce('Menyimpan…');

        const payload = {
            nodes: [...this.nodes.values()].map((n) => ({
                key: n.key,
                type: n.type,
                label: n.label || null,
                config: n.config ?? {},
                position: { x: Math.round(n.position.x), y: Math.round(n.position.y) },
            })),
            edges: this.edges.map((e) => ({ from: e.from, to: e.to, condition: e.condition, label: e.label })),
        };

        try {
            const response = await fetch(this.meta.saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json();

            if (!response.ok) {
                // Validation errors name the node, so say which one rather than
                // "something is wrong".
                const first = Object.values(body.errors ?? {})[0]?.[0] ?? body.message;
                this.announce('Gagal menyimpan: ' + first);
                button?.removeAttribute('disabled');
                return;
            }

            this.dirty = false;
            this.root.querySelector('[data-flow-dirty]')?.setAttribute('hidden', 'hidden');
            this.showProblems(body.problems ?? []);
            this.announce(body.message ?? 'Alur disimpan.');
        } catch (error) {
            this.announce('Gagal menyimpan: ' + error.message);
            button?.removeAttribute('disabled');
        }
    }

    showProblems(problems) {
        const box = this.root.querySelector('[data-flow-problems]');
        if (!box) return;

        if (!problems.length) {
            box.innerHTML = '<p class="text-sm text-emerald-700 dark:text-emerald-400">Tidak ada masalah. Alur siap diterbitkan.</p>';
            return;
        }

        box.innerHTML = '<ul class="space-y-2">' + problems.map((p) =>
            `<li class="text-sm ${p.level === 'error' ? 'text-destructive' : 'text-amber-700 dark:text-amber-400'}">
                ${p.node ? `<strong>${this.escape(p.node)}</strong>: ` : ''}${this.escape(p.message)}
            </li>`).join('') + '</ul>';
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
}

export function initFlowEditor() {
    const root = document.querySelector('[data-flow-editor]');
    if (root) window.flowEditor = new FlowEditor(root);
}

export { FlowEditor, GRID, NODE_WIDTH, EDGE_COLOURS, edgeColour, uid };
