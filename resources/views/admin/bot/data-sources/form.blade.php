@php $editing = $source->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Sumber Data' : 'Tambah Sumber Data'">
    <x-ui.page-header :title="$editing ? 'Ubah Sumber Data' : 'Tambah Sumber Data'"
                      description="Pratinjau di sebelah kanan memakai isi situs yang sebenarnya dan jalur kode yang sama dengan bot.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.bot.data-sources.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div data-source-editor data-preview-url="{{ route('admin.bot.data-sources.preview') }}"
         class="grid max-w-6xl gap-6 lg:grid-cols-[1fr_22rem]">

        <form method="POST"
              action="{{ $editing ? route('admin.bot.data-sources.update', $source) : route('admin.bot.data-sources.store') }}"
              class="space-y-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-ui.card title="Sumber">
                <div class="space-y-5">
                    <x-ui.input label="Nama" name="name" :value="old('name', $source->name)" required
                                hint="Tampil sebagai judul daftar di dalam chat." />

                    <x-ui.select label="Ambil dari" name="source" :options="$sources"
                                 :value="old('source', $source->source)"
                                 hint="Hanya isi yang sudah terbit yang terbaca — aturan yang sama dengan situs publik." />

                    <x-ui.input label="Jumlah Item" name="limit" type="number" min="1" max="20"
                                :value="old('limit', $source->limit)" required
                                hint="Daftar yang terlalu panjang sulit dibaca di layar ponsel." />
                </div>
            </x-ui.card>

            <x-ui.card title="Tampilan Balasan">
                <div class="space-y-5">
                    <x-ui.input label="Baris Daftar" name="list_template" :value="old('list_template', $source->list_template)"
                                hint="Contoh: {index}. {title} ({date})" />

                    <x-ui.textarea label="Rincian" name="detail_template" :rows="5"
                                   :value="old('detail_template', $source->detail_template)"
                                   hint="Dikirim saat pengguna membalas nomornya." />

                    <div class="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                        <p class="font-medium">Penanda yang tersedia</p>
                        <ul class="mt-2 grid gap-1 text-muted-foreground sm:grid-cols-2">
                            <li><code>{index}</code> — nomor urut</li>
                            <li><code>{title}</code> — judul</li>
                            <li><code>{date}</code> — tanggal</li>
                            <li><code>{excerpt}</code> — ringkasan</li>
                            <li><code>{url}</code> — tautan ke halamannya</li>
                        </ul>
                        <p class="mt-2 text-muted-foreground">
                            Tanda <code>*bintang*</code> menebalkan teks di WhatsApp maupun Telegram.
                        </p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.checkbox label="Aktif" name="is_active" :checked="old('is_active', $source->is_active ?? true)"
                               hint="Sumber nonaktif tidak dapat dipilih node mana pun." />

                <x-slot:footer>
                    <x-ui.button :href="route('admin.bot.data-sources.index')" variant="secondary">Batal</x-ui.button>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <div class="space-y-4">
            <x-ui.card title="Pratinjau Balasan"
                       description="Seperti yang akan diterima pengguna.">
                <x-ui.button type="button" data-preview-refresh variant="secondary" icon="eye" class="mb-4 w-full">
                    Muat Pratinjau
                </x-ui.button>

                {{-- Styled as a chat bubble so an administrator can judge the
                     wording in the shape it will actually arrive in. --}}
                <div class="space-y-3">
                    <div class="rounded-2xl rounded-tl-sm bg-muted px-4 py-3">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Daftar</p>
                        <p data-preview-list class="whitespace-pre-line text-sm leading-relaxed">Tekan tombol di atas untuk memuat.</p>
                    </div>

                    <div class="rounded-2xl rounded-tl-sm bg-muted px-4 py-3">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rincian bila dibalas "1"</p>
                        <p data-preview-detail class="whitespace-pre-line text-sm leading-relaxed">—</p>
                    </div>
                </div>

                <p data-preview-status role="status" aria-live="polite" class="mt-3 text-sm text-muted-foreground"></p>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
