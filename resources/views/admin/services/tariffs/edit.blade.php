<x-layouts.admin title="Ubah Komponen Tarif">
    <x-ui.page-header title="Ubah Komponen Tarif" :description="$service->name" />

    <form method="POST" action="{{ route('admin.services.tariffs.update', [$service, $item]) }}" class="max-w-2xl">
        @csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Nama Komponen" name="name" :value="$item->name" required />
                <x-ui.input label="Tarif (Rp)" name="amount" type="number" min="0" step="0.01" :value="$item->amount" required />
                <x-ui.input label="Satuan" name="unit" :value="$item->unit" />
                <x-ui.textarea label="Catatan" name="notes" :value="$item->notes" :rows="2" />
                <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$item->sort_order" />
                <x-ui.checkbox label="Aktif" name="is_active" :checked="$item->is_active" />
            </div>
            <x-slot:footer>
                <x-ui.button :href="route('admin.services.tariffs.index', $service)" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
