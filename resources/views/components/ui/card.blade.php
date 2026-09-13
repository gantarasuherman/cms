@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card text-card-foreground shadow-sm']) }}>
    @if ($title)
        <header class="border-b border-border px-5 py-4">
            <h3 class="text-sm font-semibold leading-none tracking-tight">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1.5 text-sm text-muted-foreground">{{ $description }}</p>
            @endif
        </header>
    @endif

    <div class="p-5">{{ $slot }}</div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
            {{ $footer }}
        </footer>
    @endisset
</section>
