@php $editing = $announcement->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Pengumuman' : 'Tambah Pengumuman'">
    <x-ui.page-header :title="$editing ? 'Ubah Pengumuman' : 'Tambah Pengumuman'"
                      description="Judul dibaca di modal maupun di strip. Isi selebihnya hanya tampil di modal.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.announcements.preview')" target="_blank" rel="noopener"
                         variant="secondary" icon="eye">Pratinjau</x-ui.button>
            <x-ui.button :href="route('admin.announcements.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
          class="grid max-w-5xl gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Isi Pengumuman">
                <div class="space-y-5">
                    <x-ui.input label="Label" name="badge" :value="$announcement->badge"
                                hint="Kotak kecil di atas judul. Misalnya: Info Terbaru, Agenda, Promo. Kosongkan bila tidak perlu." />

                    <x-ui.input label="Judul" name="title" :value="$announcement->title" required
                                hint="Wajib. Dipakai modal, dan dipakai strip bila teks berjalan dikosongkan." />

                    <x-ui.textarea label="Keterangan" name="body" :value="$announcement->body" :rows="4"
                                   hint="Hanya tampil di modal. Kosongkan bila pengumuman cukup satu baris — strip tetap menampilkannya." />

                    <x-ui.input label="Kode Promo" name="code" :value="$announcement->code"
                                hint="Bila diisi, modal menampilkannya dalam kotak bergaris putus-putus lengkap dengan tombol salin." />
                </div>
            </x-ui.card>

            <x-ui.card title="Teks Berjalan">
                <x-ui.input label="Teks pada strip" name="ticker_text" :value="$announcement->ticker_text"
                            maxlength="160"
                            hint="Satu baris pendek untuk strip di atas navbar. Kosongkan agar memakai judul. Teks panjang akan terpotong, bukan dibaca." />
            </x-ui.card>

            <x-ui.card title="Tombol">
                <div class="space-y-5">
                    <x-ui.input label="Teks Tombol" name="button_text" :value="$announcement->button_text"
                                hint="Kosongkan bila pengumuman tidak mengarah ke mana pun." />
                    <x-ui.input label="Tautan Tombol" name="link" :value="$announcement->link"
                                hint="Misalnya /layanan atau https://contoh.test. Keduanya harus diisi bersama — tombol tanpa tautan tidak akan ditampilkan." />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Tayang">
                <div class="space-y-5">
                    <x-ui.checkbox label="Aktif" name="is_active" :checked="old('is_active', $announcement->is_active ?? true)"
                                   hint="Pengumuman juga harus berada di dalam rentang tanggal di bawah untuk tampil." />

                    <x-ui.input label="Mulai Tayang" name="start_date" type="datetime-local"
                                :value="old('start_date', $announcement->start_date?->format('Y-m-d\TH:i'))"
                                hint="Kosongkan agar langsung tayang." />

                    <x-ui.input label="Berhenti Tayang" name="end_date" type="datetime-local"
                                :value="old('end_date', $announcement->end_date?->format('Y-m-d\TH:i'))"
                                hint="Kosongkan agar tayang seterusnya." />
                </div>

                <x-slot:footer>
                    <x-ui.button :href="route('admin.announcements.index')" variant="secondary">Batal</x-ui.button>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Cara Kerjanya">
                <ul class="space-y-2.5 text-sm text-muted-foreground">
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />Modal terbuka satu kali per pengunjung.</li>
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />Setelah ditutup, pengumuman tetap ada sebagai strip di atas navbar.</li>
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />Mengubah pengumuman membuat modal terbuka lagi bagi pengunjung lama.</li>
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />Dua pengumuman aktif atau lebih membuat strip bergerak dari bawah ke atas.</li>
                </ul>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
