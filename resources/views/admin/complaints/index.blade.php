<x-layouts.admin title="Pengaduan">
    <x-ui.page-header title="Pengaduan"
                      description="Laporan yang masuk melalui WhatsApp dan Telegram." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($statuses as $key => $label)
            <x-ui.card class="!p-4">
                <p class="text-sm text-muted-foreground">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $counts[$key] ?? 0 }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.data-table
        :url="route('admin.complaints.data')"
        caption="Daftar pengaduan"
        :columns="[
            ['key' => 'ticket', 'label' => 'Tiket', 'raw' => true],
            ['key' => 'category_name', 'label' => 'Kategori', 'orderable' => false],
            ['key' => 'description', 'label' => 'Uraian', 'orderable' => false],
            ['key' => 'reporter', 'label' => 'Pelapor', 'orderable' => false, 'searchable' => false],
            ['key' => 'evidence', 'label' => 'Bukti', 'raw' => true, 'orderable' => false, 'searchable' => false],
            ['key' => 'status_badge', 'label' => 'Status', 'raw' => true, 'orderable' => false, 'searchable' => false],
            ['key' => 'created_at', 'label' => 'Masuk'],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
                <select id="filter-status" data-dt-filter="status"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-category" class="mb-1.5 block text-xs font-medium text-muted-foreground">Kategori</label>
                <select id="filter-category" data-dt-filter="category"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-channel" class="mb-1.5 block text-xs font-medium text-muted-foreground">Kanal</label>
                <select id="filter-channel" data-dt-filter="channel"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="telegram">Telegram</option>
                </select>
            </div>
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
