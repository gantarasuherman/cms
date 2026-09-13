<span class="inline-flex items-center gap-3 text-sm text-muted-foreground">
    @if ($complaint->attachments_count)
        <span class="inline-flex items-center gap-1">
            <x-icon name="image" class="h-3.5 w-3.5" />{{ $complaint->attachments_count }}
            <span class="sr-only">lampiran</span>
        </span>
    @endif

    @if ($complaint->hasLocation())
        <span class="inline-flex items-center gap-1">
            <x-icon name="map-pin" class="h-3.5 w-3.5" />Ada
            <span class="sr-only">titik lokasi</span>
        </span>
    @endif

    @if (! $complaint->attachments_count && ! $complaint->hasLocation())
        —
    @endif
</span>
