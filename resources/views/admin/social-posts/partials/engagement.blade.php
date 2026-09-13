@php
    $likes = App\Models\SocialPost::formatCount($post->likes);
    $comments = App\Models\SocialPost::formatCount($post->comments);
@endphp

@if ($likes === null && $comments === null)
    <span class="text-muted-foreground">—</span>
@else
    <span class="inline-flex items-center gap-3 text-sm">
        <span class="inline-flex items-center gap-1">
            <x-icon name="heart" class="h-3.5 w-3.5" />{{ $likes ?? '—' }}
            <span class="sr-only">suka</span>
        </span>
        <span class="inline-flex items-center gap-1">
            <x-icon name="message-circle" class="h-3.5 w-3.5" />{{ $comments ?? '—' }}
            <span class="sr-only">komentar</span>
        </span>
    </span>
@endif
