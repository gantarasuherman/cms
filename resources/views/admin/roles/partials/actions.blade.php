<div class="flex items-center justify-end gap-1">
    @can('update', $role)
        <a href="{{ route('admin.roles.edit', $role) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @else
        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-sm text-muted-foreground">
            <x-icon name="lock" class="h-4 w-4" /><span>Terkunci</span>
        </span>
    @endcan

    @can('delete', $role)
        <x-ui.delete-form :action="route('admin.roles.destroy', $role)"
                          confirm="Hapus peran ini? Pengguna yang memakainya akan kehilangan hak aksesnya." />
    @endcan
</div>
