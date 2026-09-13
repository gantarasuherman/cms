<x-layouts.public title="Layanan" description="Daftar layanan beserta persyaratan, tarif, dan waktu penyelesaian.">
    <x-public.page-hero title="Layanan"
                        description="Persyaratan, tarif, dan tahapan setiap layanan."
                        :breadcrumbs="['Layanan' => null]" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <x-public.filter-bar :action="route('public.services.index')" :categories="$categories"
                             :active-category="$activeCategory" :search="$search"
                             placeholder="Cari layanan…" />

        @if ($services->isEmpty())
            <x-public.empty-state title="Layanan tidak ditemukan"
                                  description="Coba kata kunci lain atau pilih kategori yang berbeda."
                                  icon="briefcase" />
        @else
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <li>
                        <a href="{{ route('public.services.show', $service->slug) }}"
                           class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-teal-700 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            <span class="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-teal-50 text-teal-800">
                                <x-icon :name="$service->icon ?: 'briefcase'" class="h-5 w-5" />
                            </span>

                            @if ($service->category)
                                <span class="mb-1.5 text-xs font-medium uppercase tracking-wide text-slate-500">{{ $service->category->name }}</span>
                            @endif

                            <h2 class="text-base font-semibold text-slate-900">{{ $service->name }}</h2>

                            @if ($service->description)
                                <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $service->description }}</p>
                            @endif

                            @if ($service->processing_time)
                                <p class="mt-4 inline-flex items-center gap-1.5 pt-2 text-xs font-medium text-slate-500">
                                    <x-icon name="clock" class="h-3.5 w-3.5" />{{ $service->processing_time }}
                                </p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $services->links() }}</div>
        @endif
    </div>
</x-layouts.public>
