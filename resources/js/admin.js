import { initDataTables } from './datatable';
import { initSortable } from './sortable';

/* Sidebar: off-canvas below lg, persistent above. */
function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');

    if (!sidebar) {
        return;
    }

    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        overlay?.classList.toggle('hidden', !open);
        toggles.forEach((t) => t.setAttribute('aria-expanded', String(open)));
        if (open) {
            sidebar.querySelector('a, button')?.focus();
        }
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            setOpen(sidebar.classList.contains('-translate-x-full'));
        });
    });

    overlay?.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            setOpen(false);
            document.querySelector('[data-sidebar-toggle]')?.focus();
        }
    });
}

const railIsCollapsed = () =>
    document.body.dataset.sidebarCollapsed === 'true' && window.matchMedia('(min-width: 768px)').matches;

/* Menu groups behave differently in the two rail widths, and the markup has to
   say which one it is:

   - wide rail: a disclosure. The button owns aria-expanded and [hidden] takes
     the panel out of the accessibility tree when closed.
   - collapsed rail: a flyout opened by hover or focus. Activating the button
     opens nothing, so it must not claim to be a disclosure — it advertises a
     popup instead, and aria-expanded tracks whether the flyout is actually
     showing. */
function initMenuGroups() {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        const node = button.closest('.sidebar-node');

        if (!panel) {
            return;
        }

        button.addEventListener('click', (event) => {
            if (railIsCollapsed()) {
                // Nothing to toggle: the flyout follows hover and focus.
                event.preventDefault();
                return;
            }

            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
            button.querySelector('[data-menu-chevron]')?.classList.toggle('rotate-90', !expanded);
        });

        if (!node) {
            return;
        }

        // Mirror the flyout's real visibility while the rail is collapsed.
        const reflect = (open) => {
            if (railIsCollapsed()) {
                button.setAttribute('aria-expanded', String(open));
            }
        };

        node.addEventListener('pointerenter', () => reflect(true));
        node.addEventListener('pointerleave', () => reflect(false));
        node.addEventListener('focusin', () => reflect(true));
        node.addEventListener('focusout', (event) => {
            if (!node.contains(event.relatedTarget)) {
                reflect(false);
            }
        });
    });
}

/* Keeps the group buttons — and their panels — honest about which pattern they
   are in.

   The panel carries [hidden] as a disclosure in the wide rail. That attribute
   has to come off entirely while the rail is collapsed: the panel is a popup
   then, shown by opacity, and [hidden] cannot be overridden from CSS. Tailwind
   declares it inside @layer base with !important, and for !important
   declarations layer order is reversed, so a layered rule outranks an
   unlayered one no matter how specific. */
function syncMenuSemantics() {
    const collapsed = railIsCollapsed();

    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));

        if (collapsed) {
            button.setAttribute('aria-haspopup', 'true');
            button.setAttribute('aria-expanded', 'false');

            if (panel && !panel.dataset.disclosureHidden) {
                // Remember the disclosure state so expanding restores it.
                panel.dataset.disclosureHidden = String(panel.hidden);
                panel.hidden = false;
            }
        } else {
            button.removeAttribute('aria-haspopup');

            if (panel && panel.dataset.disclosureHidden !== undefined) {
                panel.hidden = panel.dataset.disclosureHidden === 'true';
                delete panel.dataset.disclosureHidden;
            }

            // Back to a disclosure: the attribute must match the panel again.
            button.setAttribute('aria-expanded', String(panel ? !panel.hidden : false));
        }
    });
}

/* Permission matrix: bulk toggles.
   The checkboxes are a convenience only — the server validates every
   submitted permission against the catalogue, so nothing here is a guard. */
function initPermissionMatrix() {
    const matrix = document.querySelector('[data-permission-matrix]');

    if (!matrix) {
        return;
    }

    const boxesIn = (scope) => Array.from(scope.querySelectorAll('[data-matrix-checkbox]:not(:disabled)'));

    const syncRow = (row) => {
        const boxes = boxesIn(row);
        const toggle = row.querySelector('[data-matrix-row-toggle]');

        if (!toggle || boxes.length === 0) {
            return;
        }

        const checked = boxes.filter((box) => box.checked).length;
        toggle.checked = checked === boxes.length;
        toggle.indeterminate = checked > 0 && checked < boxes.length;
    };

    matrix.querySelectorAll('[data-matrix-row]').forEach((row) => {
        syncRow(row);

        row.querySelector('[data-matrix-row-toggle]')?.addEventListener('change', (event) => {
            boxesIn(row).forEach((box) => {
                box.checked = event.target.checked;
            });
            syncRow(row);
        });

        boxesIn(row).forEach((box) => box.addEventListener('change', () => syncRow(row)));
    });

    matrix.querySelectorAll('[data-matrix-all]').forEach((button) => {
        button.addEventListener('click', () => {
            const checked = button.dataset.matrixAll === '1';
            boxesIn(matrix).forEach((box) => {
                box.checked = checked;
            });
            matrix.querySelectorAll('[data-matrix-row]').forEach(syncRow);
        });
    });
}

/* Collapsible sidebar (desktop).
   The pre-paint script sets the flag on <html> because <body> does not exist
   yet; this mirrors it onto the shell and handles toggling. */
function initSidebarCollapse() {
    const shell = document.body;
    const button = document.querySelector('[data-sidebar-collapse]');

    const collapsed = document.documentElement.dataset.sidebarCollapsed === 'true';
    shell.dataset.sidebarCollapsed = String(collapsed);

    if (!button) {
        return;
    }

    const label = button.querySelector('[data-sidebar-collapse-label]');
    const chevron = button.querySelector('[data-sidebar-collapse-chevron]');

    const sync = (isCollapsed) => {
        shell.dataset.sidebarCollapsed = String(isCollapsed);
        button.setAttribute('aria-expanded', String(!isCollapsed));

        const text = isCollapsed ? 'Lebarkan navigasi' : 'Ciutkan navigasi';
        if (label) label.textContent = text;
        button.title = `${text} (Ctrl+B)`;
        // The chevron points where the rail will go next.
        chevron?.classList.toggle('rotate-180', isCollapsed);
    };

    sync(collapsed);

    const toggle = () => {
        const next = shell.dataset.sidebarCollapsed !== 'true';

        try {
            localStorage.setItem('admin-sidebar', next ? 'collapsed' : 'expanded');
        } catch {
            /* Preference will not persist; the page still honours the toggle. */
        }

        sync(next);
        syncMenuSemantics();
    };

    button.addEventListener('click', toggle);

    // Ctrl/Cmd+B, the shortcut the reference layout uses. Ignored while typing,
    // so it never swallows a keystroke meant for a field.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'b' || !(event.ctrlKey || event.metaKey)) {
            return;
        }

        const target = event.target;
        if (target instanceof HTMLElement && target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }

        event.preventDefault();
        toggle();
    });
}

/* Icon picker preview.
   The glyphs are fetched once as a name-to-SVG map exposed by the page, so
   changing the selection redraws without a request. */
function initIconSelects() {
    const selects = document.querySelectorAll('[data-icon-select]');

    if (selects.length === 0) {
        return;
    }

    selects.forEach((select) => {
        const preview = document.querySelector(`[data-icon-preview-for="${select.id}"]`);

        if (!preview) {
            return;
        }

        select.addEventListener('change', async () => {
            const name = select.value || 'circle';
            const svg = preview.querySelector('svg');

            if (!svg) return;

            try {
                const response = await fetch(`${preview.dataset.iconEndpoint ?? '/admin/icons'}/${encodeURIComponent(name)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) return;

                const { body } = await response.json();
                svg.innerHTML = body ?? '';
            } catch {
                /* Preview stays on the previous glyph; the value is still set. */
            }
        });
    });
}

/* Collapsed-rail flyouts.
   CSS reveals them on hover and focus; their position has to come from here.
   They are fixed rather than absolute so the scrolling <nav> cannot clip them,
   and a fixed box has no anchor of its own — the coordinates are taken from
   the row it belongs to, every time it opens. */
function initFlyouts() {
    const sidebar = document.querySelector('[data-sidebar]');

    if (!sidebar) {
        return;
    }

    const collapsed = () =>
        document.body.dataset.sidebarCollapsed === 'true' && window.matchMedia('(min-width: 768px)').matches;

    const MARGIN = 8;

    const place = (node) => {
        const panel = node.querySelector(':scope > .sidebar-flyout');

        if (!panel || !collapsed()) {
            return;
        }

        const row = node.getBoundingClientRect();
        const rail = sidebar.getBoundingClientRect();

        // Where does (0,0) actually land? The rail carries a `translate`, which
        // makes it the containing block for fixed descendants, so the panel's
        // coordinates are not necessarily viewport coordinates. Measuring the
        // origin instead of assuming it keeps this correct either way.
        panel.style.left = '0px';
        panel.style.top = '0px';
        const origin = panel.getBoundingClientRect();

        // Flip to the left of the rail if the panel would run off the screen.
        const width = panel.offsetWidth;
        const fitsRight = rail.right + width + MARGIN <= window.innerWidth;
        const targetLeft = fitsRight ? rail.right : Math.max(MARGIN, rail.left - width);

        // Nudge up when a row near the bottom would push the panel off-screen.
        const height = panel.offsetHeight;
        const targetTop = Math.min(row.top, Math.max(MARGIN, window.innerHeight - height - MARGIN));

        panel.style.left = `${targetLeft - origin.left}px`;
        panel.style.top = `${targetTop - origin.top}px`;
    };

    sidebar.querySelectorAll('.sidebar-node').forEach((node) => {
        node.addEventListener('pointerenter', () => place(node));
        node.addEventListener('focusin', () => place(node));
    });

    // A rail that changes width or a page that scrolls moves every anchor, so
    // whatever is open is repositioned rather than left floating.
    const replaceOpen = () => {
        const open = sidebar.querySelector('.sidebar-node:hover, .sidebar-node:focus-within');
        if (open) place(open);
    };

    window.addEventListener('resize', replaceOpen);
    window.addEventListener('scroll', replaceOpen, { passive: true });
    sidebar.addEventListener('scroll', replaceOpen, { passive: true, capture: true });

    /* Dismissal. WCAG 1.4.13 asks for a way to close hover/focus content
       without moving the pointer or the focus, so Escape suppresses the panels
       until either actually moves. */
    const release = () => delete sidebar.dataset.flyoutSuppressed;

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.contains(document.activeElement)) {
            sidebar.dataset.flyoutSuppressed = 'true';
        }
    });

    sidebar.addEventListener('pointermove', release);
    sidebar.addEventListener('focusin', release);
    sidebar.addEventListener('pointerleave', release);
}

/* Theme toggle.
   The class is already set before first paint by an inline script in the
   layout; this only handles switching and remembering the choice. */
function initTheme() {
    const button = document.querySelector('[data-theme-toggle]');

    if (!button) {
        return;
    }

    const light = button.querySelector('[data-theme-icon-light]');
    const dark = button.querySelector('[data-theme-icon-dark]');
    const label = button.querySelector('[data-theme-label]');

    const sync = (isDark) => {
        button.setAttribute('aria-pressed', String(isDark));
        if (light) light.hidden = isDark;
        if (dark) dark.hidden = !isDark;
        // The label names the destination, not the current state, so a screen
        // reader user hears what pressing it will do.
        if (label) label.textContent = isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
    };

    sync(document.documentElement.classList.contains('dark'));

    button.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');

        try {
            localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
        } catch {
            /* Preferences will not persist; the page still honours the click. */
        }

        sync(isDark);
    });
}

/* Dismissible flash messages. */
/**
 * Keeps a colour field's swatch and its hex box showing the same value.
 *
 * The text box is the authority — it is what submits and what a keyboard user
 * types into — so the swatch only ever follows it, and only pushes back when
 * someone actually uses the picker.
 */
function initColorFields() {
    document.querySelectorAll('[data-color-field]').forEach((field) => {
        const picker = field.querySelector('[data-color-picker]');
        const text = field.querySelector('[data-color-text]');
        if (!picker || !text) return;

        picker.addEventListener('input', () => {
            text.value = picker.value;
            text.dispatchEvent(new Event('input', { bubbles: true }));
        });

        text.addEventListener('input', () => {
            // Ignore half-typed values; the picker cannot represent them and
            // would snap to black, which looks like the field was cleared.
            if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(text.value)) picker.value = text.value;
        });
    });
}

/**
 * Keeps the AI model list to the chosen provider's own models.
 *
 * The page renders every provider's models so the form still works with no
 * JavaScript — the server refuses a model that does not belong to the chosen
 * provider either way. This only hides the ones that would be refused.
 */
function initAiSettings() {
    const provider = document.querySelector('[data-ai-provider]');
    const model = document.querySelector('[data-ai-model]');
    if (!provider || !model) return;

    const options = [...model.options];

    const sync = () => {
        const chosen = provider.value;
        let first = null;

        for (const option of options) {
            const mine = option.dataset.provider === chosen;
            option.hidden = !mine;
            option.disabled = !mine;
            if (mine && !first) first = option;
        }

        // Switching provider leaves the old model selected and refused on save,
        // so the first of the new set is chosen instead.
        if (model.selectedOptions[0]?.dataset.provider !== chosen && first) {
            model.value = first.value;
        }

        // A model on this server has nobody to authenticate to.
        const local = chosen === 'ollama';
        document.querySelector('[data-ai-key]')?.toggleAttribute('hidden', local);

        document.querySelectorAll('[data-ai-doc]').forEach((link) => {
            link.hidden = link.dataset.aiDoc !== chosen;
        });
    };

    provider.addEventListener('change', sync);
    sync();
}

/**
 * Opens a transcript at its newest message.
 *
 * A chat is read from the bottom: landing at the first "halo" of a long
 * conversation means scrolling past everything to reach what just happened.
 * Jumped rather than smoothly scrolled, because animating to the end of a long
 * list is motion nobody asked for.
 */
function initTranscript() {
    const log = document.querySelector('[data-transcript]');
    if (!log) return;

    log.scrollTop = log.scrollHeight;
}

function initDismiss() {
    document.querySelectorAll('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('[data-dismissable]')?.remove());
    });
}

import { initFlowEditor } from './flow-editor';

document.addEventListener('DOMContentLoaded', () => {
    initDataTables();
    initTheme();
    initIconSelects();
    initSidebarCollapse();
    initFlyouts();
    initSidebar();
    initMenuGroups();
    initPermissionMatrix();
    initSortable();
    initColorFields();
    initFlowEditor();
    initDataSourcePreview();
    initAiSettings();
    initTranscript();
    initLightbox();
    initDismiss();

    // After the groups are wired, so it can correct what they set up.
    syncMenuSemantics();
    window.addEventListener('resize', syncMenuSemantics);
});


/**
 * Live preview for a conversation data source.
 *
 * Rendered by the server through the same lister the bot uses, so what an
 * administrator reads here is what will actually be sent. A preview built from
 * a second rendering path is a preview that can quietly disagree with reality.
 */
function initDataSourcePreview() {
    const root = document.querySelector('[data-source-editor]');
    if (!root) return;

    const list = root.querySelector('[data-preview-list]');
    const detail = root.querySelector('[data-preview-detail]');
    const status = root.querySelector('[data-preview-status]');
    const button = root.querySelector('[data-preview-refresh]');

    const load = async () => {
        status.textContent = 'Memuat…';

        const payload = {
            name: root.querySelector('[name="name"]').value,
            source: root.querySelector('[name="source"]').value,
            limit: Number(root.querySelector('[name="limit"]').value || 5),
            list_template: root.querySelector('[name="list_template"]').value,
            detail_template: root.querySelector('[name="detail_template"]').value,
        };

        try {
            const response = await fetch(root.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json();

            if (!response.ok) {
                status.textContent = Object.values(body.errors ?? {})[0]?.[0] ?? 'Pratinjau gagal dimuat.';
                return;
            }

            list.textContent = body.list;
            detail.textContent = body.detail || '—';
            status.textContent = body.empty
                ? 'Sumber ini belum punya isi yang terbit.'
                : 'Pratinjau dimuat dari isi situs saat ini.';
        } catch (error) {
            status.textContent = 'Pratinjau gagal dimuat: ' + error.message;
        }
    };

    button?.addEventListener('click', load);
    load();
}

/**
 * Opens image links in place instead of a new tab.
 *
 * Every link marked [data-lightbox] on a page shares one dialog and one group,
 * so the arrows step through the photographs of a complaint without closing
 * and reopening. Opening in a new tab loses the record you were reading and
 * leaves a bare image with nothing but the browser's back button.
 */
function initLightbox() {
    const dialog = document.querySelector('[data-lightbox-dialog]');
    const links = [...document.querySelectorAll('[data-lightbox]')];

    if (!dialog || !links.length) return;

    const image = dialog.querySelector('[data-lightbox-image]');
    const title = dialog.querySelector('[data-lightbox-title]');
    const counter = dialog.querySelector('[data-lightbox-counter]');
    const prev = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    const openFull = dialog.querySelector('[data-lightbox-open]');

    let index = 0;
    let opener = null;

    const show = (i) => {
        index = (i + links.length) % links.length;
        const link = links[index];

        image.src = link.href;
        image.alt = link.dataset.lightbox || link.querySelector('img')?.alt || 'Lampiran';
        title.textContent = image.alt;
        openFull.href = link.href;

        // A single image needs no stepping controls and no "1 / 1".
        const many = links.length > 1;
        counter.textContent = many ? `${index + 1} / ${links.length}` : '';
        prev.hidden = next.hidden = !many;
    };

    links.forEach((link, i) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            opener = link;
            show(i);
            dialog.showModal();
        });
    });

    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());

    // Clicking the backdrop. The dialog fills the viewport, so a click landing
    // on it rather than on its panel is outside.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('keydown', (event) => {
        if (links.length < 2) return;
        if (event.key === 'ArrowRight') { show(index + 1); event.preventDefault(); }
        if (event.key === 'ArrowLeft') { show(index - 1); event.preventDefault(); }
    });

    // Escape and the close button both land here; focus goes back to the
    // thumbnail that was clicked, not to the top of the document.
    dialog.addEventListener('close', () => {
        opener?.focus();
        opener = null;
    });
}
