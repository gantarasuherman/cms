<x-layouts.admin title="Unggahan Media Sosial">
    <x-ui.page-header title="Unggahan Media Sosial"
                      description="Tampil di beranda sebagai galeri yang menautkan ke unggahan aslinya.">
        <x-slot:actions>
            @can('update', new App\Models\SocialPost())
                <form method="POST" action="{{ route('admin.social-posts.sync-all') }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="loader-circle">Sinkronkan Semua</x-ui.button>
                </form>
            @endcan
            @can('create', App\Models\SocialPost::class)
                <x-ui.button :href="route('admin.social-posts.create')" icon="plus">Tambah Unggahan</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @can('create', App\Models\SocialPost::class)
        <x-ui.card title="Tambah dari Tautan"
                   description="Tempelkan tautan unggahannya. Gambar, nama akun, keterangan, tanggal, suka, dan komentar diambil sendiri."
                   class="mb-6">
            <form method="POST" action="{{ route('admin.social-posts.fetch') }}"
                  class="flex flex-col gap-3 sm:flex-row sm:items-start">
                @csrf
                <div class="flex-1">
                    <label for="fetch-permalink" class="sr-only">Tautan unggahan</label>
                    <input type="url" id="fetch-permalink" name="permalink" required
                           value="{{ old('permalink') }}"
                           placeholder="https://www.instagram.com/p/DdLkhm_k23y/"
                           @error('permalink') aria-invalid="true" @enderror
                           class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
                    @error('permalink')
                        <p class="mt-1.5 text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.button type="submit" icon="download" class="sm:mt-0">Ambil Data</x-ui.button>
            </form>

            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Berlaku untuk unggahan <strong>akun Anda sendiri</strong> pada platform yang tokennya sudah
                dipasang. Unggahan milik akun lain tidak dapat dibaca metriknya — itu batas dari Meta,
                bukan dari sistem ini. Untuk kasus itu, isikan datanya secara manual lewat
                <a href="{{ route('admin.social-posts.create') }}" class="underline underline-offset-2">formulir biasa</a>.
            </p>
        </x-ui.card>
    @endcan

    <x-ui.card title="Sinkronisasi Angka"
               description="Angka suka, komentar, dan nama akun dibaca ulang dari platform asalnya setiap jam oleh server — bukan oleh peramban pengunjung."
               class="mb-6">
        @unless ($syncEnabled)
            <p class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                Sinkronisasi sedang dimatikan lewat <code>SOCIAL_SYNC_ENABLED=false</code>.
            </p>
        @endunless

        <ul class="space-y-3">
            @foreach ($providers as $platform => $provider)
                <li class="flex flex-wrap items-start gap-x-3 gap-y-1">
                    <span class="w-28 shrink-0 text-sm font-medium">{{ App\Models\SocialPost::PLATFORMS[$platform] ?? $platform }}</span>

                    @if ($provider->configured())
                        <span class="inline-flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400">
                            <x-icon name="circle-check" class="h-4 w-4" />Siap
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                            <x-icon name="circle-slash" class="h-4 w-4" />Manual
                        </span>
                        <span class="w-full text-xs text-muted-foreground sm:w-auto sm:flex-1">{{ $provider->requirement() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            Token disimpan di berkas <code>.env</code>, bukan di panel ini. Setiap perubahan setelan
            dicatat audit log lengkap dengan nilai lama dan barunya — token yang diketik di sebuah
            formulir akan ikut tersimpan di sana dalam bentuk terbaca.
        </p>
    </x-ui.card>

    <x-ui.data-table
        :url="route('admin.social-posts.data')"
        caption="Daftar unggahan media sosial"
        :columns="[
            ['key' => 'preview', 'label' => 'Gambar', 'orderable' => false, 'searchable' => false, 'raw' => true],
            ['key' => 'platform', 'label' => 'Platform'],
            ['key' => 'account', 'label' => 'Akun', 'orderable' => false],
            ['key' => 'caption', 'label' => 'Keterangan', 'orderable' => false],
            ['key' => 'engagement', 'label' => 'Suka / Komentar', 'raw' => true, 'orderable' => false, 'searchable' => false],
            ['key' => 'sync', 'label' => 'Sinkron', 'raw' => true, 'orderable' => false, 'searchable' => false],
            ['key' => 'status', 'label' => 'Status', 'raw' => true, 'searchable' => false, 'orderable' => false],
            ['key' => 'sort_order', 'label' => 'Urutan'],
            ['key' => 'posted_at', 'label' => 'Tanggal Unggah'],
            ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label for="filter-platform" class="mb-1.5 block text-xs font-medium text-muted-foreground">Platform</label>
                <select id="filter-platform" data-dt-filter="platform"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    @foreach (App\Models\SocialPost::PLATFORMS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">Status</label>
                <select id="filter-status" data-dt-filter="status"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                    <option value="">Semua</option>
                    <option value="active">Tampil</option>
                    <option value="inactive">Disembunyikan</option>
                </select>
            </div>
        </x-slot:filters>
    </x-ui.data-table>

    @can('update', new App\Models\SocialPost())
        <x-ui.card title="Urutan Tampil" description="Seret untuk menyusun ulang, atau gunakan tombol naik/turun." class="mt-6">
            @if ($posts->isEmpty())
                <p class="py-6 text-center text-sm text-muted-foreground">Belum ada unggahan.</p>
            @else
                <ol data-sortable data-reorder-url="{{ route('admin.social-posts.reorder') }}" class="space-y-2">
                    @foreach ($posts as $post)
                        <li data-sortable-item data-id="{{ $post->id }}" draggable="true"
                            class="flex items-center gap-3 rounded-lg border border-border bg-card p-3">
                            <span class="cursor-grab text-muted-foreground" aria-hidden="true">
                                <x-icon name="grip-vertical" class="h-5 w-5" />
                            </span>

                            <img src="{{ $post->imageUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover">

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $post->platformLabel() }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ $post->excerpt(70) ?: $post->permalink }}</p>
                            </div>

                            <x-status-badge :status="$post->is_active ? 'active' : 'inactive'" />

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" data-sortable-up aria-label="Naikkan unggahan {{ $post->platformLabel() }}"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-accent disabled:opacity-30">
                                    <x-icon name="chevron-up" class="h-4 w-4" />
                                </button>
                                <button type="button" data-sortable-down aria-label="Turunkan unggahan {{ $post->platformLabel() }}"
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
