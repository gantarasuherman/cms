<x-layouts.admin title="Petugas Penerima Pengaduan">
    <x-ui.page-header title="Petugas Penerima Pengaduan"
                      description="Siapa yang dikabari lewat WhatsApp atau Telegram begitu pengaduan baru masuk, dan untuk kategori apa saja.">
        <x-slot:actions>
            @can('create', App\Models\Bot\BotRecipient::class)
                <x-ui.button :href="route('admin.bot.recipients.create')" icon="plus">Tambah Petugas</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Kategori tanpa petugas tidak terlihat dari daftar di bawah, padahal
         justru itu keadaan yang merugikan: pengaduannya masuk, tersimpan, dan
         tidak ada seorang pun yang diberi tahu. --}}
    @if ($uncovered->isNotEmpty())
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700/60 dark:bg-amber-950/40">
            <p class="flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-200">
                <x-icon name="triangle-alert" class="h-4 w-4" />
                Belum ada petugas untuk {{ $uncovered->count() }} kategori
            </p>
            <p class="mt-1 text-amber-800 dark:text-amber-300">
                Pengaduan pada kategori
                <strong>{{ $uncovered->pluck('name')->join(', ', ' dan ') }}</strong>
                tetap tersimpan di panel, tetapi tidak ada yang menerima kabarnya.
            </p>
        </div>
    @endif

    <x-ui.card>
        <ul class="divide-y divide-border">
            @forelse ($recipients as $recipient)
                <li class="flex flex-wrap items-center gap-3 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            {{ $recipient->name }}
                            <span class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ $recipient->channelLabel() }}</span>
                            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ $recipient->destination }}</code>
                            @unless ($recipient->is_active)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Nonaktif</span>
                            @endunless
                            @if ($recipient->can_command)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Boleh memerintah</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            @if ($recipient->categories->isEmpty())
                                Belum memegang kategori apa pun — tidak akan menerima kabar.
                            @else
                                {{ $recipient->categories->pluck('name')->join(', ') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @can('update', $recipient)
                            <x-ui.button :href="route('admin.bot.recipients.edit', $recipient)" variant="secondary" icon="pencil">Ubah</x-ui.button>
                        @endcan
                        @can('delete', $recipient)
                            <x-ui.delete-form :action="route('admin.bot.recipients.destroy', $recipient)" />
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-8 text-center text-sm text-muted-foreground">
                    Belum ada petugas penerima. Selama daftar ini kosong, pengaduan baru hanya muncul di panel admin.
                </li>
            @endforelse
        </ul>
    </x-ui.card>
</x-layouts.admin>
