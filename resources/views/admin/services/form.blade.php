@php $editing = $service->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Layanan' : 'Tambah Layanan'">
    <x-ui.page-header :title="$editing ? $service->name : 'Tambah Layanan'"
                      description="Detail layanan. Persyaratan, tarif, dan tahapan dikelola pada tab tersendiri.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.services.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($editing)
        @include('admin.services.partials.tabs', ['service' => $service])
    @endif

    <form method="POST"
          action="{{ $editing ? route('admin.services.update', $service) : route('admin.services.store') }}"
          enctype="multipart/form-data"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Informasi Layanan">
                <div class="space-y-5">
                    <x-ui.input label="Nama Layanan" name="name" :value="$service->name" required />
                    <x-ui.input label="Slug" name="slug" :value="$service->slug" hint="Biarkan kosong untuk dibuat otomatis." />
                    <x-ui.textarea label="Deskripsi Singkat" name="description" :value="$service->description" :rows="3" />
                    <x-ui.textarea label="Penjelasan Lengkap" name="content" :value="$service->content" :rows="10" />
                </div>
            </x-ui.card>

            <x-ui.card title="SEO" description="Kosongkan untuk memakai pengaturan SEO global.">
                <div class="space-y-5">
                    <x-ui.input label="SEO Title" name="seo_title" :value="$service->seo_title" />
                    <x-ui.textarea label="SEO Description" name="seo_description" :value="$service->seo_description" :rows="3" />
                    <x-ui.input label="SEO Keywords" name="seo_keywords" :value="$service->seo_keywords" hint="Pisahkan dengan koma." />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Publikasi">
                <div class="space-y-5">
                    <x-ui.select label="Status" name="status" :options="$statuses" :value="$service->status" required />
                    <x-ui.select label="Kategori" name="category_id" :options="$categories" :value="$service->category_id"
                                 placeholder="— Tanpa kategori —" />
                    <x-ui.input label="Waktu Pelayanan" name="processing_time" :value="$service->processing_time"
                                hint="Bebas, misalnya: 3 hari kerja, 1x24 jam." />
                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$service->sort_order ?? 0" />
                    <x-ui.icon-select name="icon" :value="$service->icon"
                                      hint="Ditampilkan pada kartu layanan." />
                </div>

                <x-slot:footer>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Gambar">
                @if ($service->image)
                    <img src="{{ Storage::disk('public')->url($service->image) }}"
                         alt="Gambar layanan {{ $service->name }}"
                         class="mb-3 w-full rounded-lg border border-border object-cover">
                    <x-ui.checkbox label="Hapus gambar saat menyimpan" name="remove_image" />
                @endif

                <x-ui.input label="Unggah gambar" name="image" type="file" accept="image/*" class="mt-3"
                            hint="JPG, PNG, atau WebP. Maksimal 4 MB." />
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
