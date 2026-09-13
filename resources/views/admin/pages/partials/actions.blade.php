<div class="flex items-center justify-end gap-1">
    @if ($page->isPublished())
        <a href="{{ route('public.pages.show', $page->slug) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-muted">
            <x-icon name="external-link" class="h-4 w-4" /><span>Lihat</span>
        </a>
    @endif

    @can('update', $page)
        <a href="{{ route('admin.pages.edit', $page) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $page)
        <x-ui.delete-form :action="route('admin.pages.destroy', $page)" />
    @endcan
</div>
