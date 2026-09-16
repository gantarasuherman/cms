@php $editing = $category->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Jenis Pengaduan' : 'Tambah Jenis Pengaduan'">
    <x-ui.page-header :title="$editing ? 'Ubah Jenis Pengaduan' : 'Tambah Jenis Pengaduan'"
                      description="Syarat bukti di bawah langsung mengubah apa yang ditanyakan chatbot — alur percakapan tidak perlu disentuh.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.complaint-categories.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.complaint-categories.update', $category) : route('admin.complaint-categories.store') }}"
          class="max-w-2xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card title="Jenis">
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="old('name', $category->name)" required
                            hint="Tampil sebagai pilihan bernomor di chat. Contoh: Jembatan." />

                <x-ui.textarea label="Keterangan" name="description" :rows="2"
                               :value="old('description', $category->description)"
                               hint="Kalimat pendek yang menjelaskan jenis ini. Tampil di panel, bukan di chat." />

                <x-ui.icon-select :value="old('icon', $category->icon)" />

                <x-ui.color-input label="Warna Pin Peta" name="color"
                                  :value="old('color', $category->color ?: $category->pinColor())"
                                  hint="Warna titik jenis ini di Peta Pengaduan. Pin juga membawa ikon di atas, sehingga jenisnya tetap terbaca oleh mata yang tidak membedakan warna tertentu." />

                <x-ui.input label="Nomor Urut" name="sort_order" type="number" min="0"
                            :value="old('sort_order', $category->sort_order)" required
                            hint="Menentukan urutan pilihan di chat. Nilai kecil tampil lebih dulu." />

                @if ($editing)
                    <div class="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                        <p>Kode tetap: <code>{{ $category->slug }}</code></p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Tidak ikut berubah saat nama diganti — node alur percakapan merujuk
                            jenis ini dengan kode tersebut.
                        </p>
                    </div>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Nomor yang Diberikan ke Warga"
                   description="Dipakai chatbot ketika warga bertanya dan FAQ tidak menjawabnya: bidang ini dipilih, lalu nomor di bawah dikirimkan.">
            <div class="space-y-5">
                <x-ui.input label="Nama yang Disebut" name="contact_name"
                            :value="old('contact_name', $category->contact_name)"
                            hint="Sebut bidangnya, bukan nama orang — contoh: Bidang Bina Marga. Petugas berganti jauh lebih sering daripada nama bidang, sementara warga menyimpan pesan ini bertahun-tahun. Dikosongkan, nama jenis di atas yang dipakai." />

                <x-ui.input label="Nomor WhatsApp" name="contact_phone"
                            :value="old('contact_phone', $category->contact_phone)"
                            hint="Contoh: 628123456789. Dikosongkan, chatbot kembali ke cara lama untuk bidang ini: pertanyaannya diteruskan ke petugas dan warga diminta menunggu balasan." />

                <div role="note" class="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                    <x-icon name="triangle-alert" class="mt-0.5 h-4.5 w-4.5 shrink-0" />
                    <p>
                        Nomor ini <strong>disiarkan kepada siapa pun yang bertanya</strong>, dan berbeda dari
                        nomor petugas penerima pengaduan di <em>Petugas Penerima</em> — nomor itu hanya untuk
                        pemberitahuan internal dan tidak pernah dibagikan. Isi di sini nomor layanan bidang,
                        bukan nomor pribadi.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Syarat Bukti"
                   description="Apa yang harus dikirim pelapor sebelum chatbot mau mencatat laporannya.">
            <fieldset class="space-y-4">
                <legend class="sr-only">Bukti yang diwajibkan untuk jenis pengaduan ini</legend>

                <x-ui.checkbox label="Wajib foto" name="requires_photo"
                               :checked="old('requires_photo', $category->requires_photo ?? false)"
                               hint="Chatbot meminta foto dan menolak melanjutkan sebelum ada." />

                <x-ui.checkbox label="Wajib titik lokasi" name="requires_location"
                               :checked="old('requires_location', $category->requires_location ?? false)"
                               hint="Chatbot meminta lokasi; titiknya muncul di peta pengaduan." />
            </fieldset>

            {{-- Pilihannya punya harga di kedua arah, dan itu perlu dikatakan
                 sebelum seseorang mencentang semuanya "biar aman". --}}
            <div class="mt-5 rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">Menimbang keduanya</p>
                <p class="mt-1">
                    Kerusakan fisik — jalan, drainase, jembatan, bangunan — hampir tak berarti
                    tanpa foto dan titik lokasi: petugas tidak tahu harus berangkat ke mana.
                </p>
                <p class="mt-1">
                    Sebaliknya, memaksa memotret sesuatu untuk mengadukan pelayanan yang lambat
                    membuat laporannya tidak jadi dikirim. Untuk jenis seperti itu, uraian saja
                    sudah cukup.
                </p>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.checkbox label="Aktif" name="is_active" :checked="old('is_active', $category->is_active ?? true)"
                           hint="Jenis nonaktif tidak ditawarkan chatbot, tetapi pengaduan lama tetap utuh." />

            <x-slot:footer>
                <x-ui.button :href="route('admin.complaint-categories.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
