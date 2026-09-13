@props([
    'label',
    'value',
    'previous' => null,
    'delta' => null,
    'icon' => 'activity',
    'caption' => null,
])

@php
    // Direction is stated in words and by icon as well as colour, so the trend
    // is readable without relying on green/red (WCAG 1.4.1).
    $direction = match (true) {
        $delta === null => null,
        $delta > 0 => ['naik', 'chevron-up', 'text-success'],
        $delta < 0 => ['turun', 'chevron-down', 'text-destructive'],
        default => ['tetap', 'circle', 'text-muted-foreground'],
    };
@endphp

<div class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
        <x-icon :name="$icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
    </div>

    {{-- Proportional figures on a display-size number; tabular-nums is for
         columns that must align vertically, not for a lone headline. --}}
    <p class="mt-2 text-2xl font-bold">{{ number_format($value) }}</p>

    @if ($direction)
        @php([$word, $arrow, $tone] = $direction)
        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
            <span class="inline-flex items-center gap-0.5 font-medium {{ $tone }}">
                <x-icon :name="$arrow" class="h-3.5 w-3.5" />
                {{ $word }} {{ number_format(abs($delta), 1) }}%
            </span>
            <span class="text-muted-foreground">dibanding periode sebelumnya ({{ number_format($previous) }})</span>
        </p>
    @elseif ($previous !== null)
        <p class="mt-1 text-xs text-muted-foreground">Periode sebelumnya: {{ number_format($previous) }}</p>
    @endif

    @if ($caption)
        <p class="mt-1 text-xs text-muted-foreground">{{ $caption }}</p>
    @endif
</div>
