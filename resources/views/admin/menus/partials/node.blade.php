@php
    $children = $item->loadedChildren();
    $indent = match (min($depth, 3)) { 0 => 'pl-0', 1 => 'pl-6', 2 => 'pl-12', default => 'pl-16' };
@endphp

<li class="{{ $indent }}">
    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
            <x-icon :name="$item->icon ?: 'circle'" class="h-4.5 w-4.5" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="font-medium text-foreground">{{ $item->title }}</p>
            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                @if ($item->route)
                    <code>{{ $item->route }}</code>
                @elseif ($item->url)
                    {{ $item->url }}
                @else
                    <span class="italic">grup — tanpa tautan</span>
                @endif

                @if ($kind === 'admin' && $item->permission)
                    <span class="ml-1 text-muted-foreground">· {{ $item->permission }}</span>
                @endif
            </p>
        </div>

        <span class="text-xs text-muted-foreground">#{{ $item->sort_order }}</span>
        <x-status-badge :status="$item->is_active ? 'active' : 'inactive'" />

        <div class="flex shrink-0 items-center gap-1">
            @can('update', $item)
                <a href="{{ route($routeBase.'.edit', $item) }}"
                   class="rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">Ubah</a>
            @endcan

            @can('delete', $item)
                <x-ui.delete-form :action="route($routeBase.'.destroy', $item)"
                                  confirm="Hapus menu ini beserta seluruh sub-menunya?" />
            @endcan
        </div>
    </div>

    @if ($children->isNotEmpty())
        <ul class="mt-1 space-y-1">
            @foreach ($children as $child)
                @include('admin.menus.partials.node', ['item' => $child, 'depth' => $depth + 1, 'routeBase' => $routeBase, 'kind' => $kind])
            @endforeach
        </ul>
    @endif
</li>
