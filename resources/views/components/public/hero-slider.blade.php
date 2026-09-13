@props(['slides', 'label' => 'Sorotan utama'])

@php $slides = collect($slides)->values(); @endphp

@if ($slides->isNotEmpty())
    {{--
        Hero slider.

        Marked up as a tablist over slide panels rather than a bare div, so the
        control is announced for what it is. aria-live is "off" while it plays
        on its own — announcing every automatic change would interrupt a screen
        reader user mid-sentence — and the script switches it to "polite" once
        someone takes manual control.
    --}}
    <section
        data-hero
        data-interval="6000"
        aria-roledescription="carousel"
        aria-label="{{ $label }}"
        class="hero relative isolate overflow-hidden bg-[#0b1b2b]"
    >
        <div class="relative h-[26rem] sm:h-[30rem] lg:h-[38rem]">
            @foreach ($slides as $slide)
                @php $first = $loop->first; @endphp

                <article
                    data-hero-slide
                    id="hero-panel-{{ $loop->index }}"
                    role="tabpanel"
                    aria-roledescription="slide"
                    aria-label="{{ $loop->iteration }} dari {{ $slides->count() }}"
                    @unless ($first) aria-hidden="true" @endunless
                    class="absolute inset-0 transition-opacity duration-700 ease-out motion-reduce:transition-none {{ $first ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                >
                    <img
                        src="{{ $slide->imageUrl() }}"
                        alt="{{ $slide->altText() }}"
                        @if ($first) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif
                        data-hero-image
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    {{-- A directional wash, not a flat black veil: the left side
                         is dark enough to carry text while the photograph stays
                         a photograph on the right. --}}
                    <div aria-hidden="true"
                         class="absolute inset-0 bg-gradient-to-r from-[#0b1b2b]/92 via-[#0b1b2b]/70 to-[#0b1b2b]/10 sm:to-transparent"></div>

                    <div class="relative mx-auto flex h-full max-w-[82rem] items-center px-5 sm:px-8">
                        <div data-hero-copy class="max-w-xl lg:max-w-[45%]">
                            @if ($slide->category)
                                {{-- Dark ink on the brand orange, not white:
                                     white on #EF8519 is 2.61:1 and fails. The
                                     identity colour is kept exactly; only the
                                     text on it changes. --}}
                                <span class="inline-block rounded-full bg-[#EF8519] px-3.5 py-1.5 text-xs font-bold uppercase tracking-[0.12em] text-[#0b1b2b]">
                                    {{ $slide->category }}
                                </span>
                            @endif

                            <h2 class="mt-5 text-[clamp(1.875rem,1.1rem+3.4vw,4rem)] font-extrabold leading-[1.08] tracking-[-0.025em] text-white">
                                {{ $slide->title }}
                            </h2>

                            @if ($slide->subtitle)
                                <p class="mt-3 text-base font-semibold text-white/90 sm:text-lg">{{ $slide->subtitle }}</p>
                            @endif

                            @if ($slide->description)
                                <p class="mt-4 line-clamp-3 text-sm leading-relaxed text-white/85 sm:text-base">
                                    {{ $slide->description }}
                                </p>
                            @endif

                            @if ($slide->link && $slide->button_text)
                                <a href="{{ $slide->link }}"
                                   class="mt-7 inline-flex items-center gap-2 rounded-full bg-[#EF8519] px-6 py-3.5 text-sm font-bold text-[#0b1b2b] transition-colors hover:bg-[#ffa143] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                    {{ $slide->button_text }}
                                    <x-icon name="chevron-right" class="h-4 w-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($slides->count() > 1)
            <div class="absolute inset-x-0 bottom-0">
                <div class="mx-auto max-w-[82rem] px-5 pb-6 sm:px-8 sm:pb-8">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <button type="button" data-hero-prev
                                    class="grid h-11 w-11 place-items-center rounded-full border border-white/35 text-white transition-colors hover:bg-white hover:text-[#02468B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                <x-icon name="chevron-left" class="h-5 w-5" />
                                <span class="sr-only">Slide sebelumnya</span>
                            </button>

                            <button type="button" data-hero-next
                                    class="grid h-11 w-11 place-items-center rounded-full border border-white/35 text-white transition-colors hover:bg-white hover:text-[#02468B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                <x-icon name="chevron-right" class="h-5 w-5" />
                                <span class="sr-only">Slide berikutnya</span>
                            </button>

                            {{-- Autoplay must be stoppable by a real control, not
                                 only by hovering (WCAG 2.2.2). --}}
                            <button type="button" data-hero-play aria-pressed="false"
                                    class="grid h-11 w-11 place-items-center rounded-full border border-white/35 text-white transition-colors hover:bg-white hover:text-[#02468B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                <span data-hero-icon-pause><x-icon name="pause" class="h-4 w-4" /></span>
                                <span data-hero-icon-play hidden><x-icon name="play" class="h-4 w-4" /></span>
                                <span class="sr-only" data-hero-play-label>Hentikan pergantian otomatis</span>
                            </button>
                        </div>

                        <p class="font-mono text-sm font-semibold tracking-widest text-white">
                            <span data-hero-current>01</span>
                            <span class="text-white/50"> / {{ str_pad($slides->count(), 2, '0', STR_PAD_LEFT) }}</span>
                        </p>

                        {{-- Tabs are the accessible way to jump between slides;
                             the bar above each one doubles as the progress
                             indicator for the current slide. --}}
                        <div role="tablist" aria-label="Pilih slide" class="flex flex-1 items-center gap-1.5">
                            @foreach ($slides as $slide)
                                <button type="button" role="tab"
                                        data-hero-dot="{{ $loop->index }}"
                                        aria-controls="hero-panel-{{ $loop->index }}"
                                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                        class="group relative h-1.5 min-w-8 flex-1 overflow-hidden rounded-full bg-white/25 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                                    <span data-hero-progress
                                          class="absolute inset-y-0 left-0 block w-0 rounded-full bg-[#EF8519]"></span>
                                    <span class="sr-only">Slide {{ $loop->iteration }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Announces the current slide only after a manual change. --}}
        <p data-hero-status role="status" aria-live="off" class="sr-only"></p>
    </section>
@endif
