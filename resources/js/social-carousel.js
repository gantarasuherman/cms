/**
 * Carousel and caption clamp for a republished social post.
 *
 * Everything here is an enhancement over markup that already works:
 *
 *   - The track is a scroll-snap row, so swiping and shift-scrolling work with
 *     no script at all. This adds arrows, dots and a counter, and each of
 *     those is revealed only once it has something to drive — a button that
 *     scrolls nothing would be a dead control.
 *   - The caption renders in full by default and is clamped here, because a
 *     "selengkapnya" that cannot expand hides text permanently.
 *
 * Position is read back from the element's own scrollLeft rather than tracked
 * in a variable: a swipe, a scrollbar drag and an arrow press then all agree,
 * and there is no state to drift.
 */

function initCarousel(root) {
    const track = root.querySelector('[data-ig-track]');
    const slides = Array.from(root.querySelectorAll('[data-ig-slide]'));

    if (!track || slides.length < 2) {
        return;
    }

    const prev = root.querySelector('[data-ig-prev]');
    const next = root.querySelector('[data-ig-next]');
    const counter = root.querySelector('[data-ig-counter]');
    // The dots live outside the media box, so they are looked up from the card.
    const dots = Array.from(root.closest('.ig-post')?.querySelectorAll('[data-ig-dot]') ?? []);

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const indexOf = () => {
        const width = track.clientWidth || 1;
        return Math.min(slides.length - 1, Math.max(0, Math.round(track.scrollLeft / width)));
    };

    const goTo = (index) => {
        const target = Math.min(slides.length - 1, Math.max(0, index));
        track.scrollTo({ left: target * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
    };

    const paint = () => {
        const index = indexOf();

        if (counter) {
            counter.textContent = `${index + 1}/${slides.length}`;
        }

        dots.forEach((dot, i) => dot.classList.toggle('is-on', i === index));

        // Disabled rather than hidden: Instagram fades the arrow away at each
        // end, and `[disabled]` already takes it out of the tab order.
        if (prev) prev.disabled = index === 0;
        if (next) next.disabled = index === slides.length - 1;
    };

    [prev, next].forEach((button) => button && (button.hidden = false));
    prev?.addEventListener('click', () => goTo(indexOf() - 1));
    next?.addEventListener('click', () => goTo(indexOf() + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => goTo(Number(dot.dataset.igDot))));

    // Arrow keys, once the track itself has focus — the same keys a native
    // scroller answers, so nothing is stolen from the page.
    track.tabIndex = 0;
    track.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
            return;
        }

        event.preventDefault();
        goTo(indexOf() + (event.key === 'ArrowRight' ? 1 : -1));
    });

    let queued = null;
    track.addEventListener('scroll', () => {
        // One paint per frame: a smooth scroll fires this dozens of times.
        if (queued === null) {
            queued = requestAnimationFrame(() => {
                queued = null;
                paint();
            });
        }
    }, { passive: true });

    paint();
}

function initCaption(caption) {
    const toggle = caption.parentElement?.querySelector('[data-ig-more]');

    if (!toggle) {
        return;
    }

    caption.classList.add('is-clamped');

    // Measured after clamping: a caption that fits in two lines has nothing to
    // reveal, and the button would do nothing.
    if (caption.scrollHeight <= caption.clientHeight + 1) {
        caption.classList.remove('is-clamped');

        return;
    }

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const clamped = caption.classList.toggle('is-clamped');
        toggle.textContent = clamped ? 'selengkapnya' : 'lebih sedikit';
    });
}

export function initSocialPosts(scope = document) {
    scope.querySelectorAll('[data-ig-carousel]').forEach(initCarousel);
    scope.querySelectorAll('[data-ig-caption]').forEach(initCaption);
}
