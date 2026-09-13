/**
 * Hero slider.
 *
 * Fade between slides with a small lift on the copy, a numeric counter and a
 * per-slide progress bar. Everything the reference asks for, with three rules
 * that shape the implementation:
 *
 *   - Autoplay must be stoppable by a real control, not only by hovering
 *     (WCAG 2.2.2). The pause button is that control, and its state persists.
 *   - Reduced motion is honoured: no transitions, no progress animation, and
 *     autoplay does not start at all.
 *   - The live region stays silent while it plays itself and only speaks after
 *     a manual change, so an automatic slide never interrupts a screen reader.
 */

const AUTOPLAY_DEFAULT = 6000;
const SWIPE_THRESHOLD = 45;

class HeroSlider {
    constructor(root) {
        this.root = root;
        this.slides = Array.from(root.querySelectorAll('[data-hero-slide]'));
        this.dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
        this.counter = root.querySelector('[data-hero-current]');
        this.status = root.querySelector('[data-hero-status]');
        this.playButton = root.querySelector('[data-hero-play]');
        this.interval = Number(root.dataset.interval || AUTOPLAY_DEFAULT);
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.index = 0;
        this.timer = null;
        this.paused = false;

        if (this.slides.length === 0) {
            return;
        }

        this.bind();
        this.show(0, { announce: false });

        if (this.slides.length > 1 && !this.reduced) {
            this.play();
        } else if (this.playButton) {
            // Nothing rotates, so a pause control would be a lie.
            this.playButton.hidden = true;
        }
    }

    bind() {
        this.root.querySelector('[data-hero-prev]')?.addEventListener('click', () => this.step(-1));
        this.root.querySelector('[data-hero-next]')?.addEventListener('click', () => this.step(1));

        this.dots.forEach((dot, i) =>
            dot.addEventListener('click', () => this.show(i, { manual: true })),
        );

        this.playButton?.addEventListener('click', () => (this.paused ? this.play() : this.pause({ byUser: true })));

        // Hover and focus suspend playback without changing the pause state, so
        // moving away resumes only if the visitor had not stopped it.
        ['pointerenter', 'focusin'].forEach((event) =>
            this.root.addEventListener(event, () => this.suspend()),
        );
        ['pointerleave', 'focusout'].forEach((event) =>
            this.root.addEventListener(event, (e) => {
                if (e.type === 'focusout' && this.root.contains(e.relatedTarget)) return;
                this.resume();
            }),
        );

        // Arrow keys move between slides when the control has focus.
        this.root.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.step(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.step(1);
            }
        });

        this.bindSwipe();

        // A backgrounded tab should not burn through the slides unseen.
        document.addEventListener('visibilitychange', () =>
            document.hidden ? this.suspend() : this.resume(),
        );
    }

    bindSwipe() {
        let startX = null;
        let startY = null;

        this.root.addEventListener('touchstart', (event) => {
            startX = event.changedTouches[0].clientX;
            startY = event.changedTouches[0].clientY;
        }, { passive: true });

        this.root.addEventListener('touchend', (event) => {
            if (startX === null) return;

            const dx = event.changedTouches[0].clientX - startX;
            const dy = event.changedTouches[0].clientY - startY;

            // Only a clearly horizontal gesture counts, so swiping the page
            // vertically never flips a slide by accident.
            if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
                this.step(dx < 0 ? 1 : -1);
            }

            startX = null;
            startY = null;
        }, { passive: true });
    }

    step(delta) {
        this.show((this.index + delta + this.slides.length) % this.slides.length, { manual: true });
    }

    show(next, { manual = false, announce = true } = {}) {
        this.index = next;

        this.slides.forEach((slide, i) => {
            const active = i === next;
            slide.classList.toggle('opacity-100', active);
            slide.classList.toggle('opacity-0', !active);
            slide.classList.toggle('pointer-events-none', !active);
            slide.toggleAttribute('aria-hidden', !active);

            // Focus must never land inside a slide nobody can see.
            slide.querySelectorAll('a, button').forEach((el) => {
                if (active) {
                    el.removeAttribute('tabindex');
                } else {
                    el.setAttribute('tabindex', '-1');
                }
            });

            if (active) {
                this.animateCopy(slide);
            }
        });

        this.dots.forEach((dot, i) => dot.setAttribute('aria-selected', String(i === next)));

        if (this.counter) {
            this.counter.textContent = String(next + 1).padStart(2, '0');
        }

        if (manual) {
            // Manual changes are worth announcing; automatic ones are not.
            if (this.status) {
                this.status.setAttribute('aria-live', 'polite');
                this.status.textContent = `Slide ${next + 1} dari ${this.slides.length}`;
            }
            this.restart();
        }

        this.resetProgress();

        if (announce && !manual && this.status) {
            this.status.textContent = '';
        }
    }

    /** opacity 0→1 with a 15px lift, re-triggered on each change. */
    animateCopy(slide) {
        const copy = slide.querySelector('[data-hero-copy]');

        if (!copy || this.reduced) {
            return;
        }

        copy.style.transition = 'none';
        copy.style.opacity = '0';
        copy.style.transform = 'translateY(15px)';

        requestAnimationFrame(() => requestAnimationFrame(() => {
            copy.style.transition = 'opacity 600ms ease-out, transform 600ms ease-out';
            copy.style.opacity = '1';
            copy.style.transform = 'translateY(0)';
        }));
    }

    resetProgress() {
        this.dots.forEach((dot, i) => {
            const bar = dot.querySelector('[data-hero-progress]');
            if (!bar) return;

            bar.style.transition = 'none';
            bar.style.width = i < this.index ? '100%' : '0%';
        });

        if (this.reduced || this.paused || this.timer === null) {
            return;
        }

        const bar = this.dots[this.index]?.querySelector('[data-hero-progress]');

        if (bar) {
            requestAnimationFrame(() => requestAnimationFrame(() => {
                bar.style.transition = `width ${this.interval}ms linear`;
                bar.style.width = '100%';
            }));
        }
    }

    play() {
        this.paused = false;
        this.syncPlayButton();
        this.restart();
    }

    pause({ byUser = false } = {}) {
        if (byUser) {
            this.paused = true;
            this.syncPlayButton();
        }

        this.freezeProgress();
        window.clearInterval(this.timer);
        this.timer = null;
    }

    /** Hover/focus suspension: stops the timer without claiming the visitor paused it. */
    suspend() {
        if (!this.paused) {
            this.freezeProgress();
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    resume() {
        if (!this.paused && this.timer === null && !this.reduced && this.slides.length > 1) {
            this.restart();
        }
    }

    restart() {
        window.clearInterval(this.timer);

        if (this.paused || this.reduced || this.slides.length < 2) {
            this.timer = null;
            this.resetProgress();
            return;
        }

        this.timer = window.setInterval(() => this.show((this.index + 1) % this.slides.length), this.interval);
        this.resetProgress();
    }

    freezeProgress() {
        const bar = this.dots[this.index]?.querySelector('[data-hero-progress]');

        if (!bar) return;

        const width = bar.getBoundingClientRect().width;
        const track = bar.parentElement?.getBoundingClientRect().width || 1;
        bar.style.transition = 'none';
        bar.style.width = `${(width / track) * 100}%`;
    }

    syncPlayButton() {
        if (!this.playButton) return;

        this.playButton.setAttribute('aria-pressed', String(this.paused));
        this.playButton.querySelector('[data-hero-icon-pause]').hidden = this.paused;
        this.playButton.querySelector('[data-hero-icon-play]').hidden = !this.paused;

        const label = this.playButton.querySelector('[data-hero-play-label]');
        if (label) {
            label.textContent = this.paused ? 'Mulai pergantian otomatis' : 'Hentikan pergantian otomatis';
        }
    }
}

export function initHeroSliders(root = document) {
    root.querySelectorAll('[data-hero]').forEach((el) => new HeroSlider(el));
}
