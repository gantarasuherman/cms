@props(['title' => null, 'subtitle' => null, 'tone' => 'white'])

@php
    $id = 'section-'.uniqid();
    $background = $tone === 'muted' ? 'bg-slate-50' : 'bg-white';
@endphp

<section @if ($title) aria-labelledby="{{ $id }}" @endif class="{{ $background }} py-14 sm:py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($title)
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="{{ $id }}" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-2 max-w-2xl text-slate-600">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($action)
                    <div>{{ $action }}</div>
                @endisset
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
