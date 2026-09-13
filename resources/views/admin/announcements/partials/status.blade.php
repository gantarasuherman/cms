@php
    // The flag alone would report "active" for a notice whose window has
    // closed, so the badge says what a visitor sees and the note says why.
    $live = $announcement->isLive();
    $reason = match (true) {
        ! $announcement->is_active => 'Dinonaktifkan',
        $announcement->start_date && $announcement->start_date->isFuture() => 'Terjadwal',
        $announcement->end_date && $announcement->end_date->isPast() => 'Kedaluwarsa',
        default => null,
    };
@endphp

<span class="inline-flex flex-col items-start gap-1">
    <x-status-badge :status="$live ? 'active' : 'inactive'" />
    @if ($reason)
        <span class="text-xs text-muted-foreground">{{ $reason }}</span>
    @endif
</span>
