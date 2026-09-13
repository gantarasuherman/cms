<x-layouts.admin title="Rincian Audit Log">
    <x-ui.page-header title="Rincian Audit Log">
        <x-slot:actions>
            <x-ui.button :href="route('admin.audit-logs.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Keterangan">
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'Waktu' => $log->created_at?->translatedFormat('d F Y H:i:s'),
                    'Pengguna' => $log->user?->name ?? 'Sistem',
                    'Tindakan' => $log->action,
                    'Modul' => $log->module,
                    'ID Data' => $log->record_id ?? '—',
                    'Alamat IP' => $log->ip ?? '—',
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                        <dd class="mt-0.5 text-foreground">{{ $value }}</dd>
                    </div>
                @endforeach

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Peramban</dt>
                    <dd class="mt-0.5 break-words text-xs text-muted-foreground">{{ $log->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Perubahan" class="lg:col-span-2">
            @php
                $keys = array_unique(array_merge(
                    array_keys($log->old_values ?? []),
                    array_keys($log->new_values ?? []),
                ));
            @endphp

            @if (! $keys)
                <p class="py-6 text-center text-sm text-muted-foreground">Tidak ada rincian perubahan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <caption class="sr-only">Nilai sebelum dan sesudah perubahan</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th scope="col" class="py-2 pr-3 font-semibold">Kolom</th>
                                <th scope="col" class="py-2 pr-3 font-semibold">Sebelum</th>
                                <th scope="col" class="py-2 font-semibold">Sesudah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($keys as $key)
                                @php
                                    $format = fn ($value) => match (true) {
                                        $value === null => '—',
                                        is_bool($value) => $value ? 'ya' : 'tidak',
                                        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
                                        default => (string) $value,
                                    };
                                @endphp
                                <tr>
                                    <th scope="row" class="py-2.5 pr-3 text-left align-top font-medium text-foreground">{{ $key }}</th>
                                    <td class="py-2.5 pr-3 align-top text-muted-foreground">
                                        <span class="break-words">{{ Str::limit($format($log->old_values[$key] ?? null), 200) }}</span>
                                    </td>
                                    <td class="py-2.5 align-top text-foreground">
                                        <span class="break-words">{{ Str::limit($format($log->new_values[$key] ?? null), 200) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
