<section class="sarab-section" aria-labelledby="home-faq">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('public.partials.section-heading', [
            'eyebrow' => 'Pertanyaan',
            'title' => $section->title ?: 'Yang Sering Ditanyakan',
            'subtitle' => $section->subtitle,
            'id' => 'home-faq',
        ])

        <div class="mx-auto max-w-3xl space-y-4">
            @foreach ($items as $faq)
                <div class="sarab-card overflow-hidden">
                    @include('public.partials.faq-item', ['faq' => $faq, 'expanded' => $loop->first])
                </div>
            @endforeach
        </div>

        <p class="mt-12 text-center">
            <a href="{{ route('public.faq.index') }}" class="sarab-btn sarab-btn-outline">
                Semua FAQ<x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>
