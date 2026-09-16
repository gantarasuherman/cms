<x-layouts.admin title="Dashboard">
    <section aria-labelledby="stats-heading">
        <h2 id="stats-heading" class="sr-only">Ringkasan</h2>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($stats as $stat)
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">{{ $stat['label'] }}</span>
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-muted text-muted-foreground">
                            <x-icon :name="$stat['icon']" class="h-4.5 w-4.5" />
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-semibold tabular-nums text-foreground">{{ number_format($stat['value']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @if ($complaints)
        <section aria-labelledby="complaints-heading" class="mt-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="complaints-heading" class="text-base font-semibold text-foreground">Pengaduan</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Laporan warga yang masuk lewat WhatsApp dan Telegram dalam {{ $complaintDays }} hari terakhir.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button :href="route('admin.complaints.index')" variant="secondary" icon="clipboard-list">
                        Daftar Pengaduan
                    </x-ui.button>
                    <x-ui.button :href="route('admin.complaints.map')" variant="secondary" icon="map-pin">
                        Peta Pengaduan
                    </x-ui.button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-ui.stat-tile label="Masuk" :value="$complaints['total']" icon="clipboard-list"
                                caption="Seluruh laporan {{ $complaintDays }} hari terakhir" />

                <x-ui.stat-tile label="Belum ditangani"
                                :value="$complaints['byStatus']['baru'] + $complaints['byStatus']['diproses']"
                                icon="circle-alert"
                                caption="Masih baru atau sedang diproses" />

                <x-ui.stat-tile label="Selesai" :value="$complaints['byStatus']['selesai']" icon="circle-check"
                                caption="Ditandai sudah ditangani" />

                {{-- Angka ini ada di dasbor, bukan hanya di layar pengaduan,
                     karena ia menjawab pertanyaan pimpinan dan bukan pertanyaan
                     petugas: seberapa banyak laporan yang sampai ke sini
                     sebenarnya bukan pekerjaan dinas ini. Naik terus berarti
                     warga belum tahu harus melapor ke mana. --}}
                <x-ui.stat-tile label="Bukan kewenangan" :value="$complaints['byStatus']['diteruskan']"
                                icon="building-2"
                                caption="Warga diarahkan ke instansi lain" />

                <x-ui.stat-tile label="Ditolak" :value="$complaints['byStatus']['ditolak']" icon="circle-slash"
                                caption="Tidak dapat ditindaklanjuti" />
            </div>

            @if ($complaints['total'] > 0 && ($complaints['busiest']['value'] ?? 0) > 0)
                <p class="mt-3 text-sm text-muted-foreground">
                    Paling banyak diadukan: <strong class="text-foreground">{{ $complaints['busiest']['label'] }}</strong>
                    ({{ $complaints['busiest']['value'] }}).
                    {{ $complaints['answered'] }} dari {{ $complaints['total'] }} laporan sudah dibalas kepada pelapornya.
                </p>
            @elseif ($complaints['total'] === 0)
                <p class="mt-3 text-sm text-muted-foreground">
                    Belum ada pengaduan masuk dalam {{ $complaintDays }} hari terakhir.
                </p>
            @endif
        </section>
    @endif

    <section aria-labelledby="visitors-heading" class="mt-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="visitors-heading" class="text-base font-semibold text-foreground">Statistik Pengunjung</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Kunjungan ke situs publik. Pengunjung dihitung secara anonim — alamat IP tidak disimpan.
                </p>
            </div>
        </div>

        @if (! $analyticsEnabled)
            <div role="note" class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                <x-icon name="triangle-alert" class="mt-0.5 h-4.5 w-4.5" />
                <p>
                    Pencatatan kunjungan dimatikan (<code>ANALYTICS_ENABLED=false</code>), jadi angka di bawah tidak
                    diperbarui. Ini disampaikan terbuka agar nol tidak disalahartikan sebagai situs tanpa pengunjung.
                </p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat-tile
                    label="Pengunjung hari ini"
                    :value="$visitorSummary['visitors_today']['value']"
                    :previous="$visitorSummary['visitors_today']['previous']"
                    :delta="$visitorSummary['visitors_today']['delta']"
                    icon="users"
                    caption="Dihitung unik per hari" />

                <x-ui.stat-tile
                    label="Kunjungan hari ini"
                    :value="$visitorSummary['views_today']['value']"
                    :previous="$visitorSummary['views_today']['previous']"
                    :delta="$visitorSummary['views_today']['delta']"
                    icon="eye"
                    caption="Total halaman dibuka" />

                <x-ui.stat-tile
                    label="Pengunjung 7 hari"
                    :value="$visitorSummary['visitors_7d']['value']"
                    :previous="$visitorSummary['visitors_7d']['previous']"
                    :delta="$visitorSummary['visitors_7d']['delta']"
                    icon="activity" />

                <x-ui.stat-tile
                    label="Kunjungan 30 hari"
                    :value="$visitorSummary['views_30d']['value']"
                    :previous="$visitorSummary['views_30d']['previous']"
                    :delta="$visitorSummary['views_30d']['delta']"
                    icon="history" />
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <x-ui.bar-chart
                        :series="$visitorDaily"
                        title="Pengunjung 14 hari terakhir"
                        description="Jumlah pengunjung unik per hari."
                        value-key="visitors"
                        secondary-key="views"
                        secondary-label="Kunjungan"
                        value-label="pengunjung" />
                </div>

                <div class="space-y-4">
                    <x-ui.card title="Halaman Terpopuler" description="30 hari terakhir.">
                        @if ($topPages->isEmpty())
                            <p class="py-6 text-center text-sm text-muted-foreground">Belum ada data.</p>
                        @else
                            <ol class="space-y-2.5">
                                @foreach ($topPages as $page)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 flex-1 truncate text-foreground" title="{{ $page['label'] }}">
                                            {{ $page['label'] }}
                                        </span>
                                        <span class="shrink-0 tabular-nums font-medium text-foreground">{{ number_format($page['views']) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-ui.card>

                    @if ($topReferrers->isNotEmpty())
                        <x-ui.card title="Sumber Rujukan" description="Hanya nama situsnya yang disimpan.">
                            <ol class="space-y-2.5">
                                @foreach ($topReferrers as $referrer)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 flex-1 truncate text-foreground">{{ $referrer['host'] }}</span>
                                        <span class="shrink-0 tabular-nums font-medium text-foreground">{{ number_format($referrer['views']) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </x-ui.card>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section aria-labelledby="latest-heading" class="mt-8">
        <h2 id="latest-heading" class="mb-3 text-base font-semibold text-foreground">Berita terbaru</h2>

        <div class="overflow-x-auto rounded-xl border border-border bg-card text-card-foreground shadow-sm">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <caption class="sr-only">Lima berita terakhir yang dibuat</caption>
                <thead class="border-b border-border bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Judul</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Penulis</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Dibuat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($latestNews as $news)
                        <tr>
                            <td class="px-4 py-3 font-medium text-foreground">{{ $news->title }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $news->author?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$news->status" /></td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $news->created_at?->translatedFormat('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Belum ada berita.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
