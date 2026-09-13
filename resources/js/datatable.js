/**
 * Minimal, dependency-free client for Yajra's server-side DataTables protocol.
 *
 * Yajra stays the server-side producer (search, sort, paginate, filter all run
 * in SQL); this module is the transport and rendering layer, which lets the
 * admin UI meet the "no jQuery" constraint.
 *
 * Markup contract:
 *   <div data-datatable data-url="..." data-length="15">
 *     <input data-dt-search>                       optional
 *     <select data-dt-filter="status">             optional, repeatable
 *     <table>
 *       <thead><tr><th data-column="title" data-orderable="true">...</th></tr></thead>
 *       <tbody data-dt-body></tbody>
 *     </table>
 *     <p data-dt-info></p>
 *     <nav data-dt-pagination></nav>
 *   </div>
 *
 * Columns are rendered as text. Only columns marked data-raw="true" are
 * injected as HTML, and only the server produces those.
 */

const DEBOUNCE_MS = 300;

class DataTable {
    constructor(root) {
        this.root = root;
        this.url = root.dataset.url;
        this.length = Number(root.dataset.length || 15);
        this.start = 0;
        this.draw = 0;
        this.order = { column: null, dir: 'asc' };
        this.searchInput = root.querySelector('[data-dt-search]');
        this.body = root.querySelector('[data-dt-body]');
        this.info = root.querySelector('[data-dt-info]');
        this.pagination = root.querySelector('[data-dt-pagination]');
        this.filters = Array.from(root.querySelectorAll('[data-dt-filter]'));
        this.columns = Array.from(root.querySelectorAll('thead th')).map((th) => ({
            data: th.dataset.column || '',
            orderable: th.dataset.orderable === 'true',
            searchable: th.dataset.searchable !== 'false',
            raw: th.dataset.raw === 'true',
            el: th,
        }));
        this.controller = null;

        this.bind();
        this.load();
    }

    bind() {
        let timer;
        this.searchInput?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                this.start = 0;
                this.load();
            }, DEBOUNCE_MS);
        });

        this.filters.forEach((filter) =>
            filter.addEventListener('change', () => {
                this.start = 0;
                this.load();
            }),
        );

        this.columns.forEach((column, index) => {
            if (!column.orderable) {
                return;
            }

            const button = column.el.querySelector('button') ?? column.el;
            column.el.setAttribute('aria-sort', 'none');
            button.addEventListener('click', () => this.sortBy(index));
        });
    }

    sortBy(index) {
        const dir = this.order.column === index && this.order.dir === 'asc' ? 'desc' : 'asc';
        this.order = { column: index, dir };
        this.start = 0;

        this.columns.forEach((column, i) => {
            if (!column.orderable) return;
            column.el.setAttribute('aria-sort', i === index ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
        });

        this.load();
    }

    params() {
        const params = new URLSearchParams();
        params.set('draw', String(++this.draw));
        params.set('start', String(this.start));
        params.set('length', String(this.length));
        params.set('search[value]', this.searchInput?.value ?? '');
        params.set('search[regex]', 'false');

        this.columns.forEach((column, index) => {
            params.set(`columns[${index}][data]`, column.data);
            params.set(`columns[${index}][name]`, column.data);
            params.set(`columns[${index}][searchable]`, String(column.searchable));
            params.set(`columns[${index}][orderable]`, String(column.orderable));
            params.set(`columns[${index}][search][value]`, '');
            params.set(`columns[${index}][search][regex]`, 'false');
        });

        if (this.order.column !== null) {
            params.set('order[0][column]', String(this.order.column));
            params.set('order[0][dir]', this.order.dir);
        }

        this.filters.forEach((filter) => {
            if (filter.value !== '') {
                params.set(filter.dataset.dtFilter, filter.value);
            }
        });

        return params;
    }

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.setBusy(true);

        try {
            const response = await fetch(`${this.url}?${this.params()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: this.controller.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            this.render(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.renderMessage('Gagal memuat data. Muat ulang halaman untuk mencoba lagi.');
            }
        } finally {
            this.setBusy(false);
        }
    }

    setBusy(busy) {
        this.body?.setAttribute('aria-busy', String(busy));
    }

    render(payload) {
        const rows = payload.data ?? [];
        this.body.replaceChildren();

        if (rows.length === 0) {
            this.renderMessage('Tidak ada data yang cocok.');
        } else {
            rows.forEach((row) => this.body.appendChild(this.renderRow(row)));
        }

        this.renderInfo(payload);
        this.renderPagination(payload);
    }

    renderRow(row) {
        const tr = document.createElement('tr');
        tr.className = 'transition-colors hover:bg-muted/50';

        this.columns.forEach((column) => {
            const td = document.createElement('td');
            td.className = 'px-4 py-3 align-middle';
            const value = row[column.data];

            if (column.raw) {
                td.innerHTML = value ?? '';
            } else {
                td.textContent = value ?? '';
            }

            tr.appendChild(td);
        });

        return tr;
    }

    renderMessage(message) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = this.columns.length;
        td.className = 'px-4 py-10 text-center text-muted-foreground';
        td.textContent = message;
        tr.appendChild(td);
        this.body.replaceChildren(tr);
    }

    renderInfo(payload) {
        if (!this.info) return;

        const total = payload.recordsFiltered ?? 0;
        const from = total === 0 ? 0 : this.start + 1;
        const to = Math.min(this.start + this.length, total);
        this.info.textContent = `Menampilkan ${from}–${to} dari ${total} data`;
    }

    renderPagination(payload) {
        if (!this.pagination) return;

        const total = payload.recordsFiltered ?? 0;
        const pages = Math.max(1, Math.ceil(total / this.length));
        const current = Math.floor(this.start / this.length) + 1;

        this.pagination.replaceChildren();

        const list = document.createElement('ul');
        list.className = 'flex flex-wrap items-center gap-1';

        const addButton = (label, page, { disabled = false, active = false } = {}) => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.className = [
                'min-w-9 rounded-md border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-transparent bg-primary font-medium text-primary-foreground'
                    : 'border-input bg-background hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-40',
            ].join(' ');

            if (active) {
                button.setAttribute('aria-current', 'page');
            }

            button.addEventListener('click', () => {
                this.start = (page - 1) * this.length;
                this.load();
            });

            li.appendChild(button);
            list.appendChild(li);
        };

        addButton('Sebelumnya', current - 1, { disabled: current <= 1 });

        const window_ = 2;
        for (let page = 1; page <= pages; page += 1) {
            const near = Math.abs(page - current) <= window_;
            if (page === 1 || page === pages || near) {
                addButton(String(page), page, { active: page === current });
            } else if (page === current - window_ - 1 || page === current + window_ + 1) {
                const li = document.createElement('li');
                li.className = 'px-2 text-muted-foreground';
                li.textContent = '…';
                list.appendChild(li);
            }
        }

        addButton('Berikutnya', current + 1, { disabled: current >= pages });

        this.pagination.appendChild(list);
    }
}

export function initDataTables(root = document) {
    root.querySelectorAll('[data-datatable]').forEach((el) => new DataTable(el));
}
