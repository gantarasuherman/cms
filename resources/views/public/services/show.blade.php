<x-layouts.public
    :title="$service->seo_title ?: $service->name"
    :description="$service->seo_description ?: $service->description">

    <x-public.page-hero :title="$service->name" :description="$service->description"
                        :breadcrumbs="['Layanan' => route('public.services.index'), $service->name => null]">
        @if ($service->processing_time)
            <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-200">
                <x-icon name="clock" class="h-4 w-4 text-teal-700" />
                Waktu penyelesaian: <strong class="font-semibold">{{ $service->processing_time }}</strong>
            </p>
        @endif
    </x-public.page-hero>

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-10 lg:col-span-2">
                @if ($service->content)
                    <section aria-labelledby="service-about">
                        <h2 id="service-about" class="mb-3 text-xl font-bold text-slate-900">Tentang Layanan</h2>
                        <div class="content-body">{!! nl2br(e($service->content)) !!}</div>
                    </section>
                @endif

                @if ($service->requirements->isNotEmpty())
                    <section aria-labelledby="service-requirements">
                        <h2 id="service-requirements" class="mb-4 text-xl font-bold text-slate-900">Persyaratan</h2>
                        <ul class="space-y-3">
                            @foreach ($service->requirements as $requirement)
                                <li class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
                                    <span class="mt-0.5 shrink-0 {{ $requirement->is_required ? 'text-teal-700' : 'text-slate-400' }}">
                                        <x-icon :name="$requirement->is_required ? 'circle-check' : 'circle'" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ $requirement->name }}</p>
                                        @if ($requirement->description)
                                            <p class="mt-1 text-sm text-slate-600">{{ $requirement->description }}</p>
                                        @endif
                                        {{-- Status stated in words, not only by icon colour. --}}
                                        <p class="mt-1.5 text-xs font-medium {{ $requirement->is_required ? 'text-teal-800' : 'text-slate-500' }}">
                                            {{ $requirement->is_required ? 'Wajib' : 'Opsional' }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($service->steps->isNotEmpty())
                    <section aria-labelledby="service-steps">
                        <h2 id="service-steps" class="mb-4 text-xl font-bold text-slate-900">Tahapan</h2>
                        <ol class="space-y-4">
                            @foreach ($service->steps as $step)
                                <li class="flex items-start gap-4">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-700 text-sm font-bold text-white">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white p-4">
                                        <p class="font-medium text-slate-900">{{ $step->name }}</p>
                                        @if ($step->description)
                                            <p class="mt-1 text-sm text-slate-600">{{ $step->description }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                @if ($service->tariffs->isNotEmpty())
                    <section aria-labelledby="service-tariffs" class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h2 id="service-tariffs" class="mb-3 text-base font-bold text-slate-900">Tarif</h2>

                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Rincian tarif layanan {{ $service->name }}</caption>
                            <thead class="sr-only">
                                <tr><th scope="col">Komponen</th><th scope="col">Tarif</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($service->tariffs as $tariff)
                                    <tr>
                                        <th scope="row" class="py-2.5 pr-3 text-left font-normal text-slate-700">
                                            {{ $tariff->name }}
                                            @if ($tariff->unit)
                                                <span class="block text-xs text-slate-500">{{ $tariff->unit }}</span>
                                            @endif
                                        </th>
                                        <td class="py-2.5 text-right font-medium tabular-nums text-slate-900">
                                            Rp{{ number_format((float) $tariff->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200">
                                <tr>
                                    <th scope="row" class="py-3 pr-3 text-left font-semibold text-slate-900">Total</th>
                                    <td class="py-3 text-right text-base font-bold tabular-nums text-teal-800">
                                        Rp{{ number_format((float) $service->totalTariff(), 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        @if ($service->tariffs->whereNotNull('notes')->isNotEmpty())
                            <ul class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                                @foreach ($service->tariffs->whereNotNull('notes') as $tariff)
                                    <li>{{ $tariff->name }}: {{ $tariff->notes }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h2 class="mb-2 text-base font-bold text-slate-900">Butuh bantuan?</h2>
                    <p class="text-sm text-slate-600">Hubungi kami melalui kontak yang tercantum di bagian bawah halaman.</p>
                    <a href="{{ route('public.faq.index') }}"
                       class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        <x-icon name="circle-help" class="h-4 w-4" />Lihat pertanyaan umum
                    </a>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.public>
