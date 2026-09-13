<div class="flex items-center justify-end gap-1">
    <a href="{{ route('public.documents.download', $document->slug) }}"
       class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-muted">
        <x-icon name="download" class="h-4 w-4" /><span>Unduh</span>
    </a>

    @can('update', $document)
        <a href="{{ route('admin.documents.edit', $document) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $document)
        <x-ui.delete-form :action="route('admin.documents.destroy', $document)" />
    @endcan
</div>
