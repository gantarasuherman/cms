<div class="flex items-center justify-end gap-1">
    @can('update', $service)
        <a href="{{ route('admin.services.edit', $service) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $service)
        <x-ui.delete-form :action="route('admin.services.destroy', $service)" />
    @endcan
</div>
