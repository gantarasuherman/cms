@php $editing = $page->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Halaman' : 'Tambah Halaman'">
    <x-ui.page-header :title="$editing ? 'Ubah Halaman' : 'Tambah Halaman'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.pages.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
          enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Konten">
                <div class="space-y-5">
                    <x-ui.input label="Judul" name="title" :value="$page->title" required />
                    <x-ui.input label="Slug" name="slug" :value="$page->slug"
                                hint="Alamatnya menjadi /halaman/{slug}." />
                    <x-ui.textarea label="Ringkasan" name="excerpt" :value="$page->excerpt" :rows="3" />
                    <x-ui.textarea label="Isi Halaman" name="content" :value="$page->content" :rows="16" />
                </div>
            </x-ui.card>

            <x-ui.card title="SEO" description="Kosongkan untuk memakai pengaturan SEO global.">
                <div class="space-y-5">
                    <x-ui.input label="SEO Title" name="seo_title" :value="$page->seo_title" />
                    <x-ui.textarea label="SEO Description" name="seo_description" :value="$page->seo_description" :rows="3" />
                    <x-ui.input label="SEO Keywords" name="seo_keywords" :value="$page->seo_keywords" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Publikasi">
                <div class="space-y-5">
                    <x-ui.select label="Status" name="status" :options="$statuses" :value="$page->status" required />
                    <x-ui.input label="Tanggal Terbit" name="published_at" type="datetime-local"
                                :value="$page->published_at?->format('Y-m-d\TH:i')"
                                hint="Kosongkan untuk memakai waktu saat diterbitkan." />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Gambar Utama">
                @if ($page->featured_image)
                    <img src="{{ Storage::disk('public')->url($page->featured_image) }}"
                         alt="Gambar halaman {{ $page->title }}"
                         class="mb-3 w-full rounded-lg border border-border object-cover">
                    <x-ui.checkbox label="Hapus gambar saat menyimpan" name="remove_featured_image" />
                @endif
                <x-ui.input label="Unggah gambar" name="featured_image" type="file" accept="image/*" class="mt-3" />
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
