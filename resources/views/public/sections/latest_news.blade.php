<section class="sarab-section sarab-light" aria-labelledby="home-latest_news">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-public.card-row
            label="Berita Terbaru"
            eyebrow="Terkini"
            :title="$section->title ?: 'Berita Terbaru'"
            :subtitle="$section->subtitle"
            id="home-latest_news">
            @foreach ($items as $news)
                <li class="w-64 shrink-0 snap-start sm:w-72">
                    <x-public.media-card
                        :href="route('public.news.show', $news->slug)"
                        :title="$news->title"
                        :image="$news->featured_image ? Storage::disk('public')->url($news->featured_image) : null"
                        :badge="$news->categories->first()?->name"
                        :meta="$news->published_at?->translatedFormat('d F Y')"
                        icon="newspaper" />
                </li>
            @endforeach
        </x-public.card-row>

        <p class="mt-10">
            <a href="{{ route('public.news.index') }}" class="sarab-btn sarab-btn-outline">
                Semua Berita<x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>
