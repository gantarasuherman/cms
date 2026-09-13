<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pratinjau Hero Slider &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/public.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC]">
    {{-- Shows every slide, including those switched off or still scheduled:
         the point of a preview is to check a slide before activating it. --}}
    <div role="note" class="flex flex-wrap items-center justify-between gap-3 bg-[#02468B] px-4 py-3 text-sm text-white">
        <p class="flex items-center gap-2">
            <x-icon name="eye" class="h-4 w-4" />
            Pratinjau — termasuk slide nonaktif dan terjadwal. Publik hanya melihat slide yang aktif dan di dalam rentang tanggalnya.
        </p>
        <a href="{{ route('admin.settings.carousel.index') }}" class="font-semibold underline underline-offset-4">
            Kembali ke daftar
        </a>
    </div>

    @if ($slides->isEmpty())
        <p class="p-16 text-center text-slate-500">Belum ada slide untuk ditampilkan.</p>
    @else
        <x-public.hero-slider :slides="$slides" />

        <ul class="mx-auto max-w-6xl divide-y divide-slate-200 px-4 py-10">
            @foreach ($slides as $slide)
                <li class="flex flex-wrap items-center gap-4 py-3 text-sm">
                    <span class="w-10 font-mono text-slate-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="min-w-0 flex-1 font-medium text-slate-900">{{ $slide->title ?: '(tanpa judul)' }}</span>
                    <x-status-badge :status="$slide->isLive() ? 'active' : 'inactive'" />
                </li>
            @endforeach
        </ul>
    @endif
</body>
</html>
