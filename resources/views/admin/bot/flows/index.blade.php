<x-layouts.admin title="Alur Percakapan">
    <x-ui.page-header title="Alur Percakapan"
                      description="Susun percakapan bot secara visual. Alur bawaan dipakai kanal yang tidak memilih alurnya sendiri." />

    @can('create', App\Models\Bot\BotFlow::class)
        <x-ui.card title="Alur Baru" class="mb-6">
            <form method="POST" action="{{ route('admin.bot.flows.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <x-ui.input label="Nama Alur" name="name" required placeholder="Alur Pengaduan Jalan" />
                </div>
                <x-ui.button type="submit" icon="plus">Buat</x-ui.button>
            </form>
        </x-ui.card>
    @endcan

    <x-ui.card>
        <ul class="divide-y divide-border">
            @forelse ($flows as $flow)
                <li class="flex flex-wrap items-center gap-3 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium">
                            {{ $flow->name }}
                            @if ($flow->is_default)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Bawaan</span>
                            @endif
                            @if ($flow->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">Aktif</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ $flow->nodes_count }} node · {{ $flow->edges_count }} sambungan · versi {{ $flow->version }}
                            @if ($flow->published_at) · terbit {{ $flow->published_at->diffForHumans() }} @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @can('update', $flow)
                            <x-ui.button :href="route('admin.bot.flows.edit', $flow)" variant="secondary" icon="pencil">Susun</x-ui.button>

                            @unless ($flow->is_default)
                                <form method="POST" action="{{ route('admin.bot.flows.default', $flow) }}" class="inline">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" icon="star">Jadikan Bawaan</x-ui.button>
                                </form>
                            @endunless
                        @endcan

                        @can('delete', $flow)
                            @unless ($flow->is_default)
                                <x-ui.delete-form :action="route('admin.bot.flows.destroy', $flow)" />
                            @endunless
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-8 text-center text-sm text-muted-foreground">Belum ada alur.</li>
            @endforelse
        </ul>
    </x-ui.card>
</x-layouts.admin>
