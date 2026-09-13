<x-layouts.admin title="Hak Akses">
    <x-ui.page-header title="Hak Akses"
                      description="Seluruh hak akses yang diperiksa sistem, beserta peran yang memilikinya." />

    <p class="mb-4 flex items-start gap-2 text-sm text-muted-foreground">
        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>
            Daftar ini hanya dibaca — hak akses adalah kosakata yang diperiksa kode.
            Yang dapat diubah adalah <a href="{{ route('admin.roles.index') }}" class="font-medium text-foreground underline underline-offset-4">peran</a>.
        </span>
    </p>

    <div class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <caption class="sr-only">Hak akses per modul beserta peran yang memilikinya</caption>

                <thead class="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-2.5">Hak Akses</th>
                        <th scope="col" class="px-4 py-2.5">Dimiliki Peran</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @foreach ($modules as $moduleName => $module)
                        {{-- One row per module as a group header, so the table stays
                             one scan column instead of thirteen separate cards. --}}
                        <tr class="bg-muted/30">
                            <th scope="colgroup" colspan="2" class="px-4 py-2 text-left">
                                <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <x-icon :name="$module['icon']" class="h-3.5 w-3.5" />
                                    {{ $module['label'] }}
                                </span>
                            </th>
                        </tr>

                        @foreach ($module['abilities'] as $ability)
                            @php
                                $permission = "$moduleName.$ability";
                                $roles = $holders[$permission] ?? [];
                            @endphp
                            <tr>
                                <th scope="row" class="px-4 py-2.5 text-left font-normal">
                                    <code class="text-xs">{{ $permission }}</code>
                                </th>
                                <td class="px-4 py-2.5">
                                    @if ($roles)
                                        <span class="flex flex-wrap gap-1">
                                            @foreach ($roles as $role)
                                                <span class="rounded border border-border px-1.5 py-0.5 text-xs text-muted-foreground">{{ $role }}</span>
                                            @endforeach
                                        </span>
                                    @else
                                        <span class="text-xs text-muted-foreground">Belum dimiliki peran mana pun</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
