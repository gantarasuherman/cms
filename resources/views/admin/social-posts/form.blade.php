@php $editing = $post->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah Unggahan' : 'Tambah Unggahan'">
    <x-ui.page-header :title="$editing ? 'Ubah Unggahan' : 'Tambah Unggahan'"
                      description="Isian di bawah terisi sendiri dari platform bila tautannya dapat dibaca. Ubah hanya yang perlu.">
        <x-slot:actions>
            @if ($editing)
                <form method="POST" action="{{ route('admin.social-posts.sync', $post) }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="download">Ambil Ulang dari Platform</x-ui.button>
                </form>
            @endif
            <x-ui.button :href="route('admin.social-posts.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST"
          action="{{ $editing ? route('admin.social-posts.update', $post) : route('admin.social-posts.store') }}"
          enctype="multipart/form-data" class="grid max-w-5xl gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Unggahan">
                <div class="space-y-5">
                    <x-ui.select label="Platform" name="platform" :options="App\Models\SocialPost::PLATFORMS"
                                 :value="old('platform', $post->platform)" />

                    <x-ui.input label="Nama Akun" name="account_handle" :value="old('account_handle', $post->account_handle)"
                                placeholder="dinaspupr"
                                hint="Diisi sendiri saat sinkronisasi berhasil. Tanpa tanda @." />

                    <x-ui.input label="Tautan Unggahan Asli" name="permalink" :value="old('permalink', $post->permalink)" required
                                hint="Alamat unggahannya, misalnya https://www.instagram.com/p/xxxxxxxx/. Harus diawali http:// atau https://." />

                    <x-ui.textarea label="Keterangan" name="caption" :value="old('caption', $post->caption)" :rows="4"
                                   hint="Mengikuti keterangan di platform pada setiap sinkronisasi. Untuk menulis sendiri, matikan sinkronisasi unggahan ini." />

                    <x-ui.input label="Tanggal Unggah" name="posted_at" type="datetime-local"
                                :value="old('posted_at', $post->posted_at?->format('Y-m-d\TH:i'))"
                                hint="Ditampilkan pada kartu. Kosongkan bila tidak perlu." />
                </div>
            </x-ui.card>

            <x-ui.card title="Suka dan Komentar">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Jumlah Suka" name="likes" type="number" min="0"
                                :value="old('likes', $post->likes)"
                                hint="Terisi otomatis. Kosongkan agar angkanya tidak ditampilkan." />
                    <x-ui.input label="Jumlah Komentar" name="comments" type="number" min="0"
                                :value="old('comments', $post->comments)"
                                hint="Terisi otomatis. Kosongkan agar angkanya tidak ditampilkan." />
                </div>

                @if ($post->synced_at)
                    <p class="mt-4 text-sm text-muted-foreground">
                        Terakhir diambil {{ $post->synced_at->diffForHumans() }} dari {{ $post->platformLabel() }}.
                    </p>
                @elseif ($post->sync_status === 'failed')
                    <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ $post->sync_message }}
                    </p>
                @endif

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    Angka diperbarui tiap jam dari platform asalnya. Yang tidak dapat dibaca — misalnya
                    unggahan akun lain, atau platform tanpa jalur baca gratis — tetap bisa diisi tangan;
                    dikosongkan pun tidak masalah, ikonnya tampil tanpa angka, dan itu lebih jujur
                    daripada menuliskan 0.
                </p>
            </x-ui.card>

            @if ($post->media->isNotEmpty())
                <x-ui.card title="Slide Carousel">
                    <p class="mb-4 text-sm leading-relaxed text-muted-foreground">
                        {{ $post->media->count() }} gambar, sesuai urutan di platform. Semuanya tampil sebagai
                        carousel di halaman publik dan ikut diperbarui setiap sinkronisasi.
                    </p>
                    <ol class="flex flex-wrap gap-3">
                        @foreach ($post->media as $i => $slide)
                            <li class="relative">
                                <img src="{{ $slide->url() }}" alt="Slide {{ $i + 1 }}"
                                     class="h-24 w-24 rounded-lg border border-border object-cover">
                                <span class="absolute left-1 top-1 rounded bg-black/70 px-1.5 text-xs font-semibold text-white">{{ $i + 1 }}</span>
                                @if ($slide->isVideo())
                                    <span class="absolute bottom-1 right-1 rounded bg-black/70 px-1.5 text-xs text-white">video</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
            @endif

            <x-ui.card title="Gambar">
                <div class="space-y-5">
                    @if ($post->hasImage())
                        <img src="{{ $post->imageUrl() }}" alt="Gambar unggahan saat ini"
                             class="max-w-xs rounded-lg border border-border object-cover">
                        @if ($post->remote_image_url)
                            <p class="text-sm text-muted-foreground">
                                Diambil dari platform. Mengunggah berkas di bawah akan menggantinya sampai
                                sinkronisasi berikutnya.
                            </p>
                        @endif
                    @else
                        <p class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                            Belum ada gambar. Gambar akan terisi sendiri bila unggahannya dapat dibaca dari platform.
                        </p>
                    @endif

                    <x-ui.input label="{{ $post->hasImage() ? 'Ganti gambar' : 'Unggah gambar' }}" name="image" type="file"
                                accept="image/*"
                                hint="Hanya perlu bila gambarnya tidak dapat diambil sendiri. Disarankan bujur sangkar 1080×1080. Maksimal 6 MB." />

                    <x-ui.input label="Teks alternatif" name="alt_text" :value="old('alt_text', $post->alt_text)"
                                hint="Jelaskan isi gambarnya bagi pengguna pembaca layar. Bila dikosongkan, keterangan di atas yang dipakai." />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Tayang">
                <div class="space-y-5">
                    <x-ui.checkbox label="Tampilkan" name="is_active" :checked="old('is_active', $post->is_active ?? true)"
                                   hint="Unggahan tetap tersimpan saat disembunyikan." />

                    <x-ui.checkbox label="Ikuti perubahan di platform" name="sync_enabled"
                                   :checked="old('sync_enabled', $post->sync_enabled ?? true)"
                                   hint="Matikan bila keterangan atau angkanya ingin Anda tulis sendiri dan tidak boleh tertimpa." />

                    <x-ui.checkbox label="Akun terverifikasi" name="is_verified"
                                   :checked="old('is_verified', $post->is_verified ?? false)"
                                   hint="Menampilkan centang biru di sebelah nama akun. Tidak ada API yang melaporkan status ini, jadi centang hanya bila akunnya memang terverifikasi." />
                </div>

                <x-slot:footer>
                    <x-ui.button :href="route('admin.social-posts.index')" variant="secondary">Batal</x-ui.button>
                    <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Catatan">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Unggahan dimasukkan secara manual, bukan ditarik otomatis dari platform. Skrip sematan
                    resmi akan melaporkan setiap pengunjung beranda ke platform tersebut sebelum mereka
                    melakukan apa pun — dan itu bertentangan dengan cara situs ini memperlakukan data
                    pengunjung. Dengan cara ini gambar dilayani dari server sendiri dan halaman tetap cepat.
                </p>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
