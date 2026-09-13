<x-layouts.admin :title="$heading">
    <x-ui.page-header :title="$heading" description="Kategori mendukung sub-kategori bertingkat.">
        <x-slot:actions>
            @can('create', App\Models\Category::class)
                <x-ui.button :href="route($routeBase.'.create')" icon="plus">Tambah Kategori</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route($routeBase.'.data')"
        :caption="$heading"
        :columns="[
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'parent_name', 'label' => 'Induk', 'orderable' => false, 'searchable' => false],
            ['key' => 'sort_order', 'label' => 'Urutan'],
            ['key' => 'is_active', 'label' => 'Status', 'raw' => true, 'searchable' => false],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]" />
</x-layouts.admin>
