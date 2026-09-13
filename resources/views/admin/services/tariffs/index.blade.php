<x-layouts.admin title="Tarif Layanan">
    <x-ui.page-header :title="$service->name" description="Komponen tarif. Total dihitung otomatis dari komponen yang aktif.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.services.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.services.partials.tabs', ['service' => $service])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Komponen Tarif">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <caption class="sr-only">Komponen tarif layanan {{ $service->name }}</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th scope="col" class="py-2 pr-3 font-semibold">Komponen</th>
                                <th scope="col" class="py-2 pr-3 text-right font-semibold">Tarif</th>
                                <th scope="col" class="py-2 pr-3 font-semibold">Satuan</th>
                                <th scope="col" class="py-2 pr-3 font-semibold">Status</th>
                                <th scope="col" class="py-2 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($items as $tariff)
                                <tr>
                                    <td class="py-3 pr-3">
                                        <p class="font-medium text-foreground">{{ $tariff->name }}</p>
                                        @if ($tariff->notes)
                                            <p class="text-xs text-muted-foreground">{{ $tariff->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 text-right tabular-nums">Rp{{ number_format((float) $tariff->amount, 0, ',', '.') }}</td>
                                    <td class="py-3 pr-3 text-muted-foreground">{{ $tariff->unit ?: '—' }}</td>
                                    <td class="py-3 pr-3"><x-status-badge :status="$tariff->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="py-3 text-right">
                                        <a href="{{ route('admin.services.tariffs.edit', [$service, $tariff]) }}"
                                           class="rounded-md px-2 py-1 font-medium text-foreground hover:bg-accent">Ubah</a>
                                        <x-ui.delete-form :action="route('admin.services.tariffs.destroy', [$service, $tariff])" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-muted-foreground">Belum ada komponen tarif.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($items->isNotEmpty())
                            <tfoot class="border-t-2 border-border">
                                <tr>
                                    <th scope="row" class="py-3 pr-3 text-left font-semibold text-foreground">Total (komponen aktif)</th>
                                    <td class="py-3 pr-3 text-right font-semibold tabular-nums text-foreground">
                                        Rp{{ number_format((float) $service->totalTariff(), 0, ',', '.') }}
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-ui.card>
        </div>

        <form method="POST" action="{{ route('admin.services.tariffs.store', $service) }}">
            @csrf
            <x-ui.card title="Tambah Komponen">
                <div class="space-y-5">
                    <x-ui.input label="Nama Komponen" name="name" required />
                    <x-ui.input label="Tarif (Rp)" name="amount" type="number" min="0" step="0.01" value="0" required />
                    <x-ui.input label="Satuan" name="unit" hint="Misalnya: per dokumen, per m²." />
                    <x-ui.textarea label="Catatan" name="notes" :rows="2" />
                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0" value="{{ $items->count() * 10 + 10 }}" />
                    <x-ui.checkbox label="Aktif" name="is_active" :checked="true" hint="Hanya komponen aktif yang dihitung ke total." />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="plus">Tambah</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
