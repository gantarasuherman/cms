@php
    $main = $lead->first();
    $rest = $lead->skip(1)->take(3);
@endphp

<section aria-labelledby="lead-heading" class="border-b border-slate-200 bg-white pb-12 pt-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 id="lead-heading" class="news-section-title mb-6">Berita Utama</h2>

        <div class="grid gap-6 lg:grid-cols-5">
            {{-- The lead story: image with the headline laid over its lower half,
                 the way the source theme opens its trending block. --}}
            <article class="lg:col-span-3">
                <a href="{{ route('public.news.show', $main->slug) }}"
                   class="group relative block overflow-hidden rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--news-accent-text)]">
                    @if ($main->featured_image)
                        <img src="{{ Storage::disk('public')->url($main->featured_image) }}"
                             alt="" fetchpriority="high"
                             class="aspect-[16/10] w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                    @else
                        <div class="grid aspect-[16/10] w-full place-items-center bg-slate-100">
                            <x-icon name="newspaper" class="h-12 w-12 text-slate-300" />
                        </div>
                    @endif

                    {{-- A dark scrim, not a light tint: the headline must hold its
                         contrast whatever the photograph underneath happens to be. --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent"></div>

                    <div class="absolute inset-x-0 bottom-0 p-5 sm:p-7">
                        @if ($main->categories->isNotEmpty())
                            <span class="news-chip news-chip-solid mb-3 inline-block">{{ $main->categories->first()->name }}</span>
                        @endif

                        <h3 class="text-xl font-extrabold leading-tight text-white sm:text-3xl">{{ $main->title }}</h3>

                        @if ($main->excerpt)
                            <p class="mt-2 hidden max-w-2xl text-sm text-white/85 sm:block">{{ Str::limit($main->excerpt, 160) }}</p>
                        @endif

                        @include('public.news.partials.meta', ['news' => $main, 'light' => true])
                    </div>
                </a>
            </article>

            {{-- Beside it, the runners-up as compact rows: thumbnail left,
                 headline right. --}}
            <div class="lg:col-span-2">
                <ul class="divide-y divide-slate-200">
                    @foreach ($rest as $item)
                        <li class="py-4 first:pt-0 last:pb-0">
                            <article class="flex gap-4">
                                <a href="{{ route('public.news.show', $item->slug) }}" tabindex="-1" aria-hidden="true"
                                   class="shrink-0">
                                    @if ($item->featured_image)
                                        <img src="{{ Storage::disk('public')->url($item->featured_image) }}"
                                             alt="" loading="lazy"
                                             class="h-20 w-28 rounded object-cover">
                                    @else
                                        <span class="grid h-20 w-28 place-items-center rounded bg-slate-100">
                                            <x-icon name="newspaper" class="h-6 w-6 text-slate-300" />
                                        </span>
                                    @endif
                                </a>

                                <div class="min-w-0 flex-1">
                                    @if ($item->categories->isNotEmpty())
                                        <span class="news-eyebrow">{{ $item->categories->first()->name }}</span>
                                    @endif

                                    <h3 class="mt-1 text-sm font-bold leading-snug">
                                        <a href="{{ route('public.news.show', $item->slug) }}"
                                           class="news-headline-link focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--news-accent-text)]">
                                            {{ $item->title }}
                                        </a>
                                    </h3>

                                    <p class="news-meta mt-1.5">
                                        <time datetime="{{ $item->published_at?->toDateString() }}">
                                            {{ $item->published_at?->translatedFormat('d F Y') }}
                                        </time>
                                    </p>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
