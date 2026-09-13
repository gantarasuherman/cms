<div class="flex items-center justify-end gap-1">
    @can('update', $announcement)
        <form method="POST" action="{{ route('admin.announcements.toggle', $announcement) }}" class="inline">
            @csrf @method('PATCH')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                <x-icon :name="$announcement->is_active ? 'eye-off' : 'eye'" class="h-4 w-4" />
                <span>{{ $announcement->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
            </button>
        </form>
    @endcan

    @can('update', $announcement)
        <a href="{{ route('admin.announcements.edit', $announcement) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $announcement)
        <x-ui.delete-form :action="route('admin.announcements.destroy', $announcement)" />
    @endcan
</div>
