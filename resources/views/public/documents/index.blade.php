<x-layouts.public title="Dokumen" description="Unduh dokumen dan publikasi resmi.">
    <x-public.page-hero title="Dokumen" description="Publikasi, peraturan, dan berkas yang dapat diunduh."
                        :breadcrumbs="['Dokumen' => null]" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <x-public.filter-bar :action="route('public.documents.index')" :categories="$categories"
                             :active-category="$activeCategory" :search="$search"
                             placeholder="Cari dokumen…" />

        @if ($documents->isEmpty())
            <x-public.empty-state title="Dokumen tidak ditemukan"
                                  description="Coba kata kunci lain atau pilih kategori yang berbeda."
                                  icon="files" />
        @else
            <ul class="space-y-3">
                @foreach ($documents as $document)
                    <li class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500">
                            <x-icon name="file-text" class="h-6 w-6" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-slate-900">
                                <a href="{{ route('public.documents.show', $document->slug) }}"
                                   class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                                    {{ $document->title }}
                                </a>
                            </h2>

                            @if ($document->description)
                                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $document->description }}</p>
                            @endif

                            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                @if ($document->category)
                                    <span>{{ $document->category->name }}</span>
                                @endif
                                <span>{{ strtoupper($document->file_extension) }}</span>
                                <span>{{ number_format($document->file_size / 1024, 0) }} KB</span>
                                <span>{{ number_format($document->download_count) }} unduhan</span>
                            </p>
                        </div>

                        <a href="{{ route('public.documents.download', $document->slug) }}"
                           class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            <x-icon name="download" class="h-4 w-4" />
                            Unduh<span class="sr-only">: {{ $document->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $documents->links() }}</div>
        @endif
    </div>
</x-layouts.public>
