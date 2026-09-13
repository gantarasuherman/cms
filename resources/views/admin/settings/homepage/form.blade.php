@php $editing = $section->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Bagian Beranda' : 'Tambah Bagian Beranda'">
    <x-ui.page-header :title="$editing ? 'Ubah Bagian Beranda' : 'Tambah Bagian Beranda'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.settings.homepage.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.settings.homepage.update', $section) : route('admin.settings.homepage.store') }}"
          class="max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.select label="Jenis Bagian" name="type" :options="$types" :value="$section->type" required
                             placeholder="— Pilih jenis —"
                             hint="Menentukan bagaimana bagian ini digambar di beranda." />

                <x-ui.input label="Judul" name="title" :value="$section->title"
                            hint="Tampil sebagai judul bagian. Kosongkan untuk menyembunyikannya." />

                <x-ui.input label="Sub-judul" name="subtitle" :value="$section->subtitle" />

                <x-ui.textarea label="Konten" name="content" :value="$section->content" :rows="6"
                               hint="Dipakai oleh jenis Hero, Banner, dan Konten Bebas." />

                <x-ui.input label="Jumlah item" name="limit" type="number" min="1" max="24"
                            :value="$section->setting('limit')"
                            hint="Berapa banyak item ditampilkan, untuk jenis berita, layanan, dokumen, dan FAQ." />

                <x-ui.checkbox label="Tampilkan bagian ini" name="is_active" :checked="$section->is_active ?? true" />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route('admin.settings.homepage.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
