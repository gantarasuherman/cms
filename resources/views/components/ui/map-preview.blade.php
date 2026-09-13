@props(['latitude', 'longitude', 'label' => 'Titik lokasi', 'compact' => false])

@php
    // Assembled on this server and cached: an embedded map would hand a tile
    // provider the coordinates of somebody's complaint and the operator's
    // address on every view.
    $map = app(App\Services\Maps\MapSnapshot::class)->url((float) $latitude, (float) $longitude);
    $coordinates = $latitude.', '.$longitude;
    $maps = 'https://www.google.com/maps?q='.$latitude.','.$longitude;
@endphp

<div class="overflow-hidden rounded-lg border border-border">
    @if ($map)
        <a href="{{ $maps }}" target="_blank" rel="noopener noreferrer"
           class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
            <img src="{{ $map }}" alt="Peta {{ $label }} pada {{ $coordinates }}" loading="lazy"
                 class="{{ $compact ? 'h-32' : 'h-48' }} w-full object-cover">
        </a>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 bg-card px-3 py-2">
        <p class="font-mono text-xs text-muted-foreground">{{ $coordinates }}</p>

        <a href="{{ $maps }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
            <x-icon name="external-link" class="h-3 w-3" />Buka peta
        </a>
    </div>

    @unless ($map)
        {{-- No network, or the tile provider refused. The coordinates above
             still say where it is, which is the part that matters. --}}
        <p class="border-t border-border px-3 py-2 text-xs text-muted-foreground">
            Gambar peta belum dapat dimuat. Koordinatnya tetap dapat dibuka melalui tautan di atas.
        </p>
    @endunless
</div>
