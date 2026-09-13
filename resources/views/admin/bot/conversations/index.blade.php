<x-layouts.admin title="Riwayat Percakapan">
    <x-ui.page-header title="Riwayat Percakapan"
                      description="Seluruh percakapan dari WhatsApp dan Telegram." />

    <x-ui.data-table
        :url="route('admin.bot.conversations.data')"
        caption="Daftar percakapan"
        :columns="[
            ['key' => 'contact', 'label' => 'Pengguna', 'orderable' => false],
            ['key' => 'channel_name', 'label' => 'Kanal', 'orderable' => false, 'searchable' => false],
            ['key' => 'last_message', 'label' => 'Pesan Terakhir', 'orderable' => false, 'searchable' => false],
            ['key' => 'messages_count', 'label' => 'Pesan', 'searchable' => false],
            ['key' => 'status_badge', 'label' => 'Status', 'raw' => true, 'orderable' => false, 'searchable' => false],
            ['key' => 'last_message_at', 'label' => 'Terakhir'],
            ['key' => 'actions', 'label' => '', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-channel" class="mb-1.5 block text-xs font-medium text-muted-foreground">Kanal</label>
                <select id="filter-channel" data-dt-filter="channel"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    @foreach ($channels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
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
                <label for="filter-from" class="mb-1.5 block text-xs font-medium text-muted-foreground">Dari tanggal</label>
                <input type="date" id="filter-from" data-dt-filter="from"
                       class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
            </div>
            <div>
                <label for="filter-to" class="mb-1.5 block text-xs font-medium text-muted-foreground">Sampai</label>
                <input type="date" id="filter-to" data-dt-filter="to"
                       class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
            </div>
        </x-slot:filters>
    </x-ui.data-table>
</x-layouts.admin>
