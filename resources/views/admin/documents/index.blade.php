<x-layouts.admin title="Dokumen">
    <x-ui.page-header title="Semua Dokumen" description="Berkas disimpan di penyimpanan privat dan hanya dapat diunduh lewat tautan resmi.">
        <x-slot:actions>
            @can('create', App\Models\Document::class)
                <x-ui.button :href="route('admin.documents.create')" icon="upload">Unggah Dokumen</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.documents.data')"
        caption="Daftar dokumen"
        :columns="[
            ['key' => 'title', 'label' => 'Judul'],
            ['key' => 'category_name', 'label' => 'Kategori'],
            ['key' => 'file_info', 'label' => 'Berkas', 'orderable' => false, 'searchable' => false],
            ['key' => 'download_count', 'label' => 'Unduhan'],
            ['key' => 'is_active', 'label' => 'Status', 'raw' => true, 'searchable' => false],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-category" class="mb-1.5 block text-xs font-medium text-muted-foreground">Kategori</label>
                <select id="filter-category" data-dt-filter="category"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
                <select id="filter-status" data-dt-filter="status"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
