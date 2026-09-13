@props(['post'])

@php
    // The shortcode, taken from the path rather than from the whole link: a
    // shared Instagram URL arrives with `?img_index=`, `?utm_source=` and
    // whatever else the share sheet appended, and the embed endpoint wants
    // none of it.
    preg_match('#/(p|reel|tv)/([A-Za-z0-9_-]+)#', (string) $post->permalink, $m);
    $code = $m[2] ?? null;
    $kind = $m[1] ?? 'p';
@endphp

@if ($code)
    {{--
        Instagram's own embed, rendered by Instagram.

        This is the one way to show a post exactly as the platform shows it —
        live like counts, the real carousel, video that actually plays — and it
        is switched on in /admin/settings/appearance rather than hard-coded,
        because it carries a cost worth an explicit decision: embed.js is
        fetched from instagram.com, so Meta learns the IP address of everyone
        who opens a page carrying one. Turning the setting off falls back to
        `<x-public.social-post-card>`, which serves every picture from this
        site's own disk and calls nobody.

        The blockquote is the fallback, not a placeholder: with the script
        blocked, unreachable, or JavaScript off, what remains is a readable
        link to the post rather than an empty hole.
    --}}
    {{-- Capped to the height the site's own cards reach, and scrolled rather
         than clipped: Instagram sizes its iframe to the whole post, and a
         long caption made this one twice the height of the Facebook card
         beside it, leaving a hole in the row. Nothing is lost — the part
         below the fold scrolls, and `overscroll-behavior` keeps that scroll
         inside the card instead of carrying the page with it. --}}
    <div class="ig-embed">
    <div class="ig-embed-scroll">
    <blockquote class="instagram-media"
                data-instgrm-permalink="https://www.instagram.com/{{ $kind }}/{{ $code }}/"
                data-instgrm-version="14"
                data-instgrm-captioned>
        <a href="https://www.instagram.com/{{ $kind }}/{{ $code }}/" target="_blank" rel="noopener noreferrer">
            Lihat unggahan {{ $post->handle() }} di Instagram
        </a>
    </blockquote>
    </div>
    </div>
@else
    {{-- Not a post link — a profile, or something mistyped. The site's own
         card can still show whatever was recorded for it. --}}
    <x-public.social-post-card :post="$post" />
@endif
