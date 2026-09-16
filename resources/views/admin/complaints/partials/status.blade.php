@php
    $tone = match ($complaint->status) {
        'baru' => 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-200',
        'diproses' => 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
        'selesai' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        // Bukan kewenangan dinas bukanlah penolakan: laporannya sah, hanya
        // alamatnya yang keliru. Menyamakan rupanya dengan "Ditolak" membuat
        // dua hal yang berbeda terbaca sama sekilas pandang.
        'diteruskan' => 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
    $icon = match ($complaint->status) {
        'baru' => 'circle-alert', 'diproses' => 'clock', 'selesai' => 'circle-check',
        'diteruskan' => 'building-2', default => 'circle-slash',
    };
@endphp

{{-- Colour plus an icon plus the word: colour alone would say nothing to a
     reader who cannot distinguish these hues (WCAG 1.4.1). --}}
<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">
    <x-icon :name="$icon" class="h-3.5 w-3.5" />{{ $complaint->statusLabel() }}
</span>
