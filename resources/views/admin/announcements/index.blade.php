<x-layouts.admin title="Pengumuman">
    <x-ui.page-header title="Pengumuman"
                      description="Tampil sebagai modal sekali per pengunjung, lalu menetap sebagai teks berjalan di atas navbar.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.announcements.preview')" target="_blank" rel="noopener"
                         variant="secondary" icon="eye">Pratinjau</x-ui.button>
            @can('create', App\Models\Announcement::class)
                <x-ui.button :href="route('admin.announcements.create')" icon="plus">Tambah Pengumuman</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :url="route('admin.announcements.data')"
        caption="Daftar pengumuman"
        :columns="[
            ['key' => 'title', 'label' => 'Judul'],
            ['key' => 'ticker', 'label' => 'Teks Berjalan', 'orderable' => false],
            ['key' => 'code', 'label' => 'Kode', 'raw' => true, 'searchable' => false, 'orderable' => false],
            ['key' => 'status', 'label' => 'Status', 'raw' => true, 'searchable' => false, 'orderable' => false],
            ['key' => 'sort_order', 'label' => 'Urutan'],
            ['key' => 'start_date', 'label' => 'Mulai'],
            ['key' => 'end_date', 'label' => 'Selesai'],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
                <select id="filter-status" data-dt-filter="status"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-ui.data-table>

    @can('update', new App\Models\Announcement())
        <x-ui.card title="Urutan Teks Berjalan"
                   description="Menentukan urutan baris pada strip, dan pengumuman mana yang dibuka lebih dulu sebagai modal."
                   class="mt-6">
            @if ($announcements->isEmpty())
                <p class="py-6 text-center text-sm text-muted-foreground">Belum ada pengumuman.</p>
            @else
                <ol data-sortable data-reorder-url="{{ route('admin.announcements.reorder') }}" class="space-y-2">
                    @foreach ($announcements as $announcement)
                        <li data-sortable-item data-id="{{ $announcement->id }}" draggable="true"
                            class="flex items-center gap-3 rounded-lg border border-border bg-card p-3">
                            <span class="cursor-grab text-muted-foreground" aria-hidden="true">
                                <x-icon name="grip-vertical" class="h-5 w-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $announcement->title }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ $announcement->tickerText() }}</p>
                            </div>

                            <x-status-badge :status="$announcement->isLive() ? 'active' : 'inactive'" />

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" data-sortable-up aria-label="Naikkan {{ $announcement->title }}"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-accent disabled:opacity-30">
                                    <x-icon name="chevron-up" class="h-4 w-4" />
                                </button>
                                <button type="button" data-sortable-down aria-label="Turunkan {{ $announcement->title }}"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-accent disabled:opacity-30">
                                    <x-icon name="chevron-down" class="h-4 w-4" />
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <p data-sortable-status role="status" aria-live="polite" class="mt-3 text-sm text-muted-foreground"></p>
            @endif
        </x-ui.card>
    @endcan
</x-layouts.admin>
