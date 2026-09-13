<x-layouts.public :title="$document->title" :description="$document->description">
    <x-public.page-hero :title="$document->title"
                        :breadcrumbs="['Dokumen' => route('public.documents.index'), $document->title => null]" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($document->description)
            <div class="content-body mb-8">{!! nl2br(e($document->description)) !!}</div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-base font-bold text-slate-900">Rincian Berkas</h2>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Format</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ strtoupper($document->file_extension) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ukuran</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ number_format($document->file_size / 1024, 0) }} KB</dd>
                </div>
                @if ($document->category)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kategori</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">{{ $document->category->name }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah Unduhan</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ number_format($document->download_count) }}</dd>
                </div>
            </dl>

            <a href="{{ route('public.documents.download', $document->slug) }}"
               class="mt-6 inline-flex items-center gap-2 rounded-lg bg-teal-700 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                <x-icon name="download" class="h-4 w-4" />Unduh Dokumen
            </a>
        </div>
    </div>
</x-layouts.public>
