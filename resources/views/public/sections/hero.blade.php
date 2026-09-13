<section class="sarab-cream relative overflow-hidden">
    <div aria-hidden="true" class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-[var(--sarab-cream-2)]"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-24 h-[26rem] w-[26rem] rounded-full bg-[var(--sarab-cream-2)]/70"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            @if ($section->subtitle)
                <span class="sarab-eyebrow mb-4">{{ $section->subtitle }}</span>
            @endif

            <h1>{{ $section->title ?: $site->siteName() }}</h1>

            @if ($section->content)
                <p class="sarab-lead mx-auto mt-6 max-w-2xl">{{ $section->content }}</p>
            @endif
        </div>

        <div class="mt-10">
            <x-public.search-bar />
        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="{{ route('public.services.index') }}" class="sarab-btn sarab-btn-primary">
                <x-icon name="briefcase" class="h-4 w-4" />Lihat Layanan
            </a>
            <a href="{{ route('public.news.index') }}" class="sarab-btn sarab-btn-outline">
                <x-icon name="newspaper" class="h-4 w-4" />Berita Terbaru
            </a>
        </div>
    </div>
</section>
