<x-layouts.admin title="Audit Log">
    <x-ui.page-header title="Audit Log" description="Catatan perubahan data beserta pelakunya. Catatan bersifat hanya-baca.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.audit-logs.clear-cache') }}"
                  onsubmit="return confirm('Kosongkan seluruh cache publik?')">
                @csrf
                <x-ui.button variant="secondary" icon="database">Kosongkan Cache Publik</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.audit-logs.data')"
        caption="Catatan audit"
        :length="25"
        :columns="[
            ['key' => 'created_at', 'label' => 'Waktu'],
            ['key' => 'user_name', 'label' => 'Pengguna'],
            ['key' => 'action', 'label' => 'Tindakan'],
            ['key' => 'module', 'label' => 'Modul'],
            ['key' => 'record_id', 'label' => 'ID Data'],
            ['key' => 'changes', 'label' => 'Perubahan', 'orderable' => false, 'searchable' => false, 'raw' => true],
            ['key' => 'ip', 'label' => 'IP'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-module" class="mb-1.5 block text-xs font-medium text-muted-foreground">Modul</label>
                <select id="filter-module" data-dt-filter="module"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua modul</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}">{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-action" class="mb-1.5 block text-xs font-medium text-muted-foreground">Tindakan</label>
                <select id="filter-action" data-dt-filter="action"
                        class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
                    <option value="">Semua tindakan</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-from" class="mb-1.5 block text-xs font-medium text-muted-foreground">Dari tanggal</label>
                <input type="date" id="filter-from" data-dt-filter="from"
                       class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
            </div>

            <div>
                <label for="filter-to" class="mb-1.5 block text-xs font-medium text-muted-foreground">Sampai tanggal</label>
                <input type="date" id="filter-to" data-dt-filter="to"
                       class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm ">
            </div>
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
