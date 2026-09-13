/**
 * Public accessibility controls.
 *
 * Each feature is independent, remembered per visitor in localStorage, and
 * only rendered when an administrator has enabled it. Nothing here depends on
 * a network request, so the controls keep working on a slow connection.
 */

const STORAGE_KEY = 'a11y';
const SCALE_MIN = 0.875;
const SCALE_MAX = 1.75;
const SCALE_STEP = 0.125;

const DEFAULTS = {
    scale: 1,
    vision: 'normal',
    highlightLinks: false,
    highlightInteractive: false,
    reduceMotion: false,
};

function load() {
    try {
        return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') };
    } catch {
        // Private mode, blocked storage, corrupt value: defaults still work.
        return { ...DEFAULTS };
    }
}

function save(prefs) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch {
        /* Preferences simply will not persist; the page still honours them. */
    }
}

function apply(prefs) {
    const root = document.documentElement;

    root.style.setProperty('--a11y-scale', String(prefs.scale));

    if (prefs.vision && prefs.vision !== 'normal') {
        root.dataset.vision = prefs.vision;
    } else {
        delete root.dataset.vision;
    }

    for (const [key, attribute] of [
        ['highlightLinks', 'highlightLinks'],
        ['highlightInteractive', 'highlightInteractive'],
        ['reduceMotion', 'reduceMotion'],
    ]) {
        if (prefs[key]) {
            root.dataset[attribute] = 'true';
        } else {
            delete root.dataset[attribute];
        }
    }
}

/* ------------------------------------------------------------------ panel */

function initPanel(state) {
    const root = document.querySelector('[data-a11y-root]');

    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-a11y-toggle]');
    const panel = root.querySelector('#a11y-panel');
    const close = root.querySelector('[data-a11y-close]');

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));

        if (open) {
            panel.querySelector('button, input')?.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close?.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !root.contains(event.target)) {
            setOpen(false);
        }
    });

    initTextSize(root, state);
    initVision(root, state);
    initOptions(root, state);
    initSpeech(root);

    root.querySelector('[data-a11y-reset]')?.addEventListener('click', () => {
        Object.assign(state.prefs, DEFAULTS);
        save(state.prefs);
        apply(state.prefs);
        syncControls(root, state.prefs);
        window.speechSynthesis?.cancel();
    });

    syncControls(root, state.prefs);
}

function syncControls(root, prefs) {
    const scaleStatus = root.querySelector('[data-a11y-scale-status]');

    if (scaleStatus) {
        scaleStatus.textContent = `Ukuran teks ${Math.round(prefs.scale * 100)}%`;
    }

    root.querySelectorAll('[data-a11y-vision]').forEach((input) => {
        input.checked = input.value === prefs.vision;
    });

    root.querySelectorAll('[data-a11y-option]').forEach((input) => {
        input.checked = Boolean(prefs[input.dataset.a11yOption]);
    });
}

function initTextSize(root, state) {
    root.querySelectorAll('[data-a11y-text]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.a11yText;
            const current = state.prefs.scale;

            state.prefs.scale =
                action === 'reset'
                    ? DEFAULTS.scale
                    : Math.min(SCALE_MAX, Math.max(SCALE_MIN, current + (action === 'up' ? SCALE_STEP : -SCALE_STEP)));

            save(state.prefs);
            apply(state.prefs);
            syncControls(root, state.prefs);
        });
    });
}

function initVision(root, state) {
    root.querySelectorAll('[data-a11y-vision]').forEach((input) => {
        input.addEventListener('change', () => {
            state.prefs.vision = input.value;
            save(state.prefs);
            apply(state.prefs);
        });
    });
}

function initOptions(root, state) {
    root.querySelectorAll('[data-a11y-option]').forEach((input) => {
        input.addEventListener('change', () => {
            state.prefs[input.dataset.a11yOption] = input.checked;
            save(state.prefs);
            apply(state.prefs);
        });
    });
}

/* ----------------------------------------------------------------- speech */

function initSpeech(root) {
    const section = root.querySelector('[data-a11y-speech]');

    if (!section) {
        return;
    }

    const controls = section.querySelector('[data-a11y-speech-controls]');
    const unsupported = section.querySelector('[data-a11y-speech-unsupported]');
    const status = section.querySelector('[data-a11y-speech-status]');

    // Feature detection rather than browser sniffing: say plainly that it is
    // unavailable instead of showing buttons that do nothing.
    if (!('speechSynthesis' in window) || typeof window.SpeechSynthesisUtterance !== 'function') {
        unsupported.hidden = false;
        return;
    }

    controls.hidden = false;

    const announce = (message) => {
        if (status) status.textContent = message;
    };

    const speak = (text) => {
        const trimmed = text.replace(/\s+/g, ' ').trim();

        if (!trimmed) {
            announce('Tidak ada teks untuk dibaca.');
            return;
        }

        window.speechSynthesis.cancel();

        const utterance = new SpeechSynthesisUtterance(trimmed);
        utterance.lang = document.documentElement.lang || 'id-ID';
        utterance.onend = () => announce('Selesai membaca.');
        utterance.onerror = () => announce('Pembacaan gagal dimulai.');

        window.speechSynthesis.speak(utterance);
        announce('Sedang membaca…');
    };

    section.querySelectorAll('[data-a11y-speak]').forEach((button) => {
        button.addEventListener('click', () => {
            switch (button.dataset.a11ySpeak) {
                case 'page': {
                    // Only the main region: reading the navigation and footer
                    // aloud on every page is noise, not help.
                    const main = document.getElementById('main-content');
                    speak(main?.innerText ?? document.body.innerText);
                    break;
                }
                case 'selection':
                    speak(window.getSelection()?.toString() ?? '');
                    break;
                case 'pause':
                    if (window.speechSynthesis.paused) {
                        window.speechSynthesis.resume();
                        announce('Dilanjutkan.');
                    } else if (window.speechSynthesis.speaking) {
                        window.speechSynthesis.pause();
                        announce('Dijeda.');
                    }
                    break;
                case 'stop':
                    window.speechSynthesis.cancel();
                    announce('Dihentikan.');
                    break;
            }
        });
    });

    // Speech keeps running after navigation in some browsers; stop it so the
    // next page does not inherit the previous one's voice.
    window.addEventListener('beforeunload', () => window.speechSynthesis.cancel());
}

/* ------------------------------------------------------------ navigation */

function initNavigation() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.getElementById('mobile-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.hidden;
            nav.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    // Disclosure groups, used by both the mobile nav and the desktop dropdowns.
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));

        if (!panel) return;

        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
            button.querySelector('[data-menu-chevron]')?.classList.toggle('rotate-90', !expanded);
        });
    });

    initDropdowns();
}

/** Desktop dropdowns: hover to preview, click/Enter to hold open, Escape to close. */
function initDropdowns() {
    const dropdowns = Array.from(document.querySelectorAll('[data-nav-dropdown]'));

    const closeAll = (except = null) => {
        dropdowns.forEach((dropdown) => {
            if (dropdown === except) return;
            const button = dropdown.querySelector(':scope > button');
            const panel = dropdown.querySelector(':scope > ul');
            if (button && panel) {
                button.setAttribute('aria-expanded', 'false');
                panel.hidden = true;
            }
        });
    };

    dropdowns.forEach((dropdown) => {
        const button = dropdown.querySelector(':scope > button');
        const panel = dropdown.querySelector(':scope > ul');

        if (!button || !panel) return;

        const setOpen = (open) => {
            button.setAttribute('aria-expanded', String(open));
            panel.hidden = !open;
        };

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = panel.hidden;
            closeAll(dropdown);
            setOpen(open);
        });

        dropdown.addEventListener('mouseenter', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                closeAll(dropdown);
                setOpen(true);
            }
        });

        dropdown.addEventListener('mouseleave', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                setOpen(false);
            }
        });

        // Leaving the group entirely by keyboard closes it, so the panel does
        // not stay open behind the focus ring.
        dropdown.addEventListener('focusout', (event) => {
            if (!dropdown.contains(event.relatedTarget)) {
                setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });

    document.addEventListener('click', () => closeAll());
}

/* -------------------------------------------------------------- carousel */

/**
 * Manually operated carousel. It never advances on its own: motion a visitor
 * cannot stop is a WCAG 2.2.2 failure, and an auto-rotating banner is the
 * usual way that rule gets broken.
 */
function initCarousels() {
    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
        const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));

        if (slides.length < 2) {
            return;
        }

        let index = 0;

        const show = (next) => {
            index = (next + slides.length) % slides.length;

            slides.forEach((slide, i) => {
                slide.hidden = i !== index;
            });

            dots.forEach((dot, i) => dot.setAttribute('aria-selected', String(i === index)));
        };

        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => show(index - 1));
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => show(index + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));

        carousel.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                show(index - 1);
            } else if (event.key === 'ArrowRight') {
                show(index + 1);
            }
        });
    });
}

/* ------------------------------------------------------------- accordions */

function initAccordions() {
    document.querySelectorAll('[data-accordion-toggle]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));

        if (!panel) return;

        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
            button.querySelector('[data-accordion-icon]')?.classList.toggle('rotate-180', !expanded);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const state = { prefs: load() };
    apply(state.prefs);
    initPanel(state);
    initNavigation();
    initCarousels();
    initAccordions();
    initCardRows();
});

/* Horizontally scrolling card rows.
   The scroller is a native overflow container, so touch momentum, the arrow
   keys and Tab all keep working; these buttons only nudge it, and disable
   themselves at each end so they never look operable when they are not. */
function initCardRows() {
    document.querySelectorAll('[data-card-row]').forEach((row) => {
        const track = row.querySelector('[data-row-track]');
        const prev = row.querySelector('[data-row-prev]');
        const next = row.querySelector('[data-row-next]');

        if (!track || !prev || !next) {
            return;
        }

        const sync = () => {
            const max = track.scrollWidth - track.clientWidth;
            prev.disabled = track.scrollLeft <= 1;
            next.disabled = track.scrollLeft >= max - 1;
        };

        // One card plus its gap, so a nudge lands on a card edge.
        const step = () => track.firstElementChild?.getBoundingClientRect().width + 20 || 300;

        prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
        next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));

        track.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        sync();
    });
}
