@props(['label', 'eyebrow' => null, 'title' => null, 'subtitle' => null, 'id' => null])

@php $trackId = uniqid('row'); @endphp

{{--
    Horizontally scrolling row, as in the reference: the heading and the scroll
    controls share one line.

    Native overflow scrolling rather than a JS carousel — it keeps momentum
    scrolling on touch, the arrow keys and Tab keep working, and every card
    stays in the tab order. The buttons only nudge that same scroller, and
    disable themselves at each end so they never look operable when they are not.
--}}
<div class="relative" data-card-row>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
            @if ($eyebrow)
                <span class="sarab-eyebrow mb-2">{{ $eyebrow }}</span>
            @endif

            @if ($title)
                <h2 @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>
            @endif

            @if ($subtitle)
                <p class="sarab-lead mt-3">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <button type="button" data-row-prev aria-controls="{{ $trackId }}"
                    class="grid h-9 w-9 place-items-center rounded-full border border-[#e4e4e7] bg-white text-[#3f3f46] transition-colors hover:bg-[var(--sarab-cream)] disabled:opacity-40">
                <x-icon name="chevron-left" class="h-4 w-4" />
                <span class="sr-only">Geser {{ $label }} ke kiri</span>
            </button>
            <button type="button" data-row-next aria-controls="{{ $trackId }}"
                    class="grid h-9 w-9 place-items-center rounded-full border border-[#e4e4e7] bg-white text-[#3f3f46] transition-colors hover:bg-[var(--sarab-cream)] disabled:opacity-40">
                <x-icon name="chevron-right" class="h-4 w-4" />
                <span class="sr-only">Geser {{ $label }} ke kanan</span>
            </button>
        </div>
    </div>

    <ul id="{{ $trackId }}" data-row-track tabindex="0" aria-label="{{ $label }}"
        class="flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:thin] motion-reduce:scroll-auto">
        {{ $slot }}
    </ul>
</div>
