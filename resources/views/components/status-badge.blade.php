@props(['status'])

@php
    // Status is conveyed by icon and wording as well as colour (WCAG 1.4.1).
    $map = [
        'published' => ['Terbit', 'border-transparent bg-success/10 text-success', 'circle-check'],
        'draft' => ['Draf', 'border-border bg-muted text-muted-foreground', 'pencil'],
        'archived' => ['Arsip', 'border-transparent bg-warning/10 text-warning', 'archive'],
        'active' => ['Aktif', 'border-transparent bg-success/10 text-success', 'circle-check'],
        'inactive' => ['Nonaktif', 'border-border bg-muted text-muted-foreground', 'circle-slash'],
    ];
    [$label, $classes, $icon] = $map[$status] ?? [ucfirst((string) $status), 'border-border bg-muted text-muted-foreground', 'circle'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium $classes"]) }}>
    <x-icon :name="$icon" class="h-3.5 w-3.5" />
    {{ $label }}
</span>
