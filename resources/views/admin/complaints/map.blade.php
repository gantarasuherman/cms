<x-layouts.admin title="Peta Pengaduan">
    <x-ui.page-header title="Peta Pengaduan"
                      description="Tempat yang dilaporkan berulang kali, dikelompokkan berdasarkan jarak di lapangan.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.complaints.index')" variant="secondary" icon="list">Daftar Pengaduan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filters are a plain GET form: the result is a page an operator can
         bookmark and send to a colleague, which a JavaScript filter would not
         be. --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="f-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
            <select id="f-status" name="status" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                <option value="">Semua</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-kategori" class="mb-1.5 block text-xs font-medium text-muted-foreground">Kategori</label>
            <select id="f-kategori" name="kategori" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                <option value="">Semua</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('kategori') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Rentang tanggal untuk melihat penumpukan pada satu musim: titik
             yang sama dilaporkan sepanjang musim hujan menceritakan hal yang
             berbeda dari titik yang dilaporkan sekali tahun lalu. --}}
        <div>
            <label for="f-dari" class="mb-1.5 block text-xs font-medium text-muted-foreground">Dari tanggal</label>
            <input type="date" id="f-dari" name="dari" value="{{ $since?->format('Y-m-d') }}"
                   class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
        </div>

        <div>
            <label for="f-sampai" class="mb-1.5 block text-xs font-medium text-muted-foreground">Sampai tanggal</label>
            <input type="date" id="f-sampai" name="sampai" value="{{ $until?->format('Y-m-d') }}"
                   class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
        </div>

        <x-ui.button type="submit" icon="filter">Terapkan</x-ui.button>

        @if (request()->hasAny(['status', 'kategori', 'dari', 'sampai']))
            <x-ui.button :href="route('admin.complaints.map')" variant="secondary" icon="x">Bersihkan</x-ui.button>
        @endif
    </form>

    <p class="-mt-2 mb-6 text-sm text-muted-foreground">
        @if ($since || $until)
            Menampilkan pengaduan
            @if ($since) sejak <strong class="text-foreground">{{ $since->translatedFormat('d F Y') }}</strong> @endif
            @if ($until) sampai <strong class="text-foreground">{{ $until->translatedFormat('d F Y') }}</strong> @endif
            — {{ $located }} titik dari {{ $total }} pengaduan seluruhnya.
        @else
            Menampilkan seluruh pengaduan yang punya titik lokasi: {{ $located }} dari {{ $total }}.
        @endif
    </p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-ui.card class="!p-4">
            <p class="text-sm text-muted-foreground">Titik berulang</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $clusters->filter(fn ($c) => $c->count() > 1)->count() }}</p>
        </x-ui.card>
        <x-ui.card class="!p-4">
            <p class="text-sm text-muted-foreground">Lokasi terpantau</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $clusters->count() }}</p>
        </x-ui.card>
        <x-ui.card class="!p-4">
            <p class="text-sm text-muted-foreground">Aduan berlokasi</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $located }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">dari {{ $total }} aduan</p>
        </x-ui.card>
        <x-ui.card class="!p-4">
            <p class="text-sm text-muted-foreground">Belum selesai di titik berulang</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $clusters->filter(fn ($c) => $c->count() > 1)->sum(fn ($c) => $c->open()) }}</p>
        </x-ui.card>

        {{-- Berguna justru di peta: titik-titik yang bukan kewenangan dinas ini
             biasanya menumpuk di satu ruas — jalan nasional, saluran milik
             provinsi — dan penumpukan itu baru terlihat setelah dipetakan.
             Saring statusnya untuk melihat hanya titik-titik itu. --}}
        <x-ui.card class="!p-4">
            <p class="text-sm text-muted-foreground">Bukan kewenangan dinas</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $redirected }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">dari {{ $located }} titik yang tampil</p>
        </x-ui.card>
    </div>

    @if ($points->isNotEmpty())
        <x-ui.card title="Sebaran titik pengaduan" class="mb-6">
            {{-- Leaflet, bundled from node_modules, drawing tiles that this
                 application fetched and cached. The map pans and zooms like
                 any other, and the operator's browser still never speaks to a
                 map provider: a tile request goes to admin/peta/petak/…, and
                 the server asks OpenStreetMap once. --}}
            <div data-complaint-map="peta-pengaduan"
                 class="h-[520px] w-full overflow-hidden rounded-lg border border-border bg-muted"
                 tabindex="0"
                 role="application"
                 aria-label="Peta sebaran pengaduan. Rincian setiap titik juga tersedia sebagai daftar di bawah peta."></div>

            @php
                // Assembled here rather than inline in the directive: the tile
                // template's {z}/{x}/{y} are Leaflet's placeholders, and Blade
                // reads those braces as its own.
                $payload = [
                    'points' => $points,
                    'tiles' => url('admin/peta/petak').'/{z}/{x}/{y}',
                    'attribution' => '&copy; Kontributor OpenStreetMap',
                    // Alamatnya, bukan isinya: batas wilayah berukuran ratusan
                    // kilobyte dan tidak pernah berubah, jadi peramban cukup
                    // mengunduhnya sekali lalu menyinggahkannya — menempelkannya
                    // ke HTML berarti mengirim ulang pada setiap penyaringan.
                    'boundary' => $boundary,
                    'boundaryPadding' => config('complaints.boundary_padding'),
                ];
            @endphp

            {{-- The map's data, not a script: nothing here is executed, and a
                 ticket or an address written by a reporter cannot become
                 markup on the way in. --}}
            <script type="application/json" id="peta-pengaduan">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

            {{-- Legenda, bukan sekadar warna.
                 Warna saja tidak dapat diandalkan membedakan lebih dari empat
                 jenis — bahkan bagi mata yang membedakan warna — jadi setiap
                 pin juga membawa ikon jenisnya, dan popup menyebut namanya. --}}
            <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t border-border pt-3">
                @foreach ($legend as $entry)
                    <li class="flex items-center gap-2 text-sm">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-white ring-2 ring-background"
                              style="background: {{ $entry['color'] }}">
                            @if ($entry['icon'])
                                <x-icon :name="$entry['icon']" class="h-3 w-3" />
                            @endif
                        </span>
                        {{ $entry['name'] }}
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 text-sm text-muted-foreground">
                Titik yang berdekatan digabung menjadi satu lingkaran berangka; perbesar peta untuk
                memisahkannya kembali. Warna dan ikon menunjukkan jenis pengaduannya. Klik sebuah titik
                untuk melihat tiketnya. Gulir untuk memperbesar aktif setelah peta diklik, agar peta
                tidak menelan guliran halaman.
            </p>
        </x-ui.card>
    @endif

    @forelse ($clusters as $cluster)
        @php $latest = $cluster->latest(); @endphp
        <x-ui.card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        @if ($cluster->count() > 1)
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-2 text-xs font-bold text-primary-foreground">
                                {{ $cluster->count() }}
                            </span>
                        @endif
                        {{ $cluster->label() }}
                    </h2>

                    {{-- The finding itself, in words: "3 Jalan Rusak" rather
                         than a count that leaves the reader to guess what of. --}}
                    <p class="mt-1 text-sm text-muted-foreground">{{ $cluster->summary() }}</p>

                    <ul class="mt-3 flex flex-wrap gap-2 text-xs">
                        @foreach ($cluster->statuses() as $label => $count)
                            <li class="rounded-full border border-border px-2.5 py-1">{{ $label }}: <strong class="tabular-nums">{{ $count }}</strong></li>
                        @endforeach
                    </ul>
                </div>

                <div class="shrink-0 text-right">
                    @if ($latest)
                        <p class="text-xs text-muted-foreground">Terakhir {{ $latest->created_at->diffForHumans() }}</p>
                        <x-ui.button :href="route('admin.complaints.show', $latest)" variant="secondary" class="mt-2">Buka aduan terbaru</x-ui.button>
                    @endif
                </div>
            </div>

            <ol class="mt-4 divide-y divide-border border-t border-border">
                @foreach ($cluster->complaints->sortByDesc('created_at') as $complaint)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2.5 text-sm">
                        <a href="{{ route('admin.complaints.show', $complaint) }}" class="font-mono font-semibold text-primary hover:underline">{{ $complaint->ticket }}</a>
                        <span class="text-muted-foreground">{{ $complaint->category?->name ?: 'Tanpa kategori' }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $complaint->description }}</span>
                        <span class="text-xs text-muted-foreground">{{ $complaint->created_at->translatedFormat('d M Y') }}</span>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @empty
        <x-ui.card>
            <p class="text-sm text-muted-foreground">
                Belum ada pengaduan yang menyertakan titik lokasi
                @if (request()->hasAny(['status', 'kategori'])) dengan saringan ini @endif.
                Lokasi ikut terkirim saat pelapor membagikan titik lewat WhatsApp atau Telegram.
            </p>
        </x-ui.card>
    @endforelse
</x-layouts.admin>
