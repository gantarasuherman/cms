@php
    $general = $site->general();
    $cta = ['text' => data_get($general, 'header_cta_text'), 'link' => data_get($general, 'header_cta_link')];
@endphp

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:gap-5 lg:px-8">

        {{-- The mark alone carries the identity; the site name is not repeated
             in text beside it. It still has to be announced, so it rides on
             the link's accessible name — hidden, never absent, or a screen
             reader would read out nothing but "link". --}}
        <a href="{{ route('public.home') }}"
           class="flex shrink-0 items-center rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
            @if ($logo = data_get($general, 'logo'))
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="" class="h-11 w-auto max-w-52 object-contain">
            @else
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-teal-700 text-white">
                    <x-icon name="building-2" class="h-6 w-6" />
                </span>
            @endif
            <span class="sr-only">{{ $site->siteName() }} — beranda</span>
        </a>

        {{-- Search sits beside the logo rather than behind an icon: on a site
             whose whole purpose is looking things up, hiding the field costs a
             click on every single search. --}}
        <div class="hidden min-w-0 max-w-sm flex-1 lg:block">
            <x-public.search-pill :search="request('q')" :type="request('jenis')" />
        </div>

        <nav aria-label="Navigasi utama" class="ml-auto hidden lg:block">
            <ul class="flex items-center gap-0.5">
                @foreach ($publicMenu as $item)
                    @include('public.partials.nav-item', ['item' => $item, 'depth' => 0])
                @endforeach
            </ul>
        </nav>

        @if (filled($cta['text']) && filled($cta['link']))
            <a href="{{ $cta['link'] }}"
               class="hidden shrink-0 items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 lg:inline-flex">
                <x-icon name="external-link" class="h-4 w-4" />{{ $cta['text'] }}
            </a>
        @endif

        <button type="button" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav"
                class="ml-auto rounded-md p-2 text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 lg:hidden">
            <span class="sr-only">Buka menu</span>
            <x-icon name="menu" class="h-6 w-6" />
        </button>
    </div>

    {{-- Mobile navigation: the same database tree, rendered flat with
         disclosure groups so every level stays reachable by keyboard. --}}
    <nav id="mobile-nav" hidden aria-label="Navigasi utama (seluler)"
         class="border-t border-slate-200 bg-white lg:hidden">
        <div class="mx-auto max-w-7xl px-4 pt-3 sm:px-6">
            <x-public.search-pill :search="request('q')" :type="request('jenis')" />
        </div>

        <ul class="mx-auto max-w-7xl space-y-0.5 px-4 py-3 sm:px-6">
            @foreach ($publicMenu as $item)
                @include('public.partials.nav-item-mobile', ['item' => $item, 'depth' => 0])
            @endforeach

            @if (filled($cta['text']) && filled($cta['link']))
                <li>
                    <a href="{{ $cta['link'] }}"
                       class="mt-1 flex items-center gap-2.5 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        <x-icon name="external-link" class="h-4 w-4" />{{ $cta['text'] }}
                    </a>
                </li>
            @endif
        </ul>
    </nav>
</header>
