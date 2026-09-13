/**
 * The announcement strip and its modal.
 *
 * One record has two surfaces: a line in the strip above the header, and a
 * modal with the whole notice. Dismissing the modal does not remove the
 * announcement — it leaves it in the strip, which is where it can be reopened.
 */

const STORAGE_KEY = 'announcement-seen';
const DWELL = 4500;   // how long a line stays still
const TRAVEL = 600;   // how long it takes to slide out of the way

const reduceMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
    || document.documentElement.dataset.reduceMotion === 'true';

class Announcements {
    constructor(root) {
        this.root = root;
        this.signature = root.dataset.signature ?? '';
        this.modal = root.querySelector('[data-announcement-modal]');
        this.panels = [...root.querySelectorAll('[data-announcement-panel]')];
        this.items = [...root.querySelectorAll('[data-ticker-item]')];
        this.track = root.querySelector('[data-ticker-track]');
        this.toggle = root.querySelector('[data-ticker-toggle]');

        this.index = 0;
        this.stopped = false;    // the visitor pressed pause
        this.suspended = false;  // the pointer or focus is resting on the strip
        this.timer = null;
        this.opener = null;

        this.wireTicker();
        this.wireModal();
        this.maybeOpen();
    }

    /* ------------------------------------------------------------- ticker */

    wireTicker() {
        if (this.items.length < 2) return;

        this.height = this.items[0].offsetHeight;
        this.track.style.transition = `transform ${TRAVEL}ms cubic-bezier(0.4, 0, 0.2, 1)`;

        // Reduced motion means no self-starting movement at all, not merely a
        // gentler one. The lines are then all reachable by Tab instead.
        if (reduceMotion()) {
            this.showAll();
            this.toggle?.remove();
            return;
        }

        this.toggle?.addEventListener('click', () => this.setStopped(!this.stopped));

        const strip = this.root.querySelector('[data-ticker]');
        strip.addEventListener('mouseenter', () => this.suspend(true));
        strip.addEventListener('mouseleave', () => this.suspend(false));
        strip.addEventListener('focusin', () => this.suspend(true));
        strip.addEventListener('focusout', () => this.suspend(false));

        // A strip that keeps advancing in a hidden tab is wasted work, and it
        // would also be several lines further on when the visitor returns.
        document.addEventListener('visibilitychange', () => this.schedule());

        this.schedule();
    }

    showAll() {
        this.track.style.position = 'static';
        this.items.forEach((item) => {
            item.removeAttribute('aria-hidden');
            item.querySelector('[data-announcement-open]')?.removeAttribute('tabindex');
        });
        this.root.querySelector('[data-ticker] .overflow-hidden')?.classList.remove('h-11', 'overflow-hidden');
    }

    schedule() {
        clearTimeout(this.timer);

        if (this.stopped || this.suspended || document.hidden || this.items.length < 2) return;

        this.timer = setTimeout(() => this.advance(), DWELL);
    }

    advance() {
        this.show((this.index + 1) % this.items.length);
        this.schedule();
    }

    show(next) {
        this.index = next;
        this.track.style.transform = `translateY(-${next * this.height}px)`;

        this.items.forEach((item, i) => {
            const current = i === next;
            item.toggleAttribute('aria-hidden', !current);

            // A line that has scrolled out of the window must leave the tab
            // order with it, or Tab lands on something nobody can see.
            const button = item.querySelector('[data-announcement-open]');
            if (button) current ? button.removeAttribute('tabindex') : button.setAttribute('tabindex', '-1');
        });
    }

    suspend(state) {
        this.suspended = state;
        this.schedule();
    }

    setStopped(state) {
        this.stopped = state;
        this.toggle.setAttribute('aria-pressed', String(state));
        this.toggle.querySelector('[data-ticker-toggle-label]').textContent =
            state ? 'Lanjutkan teks berjalan' : 'Hentikan teks berjalan';
        this.toggle.querySelector('[data-ticker-icon-pause]').hidden = state;
        this.toggle.querySelector('[data-ticker-icon-play]').hidden = !state;
        this.schedule();
    }

    /* -------------------------------------------------------------- modal */

    wireModal() {
        if (!this.modal) return;

        this.root.querySelectorAll('[data-announcement-open]').forEach((button) => {
            button.addEventListener('click', () => this.open(Number(button.dataset.announcementOpen), button));
        });

        this.modal.querySelector('[data-announcement-close]')?.addEventListener('click', () => this.modal.close());

        // Clicking the backdrop. The dialog element itself fills the whole
        // viewport, so a click landing on it rather than on a panel is outside.
        this.modal.addEventListener('click', (event) => {
            if (event.target === this.modal) this.modal.close();
        });

        this.modal.addEventListener('close', () => {
            this.remember();
            // Escape and the close button both land here; focus must go back
            // to whatever opened the dialog, not to the top of the document.
            // Not while navigating away, though — there is nothing left to
            // return focus to.
            if (!this.navigating) this.opener?.focus();
            this.opener = null;
        });

        // Following the call to action is as much an answer as closing it.
        // Without this the dialog is still open behind the new page while it
        // loads, and — because `close` never fired — it opens again on arrival.
        this.modal.querySelectorAll('[data-announcement-action]').forEach((link) => {
            link.addEventListener('click', () => {
                this.navigating = true;
                this.modal.close();
            });
        });

        this.modal.querySelectorAll('[data-announcement-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const note = button.parentElement.querySelector('[data-announcement-copied]');
                try {
                    await navigator.clipboard.writeText(button.dataset.announcementCopy);
                    note.textContent = 'Kode disalin.';
                } catch {
                    // Clipboard access can be refused; select it instead so the
                    // visitor can copy it by hand rather than be told nothing.
                    note.textContent = 'Salin manual: ' + button.dataset.announcementCopy;
                }
                setTimeout(() => { note.textContent = ''; }, 4000);
            });
        });
    }

    open(index, opener = null) {
        this.opener = opener;
        this.panels.forEach((panel, i) => { panel.hidden = i !== index; });

        // The dialog's name has to follow the panel on show, not stay pinned
        // to the first one.
        const heading = this.panels[index]?.querySelector('[data-announcement-heading]');
        if (heading?.id) this.modal.setAttribute('aria-labelledby', heading.id);

        // showModal() places focus on the [autofocus] close button. Moving it
        // onto the heading instead would paint the focus ring across the title.
        this.modal.showModal();
    }

    /* ------------------------------------------------------------ dismissal */

    maybeOpen() {
        if (!this.modal || !this.panels.length) return;

        if (this.root.hasAttribute('data-force')) {
            this.open(0);
            return;
        }

        if (this.seen()) return;

        this.open(0);
    }

    seen() {
        try {
            return localStorage.getItem(STORAGE_KEY) === this.signature;
        } catch {
            // Storage can be unavailable. Showing the modal every visit is a
            // nuisance; showing it never would hide the notice — so err toward
            // treating it as already seen and leave the strip to carry it.
            return true;
        }
    }

    remember() {
        try {
            localStorage.setItem(STORAGE_KEY, this.signature);
        } catch { /* nothing to do: the strip still shows the notice */ }
    }
}

export function initAnnouncements() {
    document.querySelectorAll('[data-announcements]').forEach((root) => new Announcements(root));
}
