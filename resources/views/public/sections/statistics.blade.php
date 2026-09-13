<section class="sarab-section sarab-cream-2" aria-labelledby="home-stats">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('public.partials.section-heading', [
            'eyebrow' => 'Ringkasan',
            'title' => $section->title ?: 'Dalam Angka',
            'subtitle' => $section->subtitle,
            'id' => 'home-stats',
        ])

        <dl class="grid gap-7 sm:grid-cols-3">
            @foreach ($items as $stat)
                <div class="sarab-card p-9 text-center">
                    <span class="mx-auto mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-[var(--sarab-cream)] text-[var(--sarab-primary)]">
                        <x-icon :name="$stat['icon']" class="h-6 w-6" />
                    </span>
                    <dd class="text-[var(--step-4)] font-extrabold leading-none text-[var(--sarab-dark)]">{{ number_format($stat['value']) }}</dd>
                    <dt class="mt-2 text-[var(--step--1)] font-medium uppercase tracking-wide">{{ $stat['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</section>
