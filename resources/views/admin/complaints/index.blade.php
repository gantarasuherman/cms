<x-layouts.admin title="Pengaduan">
    <x-ui.page-header title="Pengaduan"
                      description="Laporan yang masuk melalui WhatsApp dan Telegram." />

    {{-- Periode memayungi SELURUH angka di bawahnya, jadi ia berdiri di atas
         semuanya. Ditaruh di sela kartu, ia akan terbaca sebagai saringan
         salah satu kartu saja. --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3">
        <div>
            <label for="periode" class="mb-1.5 block text-xs font-medium text-muted-foreground">Periode</label>
            <select id="periode" name="periode"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <x-ui.button variant="secondary" icon="filter">Terapkan</x-ui.button>

        <p class="ml-auto self-center text-sm text-muted-foreground">
            @if ($from)
                Menghitung sejak {{ $from->translatedFormat('d F Y') }}.
            @else
                Menghitung seluruh pengaduan yang pernah masuk.
            @endif
        </p>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat-tile label="Pengaduan masuk" :value="$stats['total']" icon="clipboard-list"
                        caption="Seluruh laporan pada periode ini" />

        <x-ui.stat-tile label="Dijawab" :value="$stats['answered']" icon="message-circle"
                        caption="Pelapor menerima balasan tertulis" />

        <x-ui.stat-tile label="Selesai" :value="$stats['byStatus']['selesai']" icon="circle-check"
                        caption="Ditandai sudah ditangani" />

        {{-- Berdiri sendiri, tidak dilebur ke "Ditolak": laporannya sah, hanya
             alamatnya yang keliru. Angka ini juga yang menjawab pertanyaan
             yang sering muncul di rapat — berapa banyak yang sebenarnya bukan
             pekerjaan dinas ini. --}}
        <x-ui.stat-tile label="Bukan kewenangan" :value="$stats['byStatus']['diteruskan']" icon="building-2"
                        caption="Warga diarahkan ke instansi lain" />

        <x-ui.stat-tile label="Ditolak" :value="$stats['byStatus']['ditolak']" icon="circle-slash"
                        caption="Ditandai tidak dapat ditindaklanjuti" />
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.bar-chart
                :series="collect($statuses)->map(fn ($label, $key) => ['label' => $label, 'value' => $stats['byStatus'][$key]])->values()"
                title="Menurut status"
                description="Sebaran laporan pada periode ini."
                value-label="Pengaduan" />

            {{-- "Dijawab" sengaja TIDAK dimasukkan ke grafik di atas: ia bukan
                 status, dan sebuah laporan bisa sudah dijawab sekaligus masih
                 diproses. Menaruhnya sebagai batang kelima membuat jumlah
                 batang melebihi jumlah laporan yang ada. --}}
            <p class="mt-4 border-t border-border pt-3 text-sm text-muted-foreground">
                @if ($stats['total'] === 0)
                    Belum ada pengaduan pada periode ini.
                @else
                    {{ $stats['answered'] }} dari {{ $stats['total'] }} laporan sudah dibalas kepada pelapornya.
                    Sebuah laporan dapat sudah dijawab tetapi masih diproses, jadi angka ini berdiri
                    sendiri di luar kelima status di atas.
                @endif
            </p>
        </x-ui.card>

        <x-ui.card>
            <x-ui.bar-chart
                :series="$stats['byCategory']"
                title="Menurut jenis"
                description="Jenis yang paling sering diadukan berada di kiri."
                value-label="Pengaduan" />

            <p class="mt-4 border-t border-border pt-3 text-sm text-muted-foreground">
                @if (($stats['busiest']['value'] ?? 0) === 0)
                    Belum ada pengaduan pada periode ini, jadi belum ada yang menonjol.
                @else
                    Terbanyak: <strong class="text-foreground">{{ $stats['busiest']['label'] }}</strong>
                    ({{ $stats['busiest']['value'] }}).
                    Paling sedikit: <strong class="text-foreground">{{ $stats['quietest']['label'] }}</strong>
                    ({{ $stats['quietest']['value'] }}){{ $stats['quietest']['value'] === 0 ? ' — belum ada satu pun' : '' }}.
                @endif
            </p>
        </x-ui.card>
    </div>

    <x-ui.card title="Pertanyaan terbanyak"
               description="Yang paling sering ditanyakan warga pada periode ini, dikelompokkan menurut kemiripan kalimatnya."
               class="mb-6">
        @if ($stats['questions']->isEmpty())
            <p class="py-6 text-center text-sm text-muted-foreground">
                Belum ada pertanyaan yang tercatat pada periode ini.
            </p>
        @else
            <ol class="divide-y divide-border">
                @foreach ($stats['questions'] as $topic)
                    <li class="flex items-start gap-3 py-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-muted text-xs font-bold tabular-nums">
                            {{ $loop->iteration }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">{{ $topic['question'] }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Ditanyakan {{ $topic['asks'] }}&times; oleh {{ $topic['askers'] }} orang
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <x-slot:footer>
                <x-ui.button :href="route('admin.bot.questions.index')" variant="secondary" icon="circle-help">
                    Jadikan FAQ
                </x-ui.button>
            </x-slot:footer>
        @endif
    </x-ui.card>

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
