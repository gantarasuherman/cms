@php
    $editing = $user->exists;
    $isSelf = $editing && auth()->user()->is($user);
@endphp

<x-layouts.admin :title="$editing ? 'Ubah Pengguna' : 'Tambah Pengguna'">
    <x-ui.page-header :title="$editing ? 'Ubah Pengguna' : 'Tambah Pengguna'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.users.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
          class="grid max-w-4xl gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Akun">
                <div class="space-y-5">
                    <x-ui.input label="Nama" name="name" :value="$user->name" required autocomplete="name" />
                    <x-ui.input label="Email" name="email" type="email" :value="$user->email" required autocomplete="email" />

                    <x-ui.input label="{{ $editing ? 'Kata sandi baru' : 'Kata sandi' }}" name="password" type="password"
                                :required="! $editing" autocomplete="new-password"
                                hint="{{ $editing ? 'Kosongkan bila tidak ingin mengganti kata sandi.' : 'Minimal 8 karakter.' }}" />

                    <x-ui.input label="Ulangi kata sandi" name="password_confirmation" type="password"
                                :required="! $editing" autocomplete="new-password" />
                </div>

                <x-slot:footer>
                    <x-ui.button :href="route('admin.users.index')" variant="secondary">Batal</x-ui.button>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <x-ui.checkbox label="Akun aktif" name="is_active" :checked="$user->is_active ?? true"
                               hint="Akun nonaktif langsung dikeluarkan pada permintaan berikutnya." />
            </x-ui.card>

            <x-ui.card title="Peran">
                @if ($isSelf)
                    <p class="mb-3 flex items-start gap-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                        <x-icon name="triangle-alert" class="mt-0.5 h-4 w-4" />
                        Anda tidak dapat mengubah peran akun sendiri.
                    </p>
                @endif

                @foreach ($roles as $name)
                    <label class="flex items-center gap-2.5 py-1.5 text-sm text-foreground">
                        <input type="checkbox" name="roles[]" value="{{ $name }}"
                               @checked(in_array($name, old('roles', $assigned), true))
                               @disabled($isSelf)
                               class="h-4 w-4 rounded border-input text-foreground">
                        {{ $name }}
                    </label>
                @endforeach

                @error('roles')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
