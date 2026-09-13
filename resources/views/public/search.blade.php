<x-layouts.public title="Pencarian" :noindex="true">
    <x-public.page-hero title="Pencarian" description="Cari berita, layanan, dan dokumen sekaligus."
                        :breadcrumbs="['Pencarian' => null]" />

    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('public.search') }}" role="search" class="mb-10 flex gap-3">
            <div class="relative flex-1">
                <label for="site-search" class="sr-only">Kata kunci</label>
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input type="search" id="site-search" name="q" value="{{ $search }}" autofocus
                       placeholder="Ketik kata kunci…"
                       @error('q') aria-invalid="true" aria-describedby="search-error" @enderror
                       class="w-full rounded-lg border border-slate-300 py-3 pl-11 pr-3 focus:outline-2 focus:outline-offset-0 focus:outline-teal-700">
            </div>
            <button type="submit"
                    class="rounded-lg bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                Cari
            </button>
        </form>

        @error('q')
            <p id="search-error" role="alert" class="mb-6 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <x-icon name="circle-alert" class="h-4 w-4" />{{ $message }}
            </p>
        @enderror

        @if (! $search)
            <x-public.empty-state title="Mulai mencari"
                                  description="Masukkan kata kunci untuk mencari di berita, layanan, dan dokumen."
                                  icon="search" />
        @else
            @php
                $total = ($news?->total() ?? 0) + ($services?->total() ?? 0) + ($documents?->total() ?? 0);
            @endphp

            <p role="status" class="mb-8 text-sm text-slate-600">
                <strong>{{ number_format($total) }}</strong> hasil untuk &ldquo;{{ $search }}&rdquo;
            </p>

            @if ($total === 0)
                <x-public.empty-state title="Tidak ada hasil"
                                      description="Coba kata kunci yang lebih umum atau periksa ejaannya."
                                      icon="search" />
            @endif

            @if ($news?->isNotEmpty())
                <section class="mb-10" aria-labelledby="search-news">
                    <h2 id="search-news" class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900">
                        <x-icon name="newspaper" class="h-5 w-5 text-teal-700" />Berita ({{ $news->total() }})
                    </h2>
                    <ul class="space-y-3">
                        @foreach ($news as $item)
                            <li class="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 class="font-semibold text-slate-900">
                                    <a href="{{ route('public.news.show', $item->slug) }}"
                                       class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">{{ $item->title }}</a>
                                </h3>
                                @if ($item->excerpt)
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $item->excerpt }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($services?->isNotEmpty())
                <section class="mb-10" aria-labelledby="search-services">
                    <h2 id="search-services" class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900">
                        <x-icon name="briefcase" class="h-5 w-5 text-teal-700" />Layanan ({{ $services->total() }})
                    </h2>
                    <ul class="space-y-3">
                        @foreach ($services as $item)
                            <li class="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 class="font-semibold text-slate-900">
                                    <a href="{{ route('public.services.show', $item->slug) }}"
                                       class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">{{ $item->name }}</a>
                                </h3>
                                @if ($item->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $item->description }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($documents?->isNotEmpty())
                <section aria-labelledby="search-documents">
                    <h2 id="search-documents" class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900">
                        <x-icon name="files" class="h-5 w-5 text-teal-700" />Dokumen ({{ $documents->total() }})
                    </h2>
                    <ul class="space-y-3">
                        @foreach ($documents as $item)
                            <li class="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 class="font-semibold text-slate-900">
                                    <a href="{{ route('public.documents.show', $item->slug) }}"
                                       class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">{{ $item->title }}</a>
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">{{ strtoupper($item->file_extension) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</x-layouts.public>
