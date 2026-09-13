<x-layouts.public
    :title="$news->seo_title ?: $news->title"
    :description="$news->seo_description ?: $news->excerpt"
    :image="$news->featured_image ? Storage::disk('public')->url($news->featured_image) : null"
    type="article">

    @push('head')
        <meta property="article:published_time" content="{{ $news->published_at?->toIso8601String() }}">
        @foreach ($news->categories as $category)
            <meta property="article:section" content="{{ $category->name }}">
        @endforeach
    @endpush

    <div class="news-theme bg-white">
        {{-- Same container as the site header: mx-auto max-w-7xl with identical
             padding, so the article's left and right edges line up with the
             navbar instead of sitting inset from it. --}}
        <article class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <nav aria-label="Remah roti" class="mb-4">
                <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
                    <li><a href="{{ route('public.home') }}" class="hover:text-[#db3700] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">Beranda</a></li>
                    <li aria-hidden="true" class="text-slate-300">/</li>
                    <li><a href="{{ route('public.news.index') }}" class="hover:text-[#db3700] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">Berita</a></li>
                </ol>
            </nav>

            {{-- The photograph opens the article, at the width of the text and in
                 its own proportions — the source layout runs it at 100% of the
                 content column with no crop, so a wide shot is not sliced into a
                 letterbox. The cap is the one departure: without it a portrait
                 upload would push the headline off the first screen. --}}
            @if ($news->featured_image)
                <img src="{{ Storage::disk('public')->url($news->featured_image) }}"
                     alt="" fetchpriority="high"
                     class="mb-7 w-full rounded-lg object-cover"
                     style="max-height: 34rem">
            @endif

            <header>
                @if ($news->categories->isNotEmpty())
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        @foreach ($news->categories as $category)
                            <x-public.news-chip :category="$category" :index="$loop->index" />
                        @endforeach
                    </div>
                @endif

                <h1 class="text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">{{ $news->title }}</h1>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-y border-slate-200 py-3">
                    @include('public.news.partials.meta', ['news' => $news])

                    {{-- Share links are plain anchors: they work without
                         JavaScript and carry no third-party tracking script. --}}
                    <ul class="flex items-center gap-1.5">
                        <li class="news-meta mr-1">Bagikan:</li>
                        @php
                            $url = urlencode(route('public.news.show', $news->slug));
                            $text = urlencode($news->title);
                        @endphp
                        @foreach ([
                            ['Facebook', "https://www.facebook.com/sharer/sharer.php?u=$url", 'globe'],
                            ['X', "https://twitter.com/intent/tweet?url=$url&text=$text", 'send'],
                            ['WhatsApp', "https://wa.me/?text=$text%20$url", 'phone'],
                            ['Surel', "mailto:?subject=$text&body=$url", 'mail'],
                        ] as [$label, $href, $icon])
                            <li>
                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer"
                                   class="grid h-9 w-9 place-items-center rounded border border-slate-200 text-slate-600 transition hover:border-[#fc3f00] hover:text-[#db3700] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
                                    <x-icon :name="$icon" class="h-4 w-4" />
                                    <span class="sr-only">Bagikan ke {{ $label }} (tab baru)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </header>

            <div class="py-8">
                @if ($news->excerpt)
                    <p class="mb-8 border-l-4 border-[#fc3f00] pl-4 text-lg font-medium leading-relaxed text-slate-700">
                        {{ $news->excerpt }}
                    </p>
                @endif

                {{-- Escaped: article bodies are plain text in the CMS, so
                     rendering them as markup would be a stored-XSS path. --}}
                <div class="content-body">{!! nl2br(e($news->content)) !!}</div>

                @if ($news->tags->isNotEmpty())
                    <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-6">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-900">Tag</h2>
                        @foreach ($news->tags as $tag)
                            <span class="rounded border border-slate-200 px-2.5 py-1 text-xs text-slate-600">#{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>

        @if ($related->isNotEmpty())
            <section aria-labelledby="related-heading" class="border-t border-slate-200 bg-[#f7f7fd] py-12">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h2 id="related-heading" class="news-section-title mb-6">Berita Terkait</h2>

                    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($related as $item)
                            <li>
                                <article class="h-full overflow-hidden rounded-lg bg-white">
                                    <a href="{{ route('public.news.show', $item->slug) }}" tabindex="-1" aria-hidden="true">
                                        @if ($item->featured_image)
                                            <img src="{{ Storage::disk('public')->url($item->featured_image) }}"
                                                 alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
                                        @else
                                            <span class="grid aspect-[16/10] w-full place-items-center bg-slate-100">
                                                <x-icon name="newspaper" class="h-8 w-8 text-slate-300" />
                                            </span>
                                        @endif
                                    </a>

                                    <div class="p-4">
                                        @if ($item->categories->isNotEmpty())
                                            <span class="news-eyebrow">{{ $item->categories->first()->name }}</span>
                                        @endif

                                        <h3 class="mt-1.5 text-base font-bold leading-snug">
                                            <a href="{{ route('public.news.show', $item->slug) }}"
                                               class="news-headline-link focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
                                                {{ $item->title }}
                                            </a>
                                        </h3>

                                        <p class="news-meta mt-2">
                                            <time datetime="{{ $item->published_at?->toDateString() }}">
                                                {{ $item->published_at?->translatedFormat('d F Y') }}
                                            </time>
                                        </p>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
