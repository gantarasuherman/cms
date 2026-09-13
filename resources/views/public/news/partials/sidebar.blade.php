<aside class="space-y-8" aria-label="Pelengkap berita">
    <section aria-labelledby="sidebar-search">
        <h2 id="sidebar-search" class="news-section-title mb-4 !text-lg">Cari Berita</h2>

        <form method="GET" action="{{ route('public.news.index') }}" role="search" class="flex gap-2">
            @if ($activeCategory)
                <input type="hidden" name="kategori" value="{{ $activeCategory }}">
            @endif
            <label for="sidebar-q" class="sr-only">Kata kunci berita</label>
            <input type="search" id="sidebar-q" name="q" value="{{ $search }}" placeholder="Ketik kata kunci…"
                   class="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2.5 text-sm focus:outline-2 focus:outline-offset-0 focus:outline-[#db3700]">
            <button type="submit"
                    class="shrink-0 rounded bg-[#fc3f00] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#db3700] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
                Cari
            </button>
        </form>
    </section>

    @if ($popular->isNotEmpty())
        <section aria-labelledby="sidebar-popular">
            <h2 id="sidebar-popular" class="news-section-title mb-4 !text-lg">Terpopuler</h2>

            <ol class="space-y-4">
                @foreach ($popular as $item)
                    <li class="flex gap-3">
                        <span class="news-rank" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold leading-snug">
                                <a href="{{ route('public.news.show', $item->slug) }}"
                                   class="news-headline-link focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
                                    {{ $item->title }}
                                </a>
                            </h3>
                            <p class="news-meta mt-1">{{ number_format($item->views) }} kali dibaca</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section aria-labelledby="sidebar-categories">
            <h2 id="sidebar-categories" class="news-section-title mb-4 !text-lg">Kategori</h2>

            <ul class="divide-y divide-slate-200">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('public.news.index', ['kategori' => $category->slug]) }}"
                           @if ($activeCategory === $category->slug) aria-current="page" @endif
                           class="flex items-center justify-between gap-3 py-2.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700] {{ $activeCategory === $category->slug ? 'font-bold text-[#db3700]' : 'text-slate-700 hover:text-[#db3700]' }}">
                            <span class="min-w-0 truncate">{{ $category->name }}</span>
                            <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-600">
                                {{ $category->news_count }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tags->isNotEmpty())
        <section aria-labelledby="sidebar-tags">
            <h2 id="sidebar-tags" class="news-section-title mb-4 !text-lg">Tag</h2>

            <ul class="flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    <li>
                        <span class="inline-block rounded border border-slate-200 px-2.5 py-1 text-xs text-slate-600">
                            #{{ $tag->name }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</aside>
