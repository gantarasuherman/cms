@php
    $meta = [
        'saveUrl' => route('admin.bot.flows.save', $flow),
        'types' => $nodeTypes,
        'inputKinds' => $inputKinds,
        'actions' => $actions,
        'actionNames' => $actionNames,
        'retryBehaviours' => $retryBehaviours,
        'dataSources' => $dataSources,
        // Dari mana sebuah menu mengambil pilihannya. "Ditulis sendiri"
        // memakai daftar yang diketik di panel; pilihan lainnya membaca tabel,
        // sehingga menambah jenis pengaduan langsung menambah pilihan di chat
        // tanpa alurnya perlu disunting lagi.
        'optionSources' => [
            '' => 'Ditulis sendiri di bawah',
            'complaint_categories' => 'Jenis Pengaduan ('.$categoryCount.' aktif)',
        ],
    ];
@endphp

<x-layouts.admin :title="'Susun · '.$flow->name">
    <x-ui.page-header :title="$flow->name" description="Seret node untuk menyusun. Klik sebuah node untuk mengubah isinya.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.bot.flows.publish', $flow) }}" class="inline">
                @csrf
                <x-ui.button type="submit" icon="upload">Terbitkan</x-ui.button>
            </form>
            <x-ui.button :href="route('admin.bot.flows.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div data-flow-editor
         data-flow-meta='@json($meta)'
         data-flow-graph='@json($graph)'
         class="space-y-4">

        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-3">
            <span class="mr-1 text-sm font-medium text-muted-foreground">Tambah:</span>

            @foreach ($nodeTypes as $key => $type)
                @continue($key === 'start')
                <button type="button" data-flow-add="{{ $key }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                    <x-icon :name="$type['icon']" class="h-4 w-4" />{{ $type['label'] }}
                </button>
            @endforeach

            <span class="ml-auto flex items-center gap-2">
                <span data-flow-dirty hidden class="text-sm text-amber-700 dark:text-amber-400">Belum disimpan</span>

                <span class="flex items-center rounded-lg border border-border">
                    <button type="button" data-flow-undo disabled
                            aria-label="Batalkan (tidak ada yang bisa dibatalkan)"
                            class="rounded-l-lg px-2.5 py-1.5 text-sm font-medium hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        <x-icon name="undo-2" class="h-4 w-4" />
                    </button>
                    <button type="button" data-flow-redo disabled
                            aria-label="Ulangi (tidak ada yang bisa diulangi)"
                            class="rounded-r-lg border-l border-border px-2.5 py-1.5 text-sm font-medium hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        <x-icon name="redo-2" class="h-4 w-4" />
                    </button>
                </span>
                <x-ui.button type="button" data-flow-fit variant="secondary" icon="move">Rapikan Tampilan</x-ui.button>
                <x-ui.button type="button" data-flow-fullscreen variant="secondary" icon="maximize"
                             aria-pressed="false" aria-label="Tampilkan satu layar penuh">Layar Penuh</x-ui.button>
                <x-ui.button type="button" data-flow-save icon="save">Simpan</x-ui.button>
            </span>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            {{-- role="listbox" with focusable options: the canvas is a set of
                 things you choose between, which is what a screen reader needs
                 to be told before any of the keys below mean anything. --}}
            <div data-flow-canvas tabindex="0" role="listbox" aria-label="Kanvas alur percakapan"
                 aria-describedby="flow-help"
                 class="flow-canvas relative overflow-hidden rounded-xl border border-border bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <svg data-flow-edges class="pointer-events-none absolute inset-0 h-full w-full overflow-visible text-muted-foreground" aria-hidden="true"></svg>
                <div data-flow-nodes class="absolute inset-0"></div>
            </div>

            <div class="space-y-4">
                <x-ui.card title="Properti Node">
                    <div data-flow-panel></div>
                </x-ui.card>

                <x-ui.card title="Pemeriksaan">
                    <div data-flow-problems>
                        @if ($problems)
                            <ul class="space-y-2">
                                @foreach ($problems as $problem)
                                    <li class="text-sm {{ $problem['level'] === 'error' ? 'text-destructive' : 'text-amber-700 dark:text-amber-400' }}">
                                        @if ($problem['node'])<strong>{{ $problem['node'] }}</strong>: @endif{{ $problem['message'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-emerald-700 dark:text-emerald-400">Tidak ada masalah. Alur siap diterbitkan.</p>
                        @endif
                    </div>
                </x-ui.card>
            </div>
        </div>

        {{-- The colours are a convenience, never the only signal: every line
             also carries its condition as text. This says what they mean. --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-border bg-card px-3 py-2.5 text-sm">
            <span class="font-medium text-muted-foreground">Warna jalur:</span>
            @foreach ([
                '#059669' => 'valid — jawaban benar',
                '#e11d48' => 'invalid — jawaban ditolak',
                '#d97706' => 'exhausted — gagal berulang',
                '#2563eb' => 'angka — pilihan menu',
                '#7c3aed' => 'category — dari tabel',
                '#64748b' => 'back — kembali',
                '#94a3b8' => 'tanpa syarat',
            ] as $colour => $label)
                <span class="inline-flex items-center gap-1.5">
                    <span aria-hidden="true" class="inline-block h-0.5 w-5 rounded" style="background: {{ $colour }}"></span>
                    {{ $label }}
                </span>
            @endforeach
        </div>

        <p id="flow-help" class="text-sm text-muted-foreground">
            Seret titik di tepi node ke node lain untuk menyambungkan.
            Geser tampilan dengan menahan klik kiri pada latar kanvas — atau di mana saja sambil menahan
            <kbd class="rounded border border-border px-1">Spasi</kbd>.
            <kbd class="rounded border border-border px-1">Ctrl</kbd>+gulir untuk memperbesar.
            Dengan papan ketik: Tab untuk berpindah node, panah untuk memindahkan (Shift untuk langkah besar),
            <kbd class="rounded border border-border px-1">Enter</kbd> membuka propertinya,
            <kbd class="rounded border border-border px-1">C</kbd> mulai menyambung lalu Tab ke tujuan dan Enter,
            <kbd class="rounded border border-border px-1">Delete</kbd> menghapus.
            <kbd class="rounded border border-border px-1">Ctrl</kbd>+<kbd class="rounded border border-border px-1">Z</kbd>
            membatalkan, <kbd class="rounded border border-border px-1">Ctrl</kbd>+<kbd class="rounded border border-border px-1">Shift</kbd>+<kbd class="rounded border border-border px-1">Z</kbd> mengulangi.
        </p>

        <p data-flow-status role="status" aria-live="polite" class="sr-only"></p>

        {{-- The same graph as plain controls. Not a fallback bolted on:
             connecting boxes by dragging cannot be done with a keyboard alone,
             so this is how the editor is operable at all for some people. --}}
        <x-ui.card title="Daftar Node dan Sambungan"
                   description="Tampilan yang sama dalam bentuk daftar. Setiap perubahan di sini juga terlihat pada kanvas.">
            <ul data-flow-outline class="space-y-4"></ul>
        </x-ui.card>
    </div>
</x-layouts.admin>
