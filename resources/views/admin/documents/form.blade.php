@php $editing = $document->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Dokumen' : 'Unggah Dokumen'">
    <x-ui.page-header :title="$editing ? 'Ubah Dokumen' : 'Unggah Dokumen'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.documents.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.documents.update', $document) : route('admin.documents.store') }}"
          enctype="multipart/form-data"
          class="max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Judul" name="title" :value="$document->title" required />
                <x-ui.input label="Slug" name="slug" :value="$document->slug" hint="Dipakai pada tautan unduhan publik." />
                <x-ui.select label="Kategori" name="category_id" :options="$categories" :value="$document->category_id"
                             placeholder="— Tanpa kategori —" />
                <x-ui.textarea label="Deskripsi" name="description" :value="$document->description" :rows="4" />

                @if ($editing)
                    <div class="rounded-lg border border-border bg-muted p-4">
                        <p class="text-sm font-medium text-foreground">Berkas saat ini</p>
                        <p class="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                            <x-icon name="paperclip" class="h-4 w-4" />
                            {{ $document->file_name }}
                            <span class="text-muted-foreground">·</span>
                            {{ strtoupper($document->file_extension) }}
                            <span class="text-muted-foreground">·</span>
                            {{ number_format($document->file_size / 1024, 0) }} KB
                        </p>
                    </div>
                @endif

                <x-ui.input label="{{ $editing ? 'Ganti berkas (opsional)' : 'Berkas' }}" name="file" type="file"
                            :required="! $editing"
                            hint="PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, atau ZIP. Maksimal 50 MB." />

                <x-ui.input label="Tanggal Terbit" name="published_at" type="datetime-local"
                            :value="$document->published_at?->format('Y-m-d\TH:i')"
                            hint="Kosongkan agar langsung tampil." />

                <x-ui.checkbox label="Aktif" name="is_active" :checked="$document->is_active ?? true"
                               hint="Dokumen nonaktif tidak dapat diunduh publik." />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route('admin.documents.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Unggah' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
