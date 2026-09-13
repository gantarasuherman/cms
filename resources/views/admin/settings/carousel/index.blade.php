<x-layouts.admin title="Carousel">
    <x-ui.page-header title="Hero Slider"
                      description="Slide utama beranda. Hanya slide aktif dan di dalam rentang tanggalnya yang tampil.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.settings.carousel.preview')" target="_blank" rel="noopener"
                         variant="secondary" icon="eye">Pratinjau</x-ui.button>
            @can('create', App\Models\CarouselSlide::class)
                <x-ui.button :href="route('admin.settings.carousel.create')" icon="plus">Tambah Slide</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.settings.partials.tabs')

    <x-ui.data-table
        :url="route('admin.settings.carousel.data')"
        caption="Daftar slide hero"
        :columns="[
            ['key' => 'preview', 'label' => 'Pratinjau', 'orderable' => false, 'searchable' => false, 'raw' => true],
            ['key' => 'title', 'label' => 'Judul'],
            ['key' => 'category', 'label' => 'Kategori'],
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

    @can('update', new App\Models\CarouselSlide())
        <x-ui.card title="Urutan Tampil" description="Seret untuk menyusun ulang, atau gunakan tombol naik/turun."
                   class="mt-6">
            @php $ordered = App\Models\CarouselSlide::orderBy('sort_order')->get(); @endphp

            @if ($ordered->isEmpty())
                <p class="py-6 text-center text-sm text-muted-foreground">Belum ada slide.</p>
            @else
                <ol data-sortable data-reorder-url="{{ route('admin.settings.carousel.reorder') }}" class="space-y-2">
                    @foreach ($ordered as $slide)
                        <li data-sortable-item data-id="{{ $slide->id }}" draggable="true"
                            class="flex items-center gap-3 rounded-lg border border-border bg-card p-3">
                            <span class="cursor-grab text-muted-foreground" aria-hidden="true">
                                <x-icon name="grip-vertical" class="h-5 w-5" />
                            </span>

                            <img src="{{ $slide->imageUrl() }}" alt=""
                                 class="h-10 w-16 shrink-0 rounded object-cover">

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $slide->title ?: '(tanpa judul)' }}</p>
                                @if ($slide->category)
                                    <p class="truncate text-xs text-muted-foreground">{{ $slide->category }}</p>
                                @endif
                            </div>

                            <x-status-badge :status="$slide->isLive() ? 'active' : 'inactive'" />

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" data-sortable-up
                                        aria-label="Naikkan {{ $slide->title ?: 'slide' }}"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-accent disabled:opacity-30">
                                    <x-icon name="chevron-up" class="h-4 w-4" />
                                </button>
                                <button type="button" data-sortable-down
                                        aria-label="Turunkan {{ $slide->title ?: 'slide' }}"
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
