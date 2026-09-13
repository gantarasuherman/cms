<x-layouts.admin title="Pengguna">
    <x-ui.page-header title="Pengguna" description="Akun yang dapat masuk ke panel admin.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <x-ui.button :href="route('admin.users.create')" icon="user-plus">Tambah Pengguna</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.users.data')"
        caption="Daftar pengguna"
        :columns="[
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'role_names', 'label' => 'Peran', 'orderable' => false, 'searchable' => false],
            ['key' => 'last_login_at', 'label' => 'Terakhir masuk'],
            ['key' => 'is_active', 'label' => 'Status', 'raw' => true, 'searchable' => false],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-role" class="mb-1.5 block text-xs font-medium text-muted-foreground">Peran</label>
                <select id="filter-role" data-dt-filter="role"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua peran</option>
                    @foreach ($roles as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
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
