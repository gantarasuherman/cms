@props(['post', 'avatar' => null])

@php
    $slides = $post->gallery();
    $likes = App\Models\SocialPost::formatCount($post->likes);
    $comments = App\Models\SocialPost::formatCount($post->comments);
    $preview = $post->previewComments();
    // Instagram renders a whole post at one shape — the first slide's — and
    // pads the rest, so advancing a mixed-orientation carousel does not make
    // the page jump under the reader's cursor.
    $ratio = $slides->first()?->ratio() ?? '1 / 1';
@endphp

{{--
    A republished post, laid out the way Instagram lays one out: account row,
    media, action row, like count, caption, comments, timestamp.

    Three things it deliberately is *not*:

    1. An embed. Instagram's own blockquote+embed.js would call the platform
       from every visitor's browser and report that visitor before they had
       done anything. Every picture here is served from this site's own disk
       and nothing on the page touches instagram.com until a link is followed.
    2. A set of working controls. A heart that likes nothing is a dead control:
       it takes focus, promises an action and does none. So the action row is a
       row of glyphs marked `aria-hidden`, the counts beside it are text, and
       the real "like" lives behind the link to the post. The carousel arrows
       are the exception — they do something, so they are real buttons.
    3. A live figure. The counts are what the last sync read. They are left out
       entirely when unknown, because a 0 would claim the post has no likes
       rather than admit we do not know.
--}}
<article class="ig-post" style="--ig-ratio: {{ $ratio }}">

    <header class="ig-head">
        <span class="ig-avatar">
            @if ($avatar)
                <img src="{{ $avatar }}" alt="" loading="lazy" decoding="async">
            @else
                <x-icon name="building-2" class="h-4 w-4" />
            @endif
        </span>

        <span class="ig-who">
            <span class="ig-name">
                {{ $post->handle() }}
                @if ($post->is_verified)
                    <x-icon name="badge-check" class="ig-verified" aria-hidden="true" />
                    <span class="sr-only">(akun terverifikasi)</span>
                @endif
            </span>
            <span class="ig-sub">{{ $post->platformLabel() }}</span>
        </span>

        <x-icon name="ellipsis" class="ig-more-glyph" aria-hidden="true" />
    </header>

    @if ($slides->isNotEmpty())
        <div class="ig-media" data-ig-carousel>
            <ul class="ig-track" data-ig-track role="list"
                @if ($slides->count() > 1) aria-label="{{ $slides->count() }} gambar unggahan {{ $post->handle() }}" @endif>
                @foreach ($slides as $i => $slide)
                    <li class="ig-slide" data-ig-slide>
                        <img src="{{ $slide->url() }}"
                             alt="{{ $slide->alt_text ?: $post->altText() }}{{ $slides->count() > 1 ? ' (gambar '.($i + 1).' dari '.$slides->count().')' : '' }}"
                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async">

                        @if ($slide->isVideo())
                            {{-- The still, not the file. Mirroring the video
                                 would mean unbounded storage for something the
                                 platform already serves; the badge says where
                                 it plays. --}}
                            <a class="ig-play" href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer">
                                <x-icon name="play" class="h-6 w-6" />
                                <span class="sr-only">Putar video di {{ $post->platformLabel() }} (tab baru)</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($slides->count() > 1)
                <p class="ig-counter" data-ig-counter aria-hidden="true">1/{{ $slides->count() }}</p>

                {{-- Added by script, because without it they would be buttons
                     that scroll nothing: the track itself swipes and scrolls
                     natively, which is the behaviour that must never depend on
                     JavaScript. --}}
                <button type="button" class="ig-arrow ig-arrow--prev" data-ig-prev hidden>
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    <span class="sr-only">Gambar sebelumnya</span>
                </button>
                <button type="button" class="ig-arrow ig-arrow--next" data-ig-next hidden>
                    <x-icon name="chevron-right" class="h-4 w-4" />
                    <span class="sr-only">Gambar berikutnya</span>
                </button>
            @endif
        </div>
    @endif

    <div class="ig-body">
        @if ($slides->count() > 1)
            <ol class="ig-dots" data-ig-dots role="list">
                @foreach ($slides as $i => $slide)
                    <li><button type="button" data-ig-dot="{{ $i }}" @class(['ig-dot', 'is-on' => $i === 0])>
                        <span class="sr-only">Gambar {{ $i + 1 }}</span>
                    </button></li>
                @endforeach
            </ol>
        @endif

        <p class="ig-actions" aria-hidden="true">
            <x-icon name="heart" class="ig-glyph" />
            <x-icon name="message-circle" class="ig-glyph" />
            <x-icon name="send" class="ig-glyph" />
            <x-icon name="bookmark" class="ig-glyph ig-glyph--end" />
        </p>

        @if ($likes !== null)
            <p class="ig-likes">{{ $likes }} suka</p>
        @endif

        @if (filled($post->caption))
            <p class="ig-caption" data-ig-caption>
                <span class="ig-name">{{ $post->handle() }}</span>
                {!! $post->captionHtml() !!}
            </p>
            {{-- Shown by script only: with the caption unclamped there is
                 nothing for it to reveal. --}}
            <button type="button" class="ig-toggle" data-ig-more hidden>selengkapnya</button>
        @endif

        @if ($post->comments > 0)
            <p class="ig-viewall">
                <a href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer">
                    Lihat semua {{ $comments }} komentar
                </a>
            </p>
        @endif

        @foreach ($preview as $comment)
            <p class="ig-comment">
                <span class="ig-name">{{ $comment['username'] }}</span>
                {{ $comment['text'] }}
            </p>
        @endforeach

        <p class="ig-foot">
            @if ($post->posted_at)
                {{-- Relative for recency, exact in the tooltip and in the
                     markup — "3 hari lalu" alone loses the date. --}}
                <time datetime="{{ $post->posted_at->toIso8601String() }}"
                      title="{{ $post->posted_at->translatedFormat('d F Y, H:i') }}">{{ $post->posted_at->diffForHumans() }}</time>
                <span aria-hidden="true">·</span>
            @endif
            <a href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer" class="ig-permalink">
                Lihat di {{ $post->platformLabel() }}<span class="sr-only"> (tab baru)</span>
            </a>
        </p>
    </div>
</article>
