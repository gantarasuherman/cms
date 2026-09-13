@props([
    'action',
    'categories' => [],
    'activeCategory' => null,
    'search' => null,
    'placeholder' => 'Cari…',
])

@php $searchId = 'filter-search-'.uniqid(); @endphp

<div class="mb-8 space-y-4">
    <form method="GET" action="{{ $action }}" role="search" class="flex flex-wrap gap-3">
        @if ($activeCategory)
            <input type="hidden" name="kategori" value="{{ $activeCategory }}">
        @endif

        <div class="min-w-56 flex-1">
            <label for="{{ $searchId }}" class="sr-only">{{ $placeholder }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input type="search" id="{{ $searchId }}" name="q" value="{{ $search }}"
                       placeholder="{{ $placeholder }}"
                       class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:outline-2 focus:outline-offset-0 focus:outline-teal-700">
            </div>
        </div>

        <button type="submit"
                class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
            Cari
        </button>
    </form>

    @if (count($categories))
        <nav aria-label="Saring berdasarkan kategori">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ $action }}{{ $search ? '?q='.urlencode($search) : '' }}"
                       @if (! $activeCategory) aria-current="page" @endif
                       class="inline-block rounded-full border px-3.5 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ ! $activeCategory ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-teal-700' }}">
                        Semua
                    </a>
                </li>
                @foreach ($categories as $category)
                    @php $isActive = $activeCategory === $category->slug; @endphp
                    <li>
                        <a href="{{ $action }}?{{ http_build_query(array_filter(['kategori' => $category->slug, 'q' => $search])) }}"
                           @if ($isActive) aria-current="page" @endif
                           class="inline-block rounded-full border px-3.5 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ $isActive ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-teal-700' }}">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
