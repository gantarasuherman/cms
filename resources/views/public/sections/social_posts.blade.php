<section class="sarab-section" aria-labelledby="home-social">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-8 text-center">
            <p class="sarab-eyebrow">Media Sosial</p>
            <h2 id="home-social" class="text-[length:var(--step-3)] font-extrabold">
                {{ $section->title ?: 'Ikuti Kami' }}
            </h2>
            @if ($subtitle = $section->subtitle)
                <p class="sarab-lead mx-auto mt-3 max-w-2xl">{{ $subtitle }}</p>
            @endif
        </div>

        @php
            // The institution's logo doubles as the account picture; it is
            // already admin-managed, so this needs no second upload.
            $logo = data_get($site->general(), 'logo');
            $avatar = $logo ? Storage::disk('public')->url($logo) : null;

            // Instagram renders its own posts when this is on. Everything else
            // — Facebook, X, TikTok, YouTube — keeps the site's own card,
            // which is the only renderer those platforms have here.
            $embedInstagram = (bool) data_get($site->appearance(), 'instagram_embed', true);
            $embedded = $embedInstagram && $items->contains(fn ($p) => $p->platform === 'instagram');
        @endphp

        {{-- Two across, four in a row on a wide screen: an Instagram embed
             stops shrinking at 326px, so four columns on a 1280px page would
             overflow the container. --}}
        <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 2xl:grid-cols-4">
            @foreach ($items as $post)
                <li class="min-w-0">
                    @if ($embedInstagram && $post->platform === 'instagram')
                        <x-public.instagram-embed :post="$post" />
                    @else
                        <x-public.social-post-card :post="$post" :avatar="$avatar" />
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($embedded)
            {{-- Once for the page, after the blockquotes it has to find, and
                 `async` so a slow answer from Instagram never holds up the
                 rest of the homepage. --}}
            <script async src="https://www.instagram.com/embed.js"></script>
        @endif

    </div>
</section>
