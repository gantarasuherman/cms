@php
    $tone = match ($conversation->status) {
        'active' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        default => 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    };
    $label = ['active' => 'Berlangsung', 'completed' => 'Selesai', 'expired' => 'Kedaluwarsa'][$conversation->status] ?? $conversation->status;
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $label }}</span>
