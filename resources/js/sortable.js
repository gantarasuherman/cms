/**
 * Reorderable list with two equally capable controls: dragging for pointers,
 * and up/down buttons for the keyboard. Drag-and-drop on its own cannot be
 * operated without a mouse, so the buttons are not a fallback — they are the
 * accessible path, and both persist through the same endpoint.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

class SortableList {
    constructor(root) {
        this.root = root;
        this.url = root.dataset.reorderUrl;
        this.status = root.parentElement?.querySelector('[data-sortable-status]');
        this.dragged = null;

        this.bind();
        this.syncButtons();
    }

    items() {
        return Array.from(this.root.querySelectorAll('[data-sortable-item]'));
    }

    bind() {
        this.root.addEventListener('dragstart', (event) => {
            this.dragged = event.target.closest('[data-sortable-item]');
            this.dragged?.classList.add('opacity-50');
            event.dataTransfer.effectAllowed = 'move';
        });

        this.root.addEventListener('dragend', () => {
            this.dragged?.classList.remove('opacity-50');
            this.dragged = null;
            this.persist();
        });

        this.root.addEventListener('dragover', (event) => {
            event.preventDefault();

            const target = event.target.closest('[data-sortable-item]');

            if (!target || !this.dragged || target === this.dragged) {
                return;
            }

            const { top, height } = target.getBoundingClientRect();
            const after = event.clientY > top + height / 2;

            target.parentNode.insertBefore(this.dragged, after ? target.nextSibling : target);
        });

        this.root.addEventListener('click', (event) => {
            const up = event.target.closest('[data-sortable-up]');
            const down = event.target.closest('[data-sortable-down]');

            if (!up && !down) {
                return;
            }

            const item = event.target.closest('[data-sortable-item]');
            const sibling = up ? item.previousElementSibling : item.nextElementSibling;

            if (!sibling) {
                return;
            }

            if (up) {
                item.parentNode.insertBefore(item, sibling);
            } else {
                item.parentNode.insertBefore(sibling, item);
            }

            // Focus follows the moved row, otherwise the keyboard user is
            // dropped back at the top of the page after every move.
            (up ? item.querySelector('[data-sortable-up]') : item.querySelector('[data-sortable-down]'))?.focus();

            this.syncButtons();
            this.persist();
        });
    }

    /** Disables the moves that cannot happen, at both ends of the list. */
    syncButtons() {
        const items = this.items();

        items.forEach((item, index) => {
            const up = item.querySelector('[data-sortable-up]');
            const down = item.querySelector('[data-sortable-down]');

            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === items.length - 1;
        });
    }

    async persist() {
        const order = this.items().map((item) => Number(item.dataset.id));

        this.syncButtons();

        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            this.announce('Urutan tersimpan.');
        } catch {
            this.announce('Urutan gagal disimpan. Muat ulang halaman dan coba lagi.');
        }
    }

    announce(message) {
        if (this.status) {
            this.status.textContent = message;
        }
    }
}

export function initSortable(root = document) {
    root.querySelectorAll('[data-sortable]').forEach((el) => new SortableList(el));
}
