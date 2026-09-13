<x-layouts.admin title="Halaman">
    <x-ui.page-header title="Halaman" description="Halaman statis seperti Profil dan Kontak, tampil di /halaman/{slug}.">
        <x-slot:actions>
            @can('create', App\Models\Page::class)
                <x-ui.button :href="route('admin.pages.create')" icon="plus">Tambah Halaman</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.pages.data')"
        caption="Daftar halaman"
        :columns="[
            ['key' => 'title', 'label' => 'Judul'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'status', 'label' => 'Status', 'raw' => true, 'searchable' => false],
            ['key' => 'published_at', 'label' => 'Terbit'],
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
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
