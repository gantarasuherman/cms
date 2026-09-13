<x-layouts.admin title="Ubah Tautan Sosial">
    <x-ui.page-header title="Ubah Tautan Sosial" />

    <form method="POST" action="{{ route('admin.settings.social.update', $link) }}" class="max-w-2xl">
        @csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Platform" name="platform" :value="$link->platform" required />
                <x-ui.input label="Label" name="label" :value="$link->label" />
                <x-ui.input label="URL" name="url" type="url" :value="$link->url" required />
                <x-ui.icon-select name="icon" :value="$link->icon" placeholder="— Ikon tautan umum —" />
                <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$link->sort_order" />
                <x-ui.checkbox label="Aktif" name="is_active" :checked="$link->is_active" />
            </div>
            <x-slot:footer>
                <x-ui.button :href="route('admin.settings.social.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
