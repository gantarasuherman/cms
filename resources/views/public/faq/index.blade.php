<x-layouts.public title="FAQ" description="Pertanyaan yang sering diajukan beserta jawabannya.">
    <x-public.page-hero title="Pertanyaan yang Sering Diajukan"
                        description="Jawaban atas hal-hal yang paling sering ditanyakan."
                        :breadcrumbs="['FAQ' => null]" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('public.faq.index') }}" role="search" class="mb-8 flex gap-3">
            <div class="relative flex-1">
                <label for="faq-search" class="sr-only">Cari pertanyaan</label>
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input type="search" id="faq-search" name="q" value="{{ $search }}"
                       placeholder="Cari pertanyaan…"
                       class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:outline-2 focus:outline-offset-0 focus:outline-teal-700">
            </div>
            <button type="submit"
                    class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                Cari
            </button>
        </form>

        @if ($groups->isEmpty())
            <x-public.empty-state title="Tidak ada pertanyaan yang cocok"
                                  description="Coba kata kunci lain."
                                  icon="circle-help" />
        @else
            @foreach ($groups as $categoryName => $faqs)
                <section class="mb-8" aria-labelledby="faq-group-{{ $loop->index }}">
                    <h2 id="faq-group-{{ $loop->index }}" class="mb-3 text-lg font-bold text-slate-900">{{ $categoryName }}</h2>

                    <div class="divide-y divide-slate-200 rounded-2xl border border-slate-200">
                        @foreach ($faqs as $faq)
                            @include('public.partials.faq-item', ['faq' => $faq, 'expanded' => false])
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.public>
