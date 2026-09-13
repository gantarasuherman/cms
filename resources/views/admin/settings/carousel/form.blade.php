@php $editing = $slide->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Slide' : 'Tambah Slide'">
    <x-ui.page-header :title="$editing ? 'Ubah Slide' : 'Tambah Slide'"
                      description="Judul pendek dan deskripsi 2–3 baris terbaca paling baik pada hero.">
        <x-slot:actions>
            @if ($editing)
                <x-ui.button :href="route('admin.settings.carousel.preview', ['slide' => $slide->id])"
                             target="_blank" rel="noopener" variant="secondary" icon="eye">Pratinjau</x-ui.button>
            @endif
            <x-ui.button :href="route('admin.settings.carousel.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.settings.carousel.update', $slide) : route('admin.settings.carousel.store') }}"
          enctype="multipart/form-data" class="grid max-w-5xl gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Isi Slide">
                <div class="space-y-5">
                    <x-ui.input label="Kategori" name="category" :value="$slide->category"
                                hint="Tampil sebagai badge kecil di atas judul. Misalnya: Infrastruktur, Jalan, Irigasi." />

                    <x-ui.input label="Judul" name="title" :value="$slide->title"
                                hint="Maksimal sekitar 60 karakter agar tidak terpotong di layar kecil." />

                    <x-ui.input label="Sub-judul" name="subtitle" :value="$slide->subtitle" />

                    <x-ui.textarea label="Deskripsi" name="description" :value="$slide->description" :rows="3"
                                   hint="Dua sampai tiga baris. Teks yang lebih panjang akan dipangkas, bukan dibaca." />
                </div>
            </x-ui.card>

            <x-ui.card title="Gambar">
                <div class="space-y-5">
                    @if ($slide->image)
                        <img src="{{ $slide->imageUrl() }}" alt="Gambar slide saat ini"
                             class="w-full rounded-lg border border-border object-cover">
                    @endif

                    <x-ui.input label="{{ $editing ? 'Ganti gambar' : 'Gambar' }}" name="image" type="file"
                                accept="image/*" :required="! $editing"
                                hint="Lanskap, disarankan 1920×1080 piksel. JPG, PNG, atau WebP. Maksimal 6 MB." />

                    <x-ui.input label="Teks alternatif" name="alt_text" :value="$slide->alt_text"
                                hint="Jelaskan isi fotonya bagi pengguna pembaca layar. Kosongkan bila foto hanya dekoratif — judul di sebelahnya sudah menyampaikan maknanya." />
                </div>
            </x-ui.card>

            <x-ui.card title="Tombol">
                <div class="space-y-5">
                    <x-ui.input label="Teks Tombol" name="button_text" :value="$slide->button_text"
                                hint="Kosongkan bila slide tidak perlu tombol." />
                    <x-ui.input label="Tautan Tombol" name="link" :value="$slide->link"
                                hint="Misalnya /berita/judul-berita atau https://contoh.test" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Tayang">
                <div class="space-y-5">
                    <x-ui.checkbox label="Aktif" name="is_active" :checked="$slide->is_active ?? true"
                                   hint="Slide juga harus berada di dalam rentang tanggal di bawah untuk tampil." />

                    <x-ui.input label="Mulai Tayang" name="start_date" type="datetime-local"
                                :value="$slide->start_date?->format('Y-m-d\TH:i')"
                                hint="Kosongkan agar langsung tayang." />

                    <x-ui.input label="Berhenti Tayang" name="end_date" type="datetime-local"
                                :value="$slide->end_date?->format('Y-m-d\TH:i')"
                                hint="Kosongkan agar tayang seterusnya." />

                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0"
                                :value="$slide->sort_order"
                                hint="Kosongkan agar ditempatkan di urutan terakhir." />
                </div>

                <x-slot:footer>
                    <x-ui.button :href="route('admin.settings.carousel.index')" variant="secondary">Batal</x-ui.button>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
