@props(['news', 'light' => false])

<p class="news-meta flex flex-wrap items-center gap-x-3 gap-y-1 {{ $light ? 'text-white/80' : '' }}">
    <span class="inline-flex items-center gap-1.5">
        <x-icon name="calendar" class="h-3.5 w-3.5" />
        <time datetime="{{ $news->published_at?->toDateString() }}">{{ $news->published_at?->translatedFormat('d F Y') }}</time>
    </span>

    @if ($news->author)
        <span class="inline-flex items-center gap-1.5">
            <x-icon name="user" class="h-3.5 w-3.5" />{{ $news->author->name }}
        </span>
    @endif

    <span class="inline-flex items-center gap-1.5">
        <x-icon name="eye" class="h-3.5 w-3.5" />{{ number_format($news->views) }}
    </span>
</p>
