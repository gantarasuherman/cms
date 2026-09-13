@props(['title', 'description' => null, 'breadcrumbs' => []])

<div class="border-b border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($breadcrumbs)
            <nav aria-label="Remah roti" class="mb-3">
                <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
                    <li>
                        <a href="{{ route('public.home') }}" class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">Beranda</a>
                    </li>
                    @foreach ($breadcrumbs as $label => $url)
                        <li aria-hidden="true" class="text-slate-300">/</li>
                        <li>
                            @if ($url)
                                <a href="{{ $url }}" class="hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">{{ $label }}</a>
                            @else
                                <span aria-current="page" class="font-medium text-slate-700">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $title }}</h1>

        @if ($description)
            <p class="mt-3 max-w-3xl text-slate-600">{{ $description }}</p>
        @endif

        @isset($slot)
            {{ $slot }}
        @endisset
    </div>
</div>
