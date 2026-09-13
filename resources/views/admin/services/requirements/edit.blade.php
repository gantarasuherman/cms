<x-layouts.admin title="Ubah Persyaratan">
    <x-ui.page-header title="Ubah Persyaratan" :description="$service->name" />

    <form method="POST" action="{{ route('admin.services.requirements.update', [$service, $item]) }}" class="max-w-2xl">
        @csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="$item->name" required />
                <x-ui.textarea label="Keterangan" name="description" :value="$item->description" :rows="3" />
                <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$item->sort_order" />
                <x-ui.checkbox label="Wajib dipenuhi" name="is_required" :checked="$item->is_required" />
            </div>
            <x-slot:footer>
                <x-ui.button :href="route('admin.services.requirements.index', $service)" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
