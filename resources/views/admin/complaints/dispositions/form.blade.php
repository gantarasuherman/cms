@php $editing = $target->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Tujuan Pengarahan' : 'Tambah Tujuan Pengarahan'">
    <x-ui.page-header :title="$editing ? 'Ubah Tujuan Pengarahan' : 'Tambah Tujuan Pengarahan'"
                      description="Nomor instansi ini diberikan kepada warga ketika laporannya bukan kewenangan dinas ini.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.dispositions.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.dispositions.update', $target) : route('admin.dispositions.store') }}"
          class="max-w-2xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card title="Instansi">
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="old('name', $target->name)" required
                            hint="Tampil sebagai pilihan bernomor di chat petugas, dan disebut kepada warga. Contoh: Dinas Perhubungan." />

                <x-ui.input label="Nomor yang Dapat Dihubungi" name="phone"
                            :value="old('phone', $target->phone)" required
                            hint="Diberikan kepada warga, jadi tulis nomor yang memang dilayani. Angka saja." />

                <x-ui.input label="Narahubung" name="contact_person"
                            :value="old('contact_person', $target->contact_person)"
                            hint="Opsional. Ikut disebut kepada warga bila diisi." />

                <x-ui.input label="Keterangan Singkat" name="description"
                            :value="old('description', $target->description)"
                            hint="Tampil di samping namanya saat petugas memilih. Tidak dikirim ke warga. Contoh: lampu lalu lintas, rambu." />

                <x-ui.input label="Nomor Urut" name="sort_order" type="number" min="0"
                            :value="old('sort_order', $target->sort_order)" required
                            hint="Menentukan urutan pilihan di chat petugas." />
            </div>
        </x-ui.card>

        <x-ui.card title="Pesan ke Pelapor"
                   description="Satu-satunya pesan yang dikirim. Bot tidak menghubungi instansi tujuan.">
            <div class="space-y-5">
                <x-ui.textarea label="Bunyi Pesan" name="reporter_template" :rows="10"
                               :value="old('reporter_template', $target->reporter_template)"
                               hint="Kosongkan untuk memakai bunyi bawaan, yang sudah menyebut nama dan nomor instansi." />

                {{-- Penanda didaftar terbuka: sebuah templat dengan penanda yang
                     salah ketik akan terkirim apa adanya kepada warga, dan
                     tidak ada yang memeriksanya sebelum itu terjadi. --}}
                <div class="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                    <p class="font-medium">Penanda yang tersedia</p>
                    <ul class="mt-2 grid gap-1 text-muted-foreground sm:grid-cols-2">
                        <li><code>{ticket}</code> — nomor tiket</li>
                        <li><code>{category}</code> — jenis pengaduan</li>
                        <li><code>{description}</code> — uraian pelapor</li>
                        <li><code>{date}</code> — waktu laporan masuk</li>
                        <li><code>{target}</code> — nama instansi ini</li>
                        <li><code>{target_phone}</code> — nomornya saja</li>
                        <li><code>{target_contact}</code> — nama, nomor, narahubung</li>
                        <li><code>{site_name}</code> — nama dinas ini</li>
                    </ul>
                    <p class="mt-2 text-muted-foreground">
                        Tanda <code>*bintang*</code> menebalkan teks di WhatsApp.
                    </p>
                </div>

                {{-- Dikatakan di sini karena inilah yang paling mudah
                     disalahpahami tentang layar ini. --}}
                <div class="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">Bot tidak menghubungi instansi tujuan</p>
                    <p class="mt-1">
                        Yang dikirim hanyalah kabar kepada warga, berisi ke mana ia dapat menghubungi
                        sendiri. Menghubungi instansi lain atas nama warga menjanjikan sesuatu yang
                        tidak dapat dijamin dinas ini — kami tidak tahu apakah pesannya dibaca, apalagi
                        ditindaklanjuti.
                    </p>
                    <p class="mt-1">
                        Karena itu tidak ada biaya WhatsApp, tidak ada template Meta, dan tidak ada
                        jendela 24 jam yang perlu dipikirkan.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.checkbox label="Aktif" name="is_active" :checked="old('is_active', $target->is_active ?? true)"
                           hint="Tujuan nonaktif tidak muncul sebagai pilihan, tetapi pengarahan yang sudah terjadi tetap tercatat." />

            <x-slot:footer>
                <x-ui.button :href="route('admin.dispositions.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
