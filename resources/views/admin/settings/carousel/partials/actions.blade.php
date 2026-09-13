<div class="flex items-center justify-end gap-1">
    @can('update', $slide)
        <form method="POST" action="{{ route('admin.settings.carousel.toggle', $slide) }}" class="inline">
            @csrf @method('PATCH')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                <x-icon :name="$slide->is_active ? 'eye-off' : 'eye'" class="h-4 w-4" />
                <span>{{ $slide->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
            </button>
        </form>
    @endcan

    @can('create', App\Models\CarouselSlide::class)
        <form method="POST" action="{{ route('admin.settings.carousel.duplicate', $slide) }}" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                <x-icon name="copy" class="h-4 w-4" /><span>Duplikat</span>
            </button>
        </form>
    @endcan

    @can('update', $slide)
        <a href="{{ route('admin.settings.carousel.edit', $slide) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $slide)
        <x-ui.delete-form :action="route('admin.settings.carousel.destroy', $slide)" />
    @endcan
</div>
