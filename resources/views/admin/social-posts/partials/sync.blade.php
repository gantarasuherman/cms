@php
    $label = match ($post->sync_status) {
        'ok' => ['Tersinkron', 'text-emerald-700 dark:text-emerald-400', 'circle-check'],
        'failed' => ['Gagal', 'text-destructive', 'circle-alert'],
        'unsupported' => ['Manual', 'text-muted-foreground', 'circle-slash'],
        default => ['Belum pernah', 'text-muted-foreground', 'circle'],
    };
@endphp

<span class="inline-flex flex-col items-start gap-0.5">
    <span class="inline-flex items-center gap-1.5 text-sm {{ $label[1] }}">
        <x-icon :name="$label[2]" class="h-3.5 w-3.5" />{{ $label[0] }}
    </span>

    @if ($post->synced_at)
        <span class="text-xs text-muted-foreground">{{ $post->synced_at->diffForHumans() }}</span>
    @endif

    @if ($post->sync_status === 'failed' && $post->sync_message)
        <span class="max-w-64 text-xs text-muted-foreground">{{ Str::limit($post->sync_message, 80) }}</span>
    @endif
</span>
