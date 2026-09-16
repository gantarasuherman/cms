@php $editing = $recipient->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Petugas Penerima' : 'Tambah Petugas Penerima'">
    <x-ui.page-header :title="$editing ? 'Ubah Petugas Penerima' : 'Tambah Petugas Penerima'"
                      description="Pengaduan baru dikirimkan ke nomor ini untuk kategori yang dicentang.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.bot.recipients.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.bot.recipients.update', $recipient) : route('admin.bot.recipients.store') }}"
          class="max-w-2xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card title="Petugas">
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="old('name', $recipient->name)" required
                            hint="Tampil di daftar dan pada catatan riwayat. Contoh: Mantri Irigasi Sudimampir." />

                <x-ui.select label="Kanal" name="channel" :options="$channels"
                             :value="old('channel', $recipient->channel)" required
                             placeholder="Pilih kanal"
                             hint="Lewat mana kabar dikirim. Kanalnya harus sudah dikonfigurasi di Pengaturan Chatbot." />

                <x-ui.input label="Nomor Tujuan" name="destination" :value="old('destination', $recipient->destination)" required
                            hint="WhatsApp: format internasional tanpa tanda baca, contoh 6281324539882. Telegram: chat id (angka, boleh negatif untuk grup)." />
            </div>
        </x-ui.card>

        <x-ui.card title="Kategori yang Dipegang"
                   description="Hanya pengaduan pada kategori yang dicentang yang dikirimkan ke petugas ini.">
            @if ($categories->isEmpty())
                <p class="text-sm text-muted-foreground">Belum ada kategori pengaduan yang aktif.</p>
            @else
                {{-- fieldset/legend, bukan sekadar label di atas sekumpulan
                     kotak: pembaca layar perlu tahu kotak-kotak ini satu
                     kelompok pertanyaan, bukan pilihan yang berdiri sendiri. --}}
                <fieldset>
                    <legend class="sr-only">Kategori pengaduan yang dipegang petugas ini</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($categories as $category)
                            <label for="category-{{ $category->id }}"
                                   class="flex items-start gap-2.5 rounded-lg border border-border p-3 text-sm">
                                <input type="checkbox"
                                       id="category-{{ $category->id }}"
                                       name="categories[]"
                                       value="{{ $category->id }}"
                                       @checked(in_array($category->id, old('categories', $selected) ?? [], false))
                                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-input text-primary">
                                <span class="min-w-0 leading-relaxed">
                                    <span class="font-medium">{{ $category->name }}</span>
                                    @if ($category->description)
                                        <span class="mt-0.5 block text-xs text-muted-foreground">{{ $category->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @error('categories')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror
                @error('categories.*')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror
            @endif
        </x-ui.card>

        <x-ui.card>
            <div class="space-y-4">
                <x-ui.checkbox label="Aktif" name="is_active" :checked="old('is_active', $recipient->is_active ?? true)"
                               hint="Petugas nonaktif tidak menerima kabar apa pun." />

                <x-ui.checkbox label="Boleh memerintah dari chat" name="can_command"
                               :checked="old('can_command', $recipient->can_command ?? false)"
                               hint="Mengizinkan membalas /proses dan /selesai untuk mengubah status pengaduan langsung dari percakapan." />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route('admin.bot.recipients.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
