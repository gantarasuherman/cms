<x-layouts.public title="Berita" description="Kabar, pengumuman, dan informasi terbaru.">
    <div class="news-theme bg-[#f7f7fd]">
        @if ($lead->isNotEmpty())
            @include('public.news.partials.lead', ['lead' => $lead])
        @else
            <div class="border-b border-slate-200 bg-white py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <nav aria-label="Remah roti" class="mb-2">
                        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
                            <li><a href="{{ route('public.home') }}" class="hover:text-[#db3700] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">Beranda</a></li>
                            <li aria-hidden="true" class="text-slate-300">/</li>
                            <li><a href="{{ route('public.news.index') }}" class="hover:text-[#db3700] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">Berita</a></li>
                            @if ($activeCategory || $search)
                                <li aria-hidden="true" class="text-slate-300">/</li>
                                <li aria-current="page" class="font-medium text-slate-700">
                                    {{ $search ? 'Pencarian: '.$search : $categories->firstWhere('slug', $activeCategory)?->name ?? 'Kategori' }}
                                </li>
                            @endif
                        </ol>
                    </nav>

                    <h1 class="news-section-title !text-3xl">
                        @if ($search)
                            Hasil pencarian &ldquo;{{ $search }}&rdquo;
                        @elseif ($activeCategory)
                            {{ $categories->firstWhere('slug', $activeCategory)?->name ?? 'Berita' }}
                        @else
                            Berita
                        @endif
                    </h1>

                    <p role="status" class="mt-3 text-sm text-slate-600">
                        {{ number_format($news->total()) }} berita ditemukan.
                    </p>
                </div>
            </div>
        @endif

        {{-- Category rail, mirroring the theme's filter tabs. Real links, not JS
             tabs, so each slice has its own shareable URL. --}}
        @if ($categories->isNotEmpty())
            <nav aria-label="Saring berdasarkan kategori" class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <ul class="flex gap-1 overflow-x-auto py-3">
                        <li>
                            <a href="{{ route('public.news.index') }}"
                               @if (! $activeCategory) aria-current="page" @endif
                               class="inline-block whitespace-nowrap rounded px-3.5 py-2 text-sm font-bold uppercase tracking-wide focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700] {{ ! $activeCategory ? 'bg-[#fc3f00] text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                                Semua
                            </a>
                        </li>
                        @foreach ($categories as $category)
                            @php $isActive = $activeCategory === $category->slug; @endphp
                            <li>
                                <a href="{{ route('public.news.index', ['kategori' => $category->slug]) }}"
                                   @if ($isActive) aria-current="page" @endif
                                   class="inline-block whitespace-nowrap rounded px-3.5 py-2 text-sm font-bold uppercase tracking-wide focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700] {{ $isActive ? 'bg-[#fc3f00] text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                                    {{ $category->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <h2 class="news-section-title mb-6">
                        {{ $lead->isNotEmpty() ? 'Berita Terbaru' : 'Daftar Berita' }}
                    </h2>

                    @if ($news->isEmpty())
                        <x-public.empty-state title="Berita tidak ditemukan"
                                              description="Coba kata kunci lain atau pilih kategori yang berbeda."
                                              icon="newspaper" />
                    @else
                        {{-- Horizontal cards: thumbnail left, caption right — the
                             theme's "what news" row, which keeps headlines on one
                             scan line instead of a wall of squares. --}}
                        <ul class="divide-y divide-slate-200">
                            @foreach ($news as $item)
                                <li class="py-6 first:pt-0">
                                    <article class="flex flex-col gap-4 sm:flex-row">
                                        <a href="{{ route('public.news.show', $item->slug) }}" tabindex="-1" aria-hidden="true"
                                           class="shrink-0 overflow-hidden rounded-lg">
                                            @if ($item->featured_image)
                                                <img src="{{ Storage::disk('public')->url($item->featured_image) }}"
                                                     alt="" loading="lazy"
                                                     class="aspect-[16/10] w-full object-cover sm:h-32 sm:w-48">
                                            @else
                                                <span class="grid aspect-[16/10] w-full place-items-center bg-slate-100 sm:h-32 sm:w-48">
                                                    <x-icon name="newspaper" class="h-8 w-8 text-slate-300" />
                                                </span>
                                            @endif
                                        </a>

                                        <div class="min-w-0 flex-1">
                                            @if ($item->categories->isNotEmpty())
                                                <div class="mb-2 flex flex-wrap gap-1.5">
                                                    @foreach ($item->categories->take(2) as $category)
                                                        <x-public.news-chip :category="$category" :index="$loop->parent->index + $loop->index" />
                                                    @endforeach
                                                </div>
                                            @endif

                                            <h3 class="text-lg font-extrabold leading-snug">
                                                <a href="{{ route('public.news.show', $item->slug) }}"
                                                   class="news-headline-link focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
                                                    {{ $item->title }}
                                                </a>
                                            </h3>

                                            @if ($item->excerpt)
                                                <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $item->excerpt }}</p>
                                            @endif

                                            <div class="mt-3">
                                                @include('public.news.partials.meta', ['news' => $item])
                                            </div>
                                        </div>
                                    </article>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-10">{{ $news->links() }}</div>
                    @endif
                </div>

                @include('public.news.partials.sidebar')
            </div>
        </div>
    </div>
</x-layouts.public>
