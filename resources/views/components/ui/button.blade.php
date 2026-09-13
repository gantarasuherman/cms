@props([
    'variant' => 'primary',
    'href' => null,
    'icon' => null,
    'type' => 'submit',
])

@php
    // shadcn variants: a solid primary, a bordered outline, a destructive, and
    // a borderless ghost. All resolve through tokens, so they follow the theme.
    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90 border-transparent',
        'secondary' => 'border-input bg-background hover:bg-accent hover:text-accent-foreground',
        'danger' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 border-transparent',
        'ghost' => 'border-transparent bg-transparent hover:bg-accent hover:text-accent-foreground',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border px-4 py-2 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </button>
@endif
