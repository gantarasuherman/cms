<div class="flex items-center justify-end gap-1">
    <a href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer"
       class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
        <x-icon name="external-link" class="h-4 w-4" /><span>Asli</span>
    </a>

    @can('update', $post)
        <form method="POST" action="{{ route('admin.social-posts.sync', $post) }}" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                <x-icon name="loader-circle" class="h-4 w-4" /><span>Sinkron</span>
            </button>
        </form>

        <form method="POST" action="{{ route('admin.social-posts.toggle', $post) }}" class="inline">
            @csrf @method('PATCH')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                <x-icon :name="$post->is_active ? 'eye-off' : 'eye'" class="h-4 w-4" />
                <span>{{ $post->is_active ? 'Sembunyikan' : 'Tampilkan' }}</span>
            </button>
        </form>

        <a href="{{ route('admin.social-posts.edit', $post) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
            <x-icon name="pencil" class="h-4 w-4" /><span>Ubah</span>
        </a>
    @endcan

    @can('delete', $post)
        <x-ui.delete-form :action="route('admin.social-posts.destroy', $post)" />
    @endcan
</div>
