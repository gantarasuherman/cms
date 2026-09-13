@php $editing = $category->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Kategori' : 'Tambah Kategori'">
    <x-ui.page-header :title="$editing ? 'Ubah '.$heading : 'Tambah '.$heading">
        <x-slot:actions>
            <x-ui.button :href="route($routeBase.'.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route($routeBase.'.update', $category) : route($routeBase.'.store') }}"
          class="max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="$category->name" required />

                <x-ui.input label="Slug" name="slug" :value="$category->slug"
                            hint="Biarkan kosong untuk dibuat otomatis." />

                <x-ui.select label="Induk" name="parent_id" :options="$parents" :value="$category->parent_id"
                             placeholder="— Tanpa induk —" />

                <x-ui.textarea label="Deskripsi" name="description" :value="$category->description" :rows="3" />

                <x-ui.icon-select name="icon" :value="$category->icon"
                                  hint="Ditampilkan di samping nama kategori pada situs publik." />

                <x-ui.input label="Urutan" name="sort_order" type="number" min="0"
                            :value="$category->sort_order ?? 0" />

                <x-ui.checkbox label="Aktif" name="is_active" :checked="$category->is_active ?? true" />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route($routeBase.'.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
