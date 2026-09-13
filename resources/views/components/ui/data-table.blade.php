@props([
    'url',
    'columns' => [],
    'length' => 15,
    'searchable' => true,
    'caption' => null,
])

{{--
    Server-side table. Yajra produces the JSON (search/sort/paginate all run in
    SQL); resources/js/datatable.js is the transport and rendering layer.

    $columns: list of ['key' => 'title', 'label' => 'Judul', 'orderable' => true,
    'searchable' => true, 'raw' => false, 'class' => '']
--}}
<div data-datatable data-url="{{ $url }}" data-length="{{ $length }}"
     class="rounded-xl border border-border bg-card text-card-foreground shadow-sm">

    @if ($searchable || isset($filters))
        <div class="flex flex-wrap items-end gap-3 border-b border-border p-4">
            @if ($searchable)
                <div class="min-w-56 flex-1">
                    <label for="dt-search-{{ $id = uniqid() }}" class="mb-1.5 block text-xs font-medium text-muted-foreground">Cari</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-muted-foreground">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input type="search" id="dt-search-{{ $id }}" data-dt-search placeholder="Ketik untuk mencari…"
                               class="h-10 w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground">
                    </div>
                </div>
            @endif

            @isset($filters)
                {{ $filters }}
            @endisset
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full min-w-[44rem] text-left text-sm">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            <thead class="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col"
                            data-column="{{ $column['key'] }}"
                            data-orderable="{{ ($column['orderable'] ?? true) ? 'true' : 'false' }}"
                            data-searchable="{{ ($column['searchable'] ?? true) ? 'true' : 'false' }}"
                            data-raw="{{ ($column['raw'] ?? false) ? 'true' : 'false' }}"
                            class="px-4 py-3 font-semibold {{ $column['class'] ?? '' }}">
                            @if ($column['orderable'] ?? true)
                                <button type="button" class="inline-flex items-center gap-1 rounded hover:text-foreground">
                                    {{ $column['label'] }}
                                    <x-icon name="chevron-down" class="h-3.5 w-3.5 opacity-50" />
                                </button>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody data-dt-body aria-live="polite" aria-busy="true" class="divide-y divide-border">
                <tr><td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-muted-foreground">Memuat data…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
        <p data-dt-info class="text-sm text-muted-foreground"></p>
        <nav data-dt-pagination aria-label="Navigasi halaman tabel"></nav>
    </div>
</div>
