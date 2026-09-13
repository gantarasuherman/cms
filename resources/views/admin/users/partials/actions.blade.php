<div class="flex items-center justify-end gap-1">
    @can('update', $user)
        <a href="{{ route('admin.users.edit', $user) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $user)
        <x-ui.delete-form :action="route('admin.users.destroy', $user)" />
    @else
        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-sm text-muted-foreground">
            <x-icon name="lock" class="h-4 w-4" /><span>—</span>
        </span>
    @endcan
</div>
