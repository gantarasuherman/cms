<x-layouts.admin title="Jenis Pengaduan">
    <x-ui.page-header title="Jenis Pengaduan"
                      description="Pilihan yang ditawarkan chatbot, dan apa yang harus dibawa pelapor sebelum laporannya diterima.">
        <x-slot:actions>
            @can('create', App\Models\ComplaintCategory::class)
                <x-ui.button :href="route('admin.complaint-categories.create')" icon="plus">Tambah Jenis</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Jenis pengaduan beserta syarat bukti dan pemakaiannya</caption>
                <thead class="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th scope="col" class="py-2.5 pr-3 font-medium">Jenis</th>
                        <th scope="col" class="py-2.5 pr-3 font-medium">Syarat bukti</th>
                        <th scope="col" class="py-2.5 pr-3 font-medium">Petugas</th>
                        <th scope="col" class="py-2.5 pr-3 font-medium">Pengaduan</th>
                        <th scope="col" class="py-2.5"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @forelse ($categories as $category)
                        <tr>
                            <th scope="row" class="py-3 pr-3 text-left font-normal">
                                <span class="flex items-center gap-2 font-medium">
                                    @if ($category->icon)
                                        <x-icon :name="$category->icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    @endif
                                    {{ $category->name }}
                                    @unless ($category->is_active)
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Nonaktif</span>
                                    @endunless
                                </span>
                                @if ($category->description)
                                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ $category->description }}</span>
                                @endif
                            </th>

                            <td class="py-3 pr-3">
                                {{-- Dinyatakan dengan kata, bukan hanya ikon centang: "apa yang
                                     akan diminta bot" adalah hal yang paling sering salah dikira. --}}
                                @php $needs = $category->evidenceRequired(); @endphp

                                @if ($needs === [])
                                    <span class="text-muted-foreground">Cukup uraian</span>
                                @else
                                    <span class="flex flex-wrap gap-1.5">
                                        @if ($category->requires_photo)
                                            <span class="rounded bg-muted px-1.5 py-0.5 text-xs">Foto</span>
                                        @endif
                                        @if ($category->requires_location)
                                            <span class="rounded bg-muted px-1.5 py-0.5 text-xs">Titik lokasi</span>
                                        @endif
                                    </span>
                                @endif
                            </td>

                            <td class="py-3 pr-3 tabular-nums">
                                @if ($category->recipients_count === 0)
                                    <span class="text-amber-700 dark:text-amber-500">belum ada</span>
                                @else
                                    {{ $category->recipients_count }}
                                @endif
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-muted-foreground">{{ $category->complaints_count }}</td>

                            <td class="py-3">
                                <span class="flex items-center justify-end gap-2">
                                    @can('update', $category)
                                        <x-ui.button :href="route('admin.complaint-categories.edit', $category)" variant="secondary" icon="pencil">Ubah</x-ui.button>
                                    @endcan
                                    @can('delete', $category)
                                        <x-ui.delete-form :action="route('admin.complaint-categories.destroy', $category)" />
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-sm text-muted-foreground">
                                Belum ada jenis pengaduan. Chatbot tidak punya pilihan untuk ditawarkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <p class="mt-4 text-sm text-muted-foreground">
        Urutan di tabel ini adalah urutan nomor yang dibalas warga di chat.
        Menyisipkan jenis baru di tengah menggeser nomor sesudahnya — termasuk
        bagi orang yang sedang berada di tengah percakapan.
    </p>
</x-layouts.admin>
