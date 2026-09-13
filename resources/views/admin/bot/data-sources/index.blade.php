<x-layouts.admin title="Sumber Data Percakapan">
    <x-ui.page-header title="Sumber Data Percakapan"
                      description="Dari mana bot mengambil jawabannya. Isi yang ditampilkan mengikuti apa yang terbit di situs — tidak ada salinan terpisah.">
        <x-slot:actions>
            @can('create', App\Models\Bot\BotDataSource::class)
                <x-ui.button :href="route('admin.bot.data-sources.create')" icon="plus">Tambah Sumber</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <ul class="divide-y divide-border">
            @forelse ($sources as $source)
                <li class="flex flex-wrap items-center gap-3 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium">
                            {{ $source->name }}
                            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ $source->slug }}</code>
                            @unless ($source->is_active)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Nonaktif</span>
                            @endunless
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ $source->sourceLabel() }} · maksimal {{ $source->limit }} item
                            @if ($count = $usage[$source->slug] ?? 0)
                                · dipakai {{ $count }} node
                            @else
                                · belum dipakai alur mana pun
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @can('update', $source)
                            <x-ui.button :href="route('admin.bot.data-sources.edit', $source)" variant="secondary" icon="pencil">Ubah</x-ui.button>
                        @endcan
                        @can('delete', $source)
                            <x-ui.delete-form :action="route('admin.bot.data-sources.destroy', $source)" />
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-8 text-center text-sm text-muted-foreground">Belum ada sumber data.</li>
            @endforelse
        </ul>
    </x-ui.card>
</x-layouts.admin>
