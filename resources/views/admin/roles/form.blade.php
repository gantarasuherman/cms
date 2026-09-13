@php
    $editing = $role->exists;
    $isSuperAdmin = $role->name === 'Super Admin';
@endphp

<x-layouts.admin :title="$editing ? 'Ubah Peran' : 'Tambah Peran'">
    <x-ui.page-header
        :title="$editing ? 'Ubah Peran: '.$role->name : 'Tambah Peran'"
        description="Centang hak akses yang dimiliki peran ini. Penyimpanan divalidasi di server — kotak centang bukan pengamanan.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.roles.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($isSuperAdmin)
        <div role="note" class="mb-6 flex items-start gap-3 rounded-lg border border-border bg-muted px-4 py-3 text-sm text-foreground">
            <x-icon name="info" class="mt-0.5 h-4.5 w-4.5" />
            <p><strong>Super Admin</strong> selalu memiliki seluruh hak akses, termasuk modul yang ditambahkan kemudian. Peran ini tidak dapat diubah atau dihapus.</p>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
            <x-ui.card title="Identitas Peran">
                <x-ui.input label="Nama Peran" name="name" :value="$role->name" required class="max-w-md"
                            :disabled="$isSuperAdmin" />
            </x-ui.card>

            <x-ui.card title="Matriks Hak Akses"
                       description="Baris adalah modul, kolom adalah tindakan. Tanda — berarti tindakan itu tidak berlaku bagi modul tersebut.">
                <div data-permission-matrix>
                    <div class="mb-4 flex flex-wrap gap-2">
                        <x-ui.button type="button" variant="secondary" icon="check" data-matrix-all="1">Pilih semua</x-ui.button>
                        <x-ui.button type="button" variant="secondary" icon="x" data-matrix-all="0">Kosongkan semua</x-ui.button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <caption class="sr-only">Matriks hak akses per modul</caption>
                            <thead class="border-b border-border bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold">Modul</th>
                                    @foreach ($abilities as $ability)
                                        <th scope="col" class="px-4 py-3 text-center font-semibold">
                                            {{ App\Support\Permissions::abilityLabel($ability) }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-4 py-3 text-center font-semibold">Semua</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-border">
                                @foreach ($modules as $moduleName => $module)
                                    <tr data-matrix-row="{{ $moduleName }}">
                                        <th scope="row" class="px-4 py-3 text-left font-medium text-foreground">
                                            <span class="inline-flex items-center gap-2">
                                                <x-icon :name="$module['icon']" class="h-4 w-4 text-muted-foreground" />
                                                {{ $module['label'] }}
                                            </span>
                                        </th>

                                        @foreach ($abilities as $ability)
                                            @php
                                                $applies = in_array($ability, $module['abilities'], true);
                                                $permission = "$moduleName.$ability";
                                                $id = 'perm-'.str_replace('.', '-', $permission);
                                            @endphp
                                            <td class="px-4 py-3 text-center">
                                                @if ($applies)
                                                    <input type="checkbox" id="{{ $id }}" name="permissions[]"
                                                           value="{{ $permission }}"
                                                           @checked(in_array($permission, old('permissions', $granted), true))
                                                           @disabled($isSuperAdmin)
                                                           data-matrix-checkbox
                                                           class="h-4 w-4 rounded border-input text-foreground">
                                                    <label for="{{ $id }}" class="sr-only">
                                                        {{ App\Support\Permissions::abilityLabel($ability) }} {{ $module['label'] }}
                                                    </label>
                                                @else
                                                    <span class="text-muted-foreground" aria-label="Tidak berlaku">—</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" data-matrix-row-toggle
                                                   @disabled($isSuperAdmin)
                                                   aria-label="Pilih semua hak akses {{ $module['label'] }}"
                                                   class="h-4 w-4 rounded border-input text-foreground">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <x-slot:footer>
                    <x-ui.button :href="route('admin.roles.index')" variant="secondary">Batal</x-ui.button>
                    @unless ($isSuperAdmin)
                        <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                    @endunless
                </x-slot:footer>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
