<x-layouts.admin title="Media Sosial">
    <x-ui.page-header title="Media Sosial" description="Tambahkan jaringan apa pun — daftarnya tidak dibatasi sistem." />

    @include('admin.settings.partials.tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Tautan Aktif">
                @if ($links->isEmpty())
                    <p class="py-8 text-center text-sm text-muted-foreground">Belum ada tautan.</p>
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($links as $item)
                            <li class="flex items-center gap-3 py-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                                    <x-icon :name="$item->icon ?: 'link'" class="h-4.5 w-4.5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-foreground">{{ $item->label ?: $item->platform }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ $item->url }}</p>
                                </div>

                                <x-status-badge :status="$item->is_active ? 'active' : 'inactive'" />

                                <div class="flex shrink-0 items-center gap-1">
                                    @can('update', $item)
                                        <a href="{{ route('admin.settings.social.edit', $item) }}"
                                           class="rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">Ubah</a>
                                    @endcan
                                    @can('delete', $item)
                                        <x-ui.delete-form :action="route('admin.settings.social.destroy', $item)" />
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <form method="POST" action="{{ route('admin.settings.social.store') }}">
            @csrf
            <x-ui.card title="Tambah Tautan">
                <div class="space-y-5">
                    <x-ui.input label="Platform" name="platform" required
                                hint="Misalnya: Facebook, Instagram, TikTok, X." />
                    <x-ui.input label="Label" name="label" hint="Teks yang dibaca pengguna. Kosongkan untuk memakai nama platform." />
                    <x-ui.input label="URL" name="url" type="url" required placeholder="https://" />
                    <x-ui.icon-select name="icon" placeholder="— Ikon tautan umum —" />
                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0" value="{{ $links->count() * 10 + 10 }}" />
                    <x-ui.checkbox label="Aktif" name="is_active" :checked="true" />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="plus">Tambah</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
