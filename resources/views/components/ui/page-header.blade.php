@props(['title', 'description' => null])

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
