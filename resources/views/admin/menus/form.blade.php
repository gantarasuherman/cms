@php $editing = $menu->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Menu' : 'Tambah Menu'">
    <x-ui.page-header :title="($editing ? 'Ubah ' : 'Tambah ').$heading">
        <x-slot:actions>
            <x-ui.button :href="route($routeBase.'.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route($routeBase.'.update', $menu) : route($routeBase.'.store') }}"
          class="max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Judul" name="title" :value="$menu->title" required />
                <x-ui.input label="Slug" name="slug" :value="$menu->slug" hint="Pengenal internal. Dibuat otomatis bila kosong." />

                <x-ui.select label="Induk" name="parent_id" :options="$parents" :value="$menu->parent_id"
                             placeholder="— Menu tingkat atas —"
                             hint="Kosongkan untuk menempatkannya di tingkat teratas." />

                <x-ui.icon-select name="icon" :value="$menu->icon"
                                  hint="Tampil di sebelah kiri judul menu." />

                <div class="rounded-lg border border-border bg-muted p-4">
                    <p class="mb-3 text-sm font-medium text-foreground">Tujuan</p>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Isi <strong>salah satu</strong>. Nama route dipakai lebih dulu bila keduanya diisi.
                        Kosongkan keduanya untuk menjadikannya grup yang hanya menampung sub-menu.
                    </p>
                    <div class="space-y-5">
                        <x-ui.input label="Nama Route" name="route" :value="$menu->route"
                                    hint="Contoh: public.news.index" />
                        <x-ui.input label="URL" name="url" :value="$menu->url"
                                    hint="Contoh: /halaman/profil atau https://contoh.test" />
                    </div>
                </div>

                @if ($kind === 'admin')
                    <x-ui.select label="Hak Akses" name="permission" :options="$permissions" :value="$menu->permission"
                                 placeholder="— Tanpa pembatasan —"
                                 hint="Menu disembunyikan dari pengguna yang tidak memiliki hak ini. Grup tetap tampil bila salah satu anaknya boleh diakses." />
                @endif

                <x-ui.select label="Target" name="target"
                             :options="['_self' => 'Buka di tab yang sama', '_blank' => 'Buka di tab baru']"
                             :value="$menu->target ?? '_self'" required />

                <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$menu->sort_order ?? 0" />
                <x-ui.checkbox label="Aktif" name="is_active" :checked="$menu->is_active ?? true" />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route($routeBase.'.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
