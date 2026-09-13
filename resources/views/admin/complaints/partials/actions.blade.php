<div class="flex items-center justify-end gap-1">
    <a href="{{ route('admin.complaints.show', $complaint) }}"
       class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
        <x-icon name="eye" class="h-4 w-4" /><span>Rincian</span>
    </a>

    @can('delete', $complaint)
        <x-ui.delete-form :action="route('admin.complaints.destroy', $complaint)" />
    @endcan
</div>
