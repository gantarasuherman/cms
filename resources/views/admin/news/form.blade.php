@php $editing = $news->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Berita' : 'Tambah Berita'">
    <x-ui.page-header
        :title="$editing ? 'Ubah Berita' : 'Tambah Berita'"
        description="Judul, isi, kategori, dan metadata SEO.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.news.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.news.update', $news) : route('admin.news.store') }}"
          enctype="multipart/form-data"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Konten">
                <div class="space-y-5">
                    <x-ui.input label="Judul" name="title" :value="$news->title" required />

                    <x-ui.input label="Slug" name="slug" :value="$news->slug"
                                hint="Biarkan kosong untuk dibuat otomatis dari judul." />

                    <x-ui.textarea label="Ringkasan" name="excerpt" :value="$news->excerpt" :rows="3"
                                   hint="Tampil pada daftar berita dan hasil pencarian." />

                    <x-ui.textarea label="Isi Berita" name="content" :value="$news->content" :rows="14" />
                </div>
            </x-ui.card>

            <x-ui.card title="SEO" description="Kosongkan untuk memakai pengaturan SEO global.">
                <div class="space-y-5">
                    <x-ui.input label="SEO Title" name="seo_title" :value="$news->seo_title" />
                    <x-ui.textarea label="SEO Description" name="seo_description" :value="$news->seo_description" :rows="3" />
                    <x-ui.input label="SEO Keywords" name="seo_keywords" :value="$news->seo_keywords"
                                hint="Pisahkan dengan koma." />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Publikasi">
                <div class="space-y-5">
                    <x-ui.select label="Status" name="status" :options="$statuses" :value="$news->status" required />

                    <x-ui.input label="Tanggal Terbit" name="published_at" type="datetime-local"
                                :value="$news->published_at?->format('Y-m-d\TH:i')"
                                hint="Kosongkan untuk memakai waktu saat diterbitkan." />

                    <x-ui.checkbox label="Jadikan berita utama" name="is_featured" :checked="$news->is_featured"
                                   hint="Berita utama dapat ditampilkan di beranda." />
                </div>

                <x-slot:footer>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Kategori" description="Satu berita dapat memiliki lebih dari satu kategori.">
                @forelse ($categories as $id => $name)
                    <label class="flex items-center gap-2.5 py-1.5 text-sm text-foreground">
                        <input type="checkbox" name="categories[]" value="{{ $id }}"
                               @checked(in_array($id, old('categories', $selectedCategories)))
                               class="h-4 w-4 rounded border-input text-foreground">
                        {{ $name }}
                    </label>
                @empty
                    <p class="text-sm text-muted-foreground">
                        Belum ada kategori.
                        <a href="{{ route('admin.news.category.create') }}" class="font-medium underline">Tambah kategori</a>.
                    </p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Gambar Utama">
                @if ($news->featured_image)
                    <img src="{{ Storage::disk('public')->url($news->featured_image) }}"
                         alt="Gambar utama berita {{ $news->title }}"
                         class="mb-3 w-full rounded-lg border border-border object-cover">
                    <x-ui.checkbox label="Hapus gambar saat menyimpan" name="remove_featured_image" />
                @endif

                <x-ui.input label="Unggah gambar" name="featured_image" type="file" accept="image/*"
                            class="mt-3" hint="JPG, PNG, atau WebP. Maksimal 4 MB." />
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
