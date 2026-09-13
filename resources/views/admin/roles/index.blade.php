<x-layouts.admin title="Peran">
    <x-ui.page-header title="Peran" description="Peran bersifat dinamis — buat sebanyak yang dibutuhkan.">
        <x-slot:actions>
            @can('create', Spatie\Permission\Models\Role::class)
                <x-ui.button :href="route('admin.roles.create')" icon="plus">Tambah Peran</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.roles.data')"
        caption="Daftar peran"
        :columns="[
            ['key' => 'name', 'label' => 'Nama Peran'],
            ['key' => 'permission_summary', 'label' => 'Hak Akses', 'orderable' => false, 'searchable' => false],
            ['key' => 'user_count', 'label' => 'Pengguna', 'orderable' => false, 'searchable' => false],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]" />
</x-layouts.admin>
