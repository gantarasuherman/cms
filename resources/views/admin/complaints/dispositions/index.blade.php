<x-layouts.admin title="Tujuan Pengarahan">
    <x-ui.page-header title="Tujuan Pengarahan"
                      description="Instansi yang dapat dituju petugas ketika sebuah pengaduan bukan kewenangan dinas ini.">
        <x-slot:actions>
            @can('create', App\Models\DispositionTarget::class)
                <x-ui.button :href="route('admin.dispositions.create')" icon="plus">Tambah Tujuan</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <ul class="divide-y divide-border">
            @forelse ($targets as $target)
                <li class="flex flex-wrap items-center gap-3 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            {{ $target->name }}
                            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ $target->phone }}</code>
                            @unless ($target->is_active)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Nonaktif</span>
                            @endunless
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            @if ($target->contact_person)
                                {{ $target->contact_person }} ·
                            @endif
                            {{ $target->description ?: 'Tanpa keterangan' }}
                            @if ($target->dispositions_count > 0)
                                · {{ $target->dispositions_count }} penerusan
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @can('update', $target)
                            <x-ui.button :href="route('admin.dispositions.edit', $target)" variant="secondary" icon="pencil">Ubah</x-ui.button>
                        @endcan
                        @can('delete', $target)
                            <x-ui.delete-form :action="route('admin.dispositions.destroy', $target)" />
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-8 text-center text-sm text-muted-foreground">
                    Belum ada tujuan pengarahan. Petugas yang menemukan pengaduan di luar kewenangan
                    hanya dapat menolaknya.
                </li>
            @endforelse
        </ul>
    </x-ui.card>

    <x-ui.card title="Cara kerjanya di chat petugas" class="mt-6">
        <p class="text-sm text-muted-foreground">
            Satu perintah, satu pertanyaan:
        </p>
        <ol class="mt-3 space-y-1.5 text-sm text-muted-foreground">
            <li><strong class="text-foreground">1.</strong> Petugas mengetik <code>/bukan ADU-XXXXXXXX</code></li>
            <li><strong class="text-foreground">2.</strong> Daftar instansi di atas muncul; petugas membalas angkanya</li>
        </ol>
        <p class="mt-3 text-sm text-muted-foreground">
            Pengaduannya lalu berstatus <strong class="text-foreground">Bukan Kewenangan Dinas</strong> — bukan
            "Selesai", sebab belum tentu ada yang mengerjakannya, dan bukan "Ditolak", sebab laporannya sah.
        </p>
        <p class="mt-3 text-sm text-muted-foreground">
            Yang dikirim hanya <strong>satu</strong> pesan, dan itu kepada warga: laporannya bukan
            kewenangan dinas ini, beserta nama dan nomor instansi yang dapat dihubunginya sendiri.
        </p>
        <p class="mt-2 text-sm text-muted-foreground">
            <strong class="text-foreground">Bot tidak menghubungi instansi tujuan.</strong>
            Menghubungi instansi lain atas nama warga menjanjikan sesuatu yang tidak dapat dijamin
            dinas ini — kami tidak tahu apakah pesannya dibaca, apalagi ditindaklanjuti. Karena itu
            pula tidak ada biaya WhatsApp yang timbul dari langkah ini.
        </p>
    </x-ui.card>
</x-layouts.admin>
