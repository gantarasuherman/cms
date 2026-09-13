@php
    // The flag alone would report "active" for a slide whose window has closed,
    // so the badge says what the public actually sees and the note says why.
    $live = $slide->isLive();
    $reason = match (true) {
        ! $slide->is_active => 'Dinonaktifkan',
        $slide->start_date && $slide->start_date->isFuture() => 'Terjadwal',
        $slide->end_date && $slide->end_date->isPast() => 'Kedaluwarsa',
        default => null,
    };
@endphp

<span class="inline-flex flex-col items-start gap-1">
    <x-status-badge :status="$live ? 'active' : 'inactive'" />
    @if ($reason)
        <span class="text-xs text-muted-foreground">{{ $reason }}</span>
    @endif
</span>
