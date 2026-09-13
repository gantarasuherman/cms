@props([
    'href',
    'title',
    'image' => null,
    'badge' => null,
    'meta' => null,
    'note' => null,
    'icon' => 'newspaper',
])

{{--
    Card in the reference's shape: a rounded photograph with an overlay badge,
    then the title and a single meta line beneath it.

    There is deliberately no favourite/heart control. Visitors have no accounts
    here, so it would be a button that looks operable and saves nothing.
--}}
<article class="group h-full">
    <a href="{{ $href }}"
       class="block rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--sarab-primary)]">
        <div class="relative overflow-hidden rounded-2xl">
            @if ($image)
                <img src="{{ $image }}" alt="" loading="lazy"
                     class="aspect-[4/3] w-full object-cover transition-transform duration-500 group-hover:scale-[1.04] motion-reduce:transform-none">
            @else
                {{-- Not every entry has a photograph, and a faint glyph on cream
                     reads as a failed image rather than a deliberate one. This
                     is a stated placeholder: a tinted panel, a solid icon, and
                     the kind of thing it stands for. --}}
                <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-[var(--sarab-cream-2)] to-[var(--sarab-cream)]">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/80 text-[var(--sarab-primary)] shadow-sm">
                        <x-icon :name="$icon" class="h-5 w-5" />
                    </span>
                    @if ($badge)
                        <span class="text-xs font-medium text-[var(--sarab-secondary-ink)]">{{ $badge }}</span>
                    @endif
                </div>
            @endif

            @if ($badge)
                <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-[var(--sarab-dark)] shadow-sm backdrop-blur">
                    {{ $badge }}
                </span>
            @endif
        </div>

        <h3 class="mt-3 line-clamp-2 !text-base font-semibold leading-snug text-[var(--sarab-dark)] group-hover:underline">
            {{ $title }}
        </h3>
    </a>

    @if ($meta)
        <p class="mt-1 text-sm text-[#71717a]">
            {{ $meta }}
            @if ($note)
                <span aria-hidden="true"> · </span>{{ $note }}
            @endif
        </p>
    @endif
</article>
