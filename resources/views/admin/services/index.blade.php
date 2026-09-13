<x-layouts.admin title="Layanan">
    <x-ui.page-header title="Semua Layanan" description="Setiap layanan memiliki persyaratan, tarif, dan tahapan tersendiri.">
        <x-slot:actions>
            @can('create', App\Models\Service::class)
                <x-ui.button :href="route('admin.services.create')" icon="plus">Tambah Layanan</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.services.data')"
        caption="Daftar layanan"
        :columns="[
            ['key' => 'name', 'label' => 'Nama Layanan'],
            ['key' => 'category_name', 'label' => 'Kategori'],
            ['key' => 'processing_time', 'label' => 'Waktu'],
            ['key' => 'detail_counts', 'label' => 'Rincian', 'orderable' => false, 'searchable' => false],
            ['key' => 'status', 'label' => 'Status', 'raw' => true, 'searchable' => false],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
                <select id="filter-status" data-dt-filter="status"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

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
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
