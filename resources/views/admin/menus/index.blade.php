<x-layouts.admin :title="$heading">
    <x-ui.page-header :title="$heading"
                      description="Seluruh navigasi berasal dari tabel ini. Susun bertingkat sedalam yang dibutuhkan.">
        <x-slot:actions>
            @can('create', $tree->first() ?? new App\Models\AdminMenu())
                <x-ui.button :href="route($routeBase.'.create')" icon="plus">Tambah Menu</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($tree->isEmpty())
        <x-ui.card>
            <p class="py-10 text-center text-sm text-muted-foreground">Belum ada menu.</p>
        </x-ui.card>
    @else
        <x-ui.card>
            <ul class="space-y-1">
                @foreach ($tree as $item)
                    @include('admin.menus.partials.node', ['item' => $item, 'depth' => 0, 'routeBase' => $routeBase, 'kind' => $kind])
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</x-layouts.admin>
