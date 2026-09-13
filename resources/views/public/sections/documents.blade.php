<section class="sarab-section sarab-cream" aria-labelledby="home-documents">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-public.card-row
            label="Dokumen"
            eyebrow="Unduhan"
            :title="$section->title ?: 'Dokumen'"
            :subtitle="$section->subtitle ?: 'Publikasi dan peraturan yang dapat diunduh.'"
            id="home-documents">
            @foreach ($items as $document)
                <li class="w-64 shrink-0 snap-start sm:w-72">
                    <x-public.media-card
                        :href="route('public.documents.show', $document->slug)"
                        :title="$document->title"
                        :badge="$document->category?->name"
                        :meta="strtoupper($document->file_extension)"
                        :note="number_format($document->file_size / 1024, 0).' KB'"
                        icon="file-text" />
                </li>
            @endforeach
        </x-public.card-row>

        <p class="mt-10">
            <a href="{{ route('public.documents.index') }}" class="sarab-btn sarab-btn-outline">
                Semua Dokumen<x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>
