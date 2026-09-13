<x-layouts.admin title="Beranda">
    <x-ui.page-header title="Bagian Beranda" description="Susun bagian beranda. Seret untuk mengubah urutan, atau gunakan tombol naik/turun.">
        <x-slot:actions>
            @can('create', App\Models\HomepageSection::class)
                <x-ui.button :href="route('admin.settings.homepage.create')" icon="plus">Tambah Bagian</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.settings.partials.tabs')

    @if ($sections->isEmpty())
        <x-ui.card>
            <p class="py-10 text-center text-sm text-muted-foreground">Belum ada bagian beranda.</p>
        </x-ui.card>
    @else
        <x-ui.card>
            {{-- Reordering works by dragging *and* by the buttons on each row,
                 because drag-and-drop alone cannot be operated from a keyboard. --}}
            <ol data-sortable
                data-reorder-url="{{ route('admin.settings.homepage.reorder') }}"
                class="space-y-2">
                @foreach ($sections as $section)
                    <li data-sortable-item data-id="{{ $section->id }}" draggable="true"
                        class="flex items-center gap-3 rounded-lg border border-border bg-card p-3 transition-shadow">
                        <span class="cursor-grab text-muted-foreground" aria-hidden="true">
                            <x-icon name="grip-vertical" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-foreground">{{ $section->title ?: $types[$section->type] ?? $section->type }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <code>{{ $section->type }}</code>
                                @if ($section->setting('limit'))
                                    &middot; menampilkan {{ $section->setting('limit') }} item
                                @endif
                            </p>
                        </div>

                        <x-status-badge :status="$section->is_active ? 'active' : 'inactive'" />

                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" data-sortable-up
                                    aria-label="Naikkan {{ $section->title ?: $section->type }}"
                                    class="rounded-md p-1.5 text-muted-foreground hover:bg-muted disabled:opacity-30">
                                <x-icon name="chevron-up" class="h-4 w-4" />
                            </button>
                            <button type="button" data-sortable-down
                                    aria-label="Turunkan {{ $section->title ?: $section->type }}"
                                    class="rounded-md p-1.5 text-muted-foreground hover:bg-muted disabled:opacity-30">
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </button>

                            @can('update', $section)
                                <a href="{{ route('admin.settings.homepage.edit', $section) }}"
                                   class="rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">Ubah</a>
                            @endcan

                            @can('delete', $section)
                                <x-ui.delete-form :action="route('admin.settings.homepage.destroy', $section)" />
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ol>

            {{-- Announced to assistive technology after a reorder is saved. --}}
            <p data-sortable-status role="status" aria-live="polite" class="mt-3 text-sm text-muted-foreground"></p>
        </x-ui.card>
    @endif
</x-layouts.admin>
