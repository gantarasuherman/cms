<x-layouts.admin title="Bantuan AI">
    <x-ui.page-header title="Bantuan AI"
                      description="Membuat bot lebih luwes membaca kalimat warga. Bersifat membantu, bukan menggantikan alur.">
        <x-slot:actions>
            @can('update', new App\Models\Bot\BotChannel())
                <form method="POST" action="{{ route('admin.bot.ai.test') }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="activity">Uji Model</x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- A key filled in but the switch left off is the easy mistake here: the
         screen looks configured, the bot quietly answers from keyword search,
         and the answers wander. Said plainly rather than left to be noticed. --}}
    @if ($settings->keyHint() && ! $settings->enabled())
        <div class="mb-6 max-w-5xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-700 dark:bg-amber-950">
            <p class="flex items-start gap-2 text-sm text-amber-900 dark:text-amber-200">
                <x-icon name="circle-alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    <strong>Kunci sudah terisi, tetapi bantuan AI masih dimatikan.</strong>
                    Bot menjawab dengan pencarian kata kunci biasa — jawabannya akan terasa melenceng untuk
                    pertanyaan yang disusun bebas. Centang <em>Aktifkan bantuan AI</em> di bawah lalu simpan.
                </span>
            </p>
        </div>
    @endif

    <div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]">
        <form method="POST" action="{{ route('admin.bot.ai.update') }}" class="space-y-6">
            @csrf @method('PUT')

            <x-ui.card title="Penyedia">
                <div class="space-y-5">
                    <x-ui.checkbox label="Aktifkan bantuan AI" name="enabled" :checked="$settings->enabled()"
                                   hint="Dimatikan, bot bekerja persis seperti sebelumnya." />

                    <x-ui.select label="Layanan" name="provider" data-ai-provider
                                 :options="collect($providers)->map(fn ($p) => $p['label'])->all()"
                                 :value="$settings->provider()"
                                 hint="Alamat API-nya sudah diketahui sistem — Anda tidak perlu mengetiknya." />

                    {{-- Every provider's models are rendered; the script below
                         shows only the chosen provider's. Without the script
                         the list is still complete and the server refuses a
                         model that does not belong to the provider. --}}
                    <div>
                        <label for="ai-model" class="mb-1.5 block text-sm font-medium text-foreground">Model</label>
                        <select id="ai-model" name="model" data-ai-model
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
                            @foreach ($providers as $key => $provider)
                                @foreach ($provider['models'] as $model => $label)
                                    <option value="{{ $model }}" data-provider="{{ $key }}"
                                            @selected($key === $settings->provider() && $model === $settings->model())>{{ $label }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Yang lebih besar menjawab lebih baik; yang lebih kecil menjawab lebih cepat dan lebih murah.
                        </p>
                    </div>

                    <div data-ai-key>
                        <x-ui.input label="Kunci API" name="api_key" type="password" autocomplete="off"
                                    :placeholder="$settings->keyHint() ?: 'Belum diisi'"
                                    hint="Satu-satunya hal yang perlu Anda isi. Disimpan terenkripsi dan tidak pernah ditampilkan ulang." />

                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                            @if ($hint = $settings->keyHint())
                                <span class="inline-flex items-center gap-1 text-muted-foreground">
                                    <x-icon name="circle-check" class="h-3 w-3" />Terisi <code>{{ $hint }}</code>
                                    @unless ($settings->storedKeyPresent()) (dari <code>.env</code>) @endunless
                                </span>
                                @if ($settings->storedKeyPresent())
                                    <button type="submit" form="lupakan-kunci"
                                            class="rounded text-xs font-medium text-destructive underline underline-offset-2">Hapus kunci</button>
                                @endif
                            @else
                                <span class="text-muted-foreground">Belum diisi.</span>
                            @endif

                            @foreach ($providers as $key => $provider)
                                @if ($provider['docs'])
                                    <a href="{{ $provider['docs'] }}" target="_blank" rel="noopener noreferrer"
                                       data-ai-doc="{{ $key }}" hidden
                                       class="inline-flex items-center gap-1 text-primary underline underline-offset-2">
                                        <x-icon name="external-link" class="h-3 w-3" />Ambil kunci {{ $provider['label'] }}
                                    </a>
                                @endif
                            @endforeach
                        </p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Yang Boleh Dilakukan">
                <div class="space-y-5">
                    <x-ui.checkbox label="Memahami kalimat bebas pada menu" name="feature_intent"
                                   :checked="(bool) ($settings->all()['feature_intent'] ?? true)"
                                   hint="Warga yang menulis “jalan depan rumah saya rusak” diarahkan ke Pengaduan, bukan ditolak karena tidak membalas angka." />

                    <x-ui.checkbox label="Menyusun jawaban dari FAQ" name="feature_answers"
                                   :checked="(bool) ($settings->all()['feature_answers'] ?? true)"
                                   hint="Jawaban ditulis ulang dari isi yang ditemukan. Bila isinya tidak menjawab, pertanyaan tetap diteruskan ke petugas." />
                </div>

                <x-slot:footer>
                    <x-ui.button icon="save">Simpan</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <div class="space-y-4">
            <x-ui.card title="Status">
                <p class="text-sm">
                    @if ($available)
                        <span class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                            <x-icon name="circle-check" class="h-4 w-4" />Siap dipakai
                        </span>
                    @elseif ($settings->enabled())
                        <span class="inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                            <x-icon name="circle-alert" class="h-4 w-4" />Aktif tetapi belum lengkap
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                            <x-icon name="circle-slash" class="h-4 w-4" />Dimatikan
                        </span>
                    @endif
                </p>
            </x-ui.card>

            {{-- This is the one feature in the project that sends what a member
                 of the public typed to a third party. Saying so on the screen
                 where it is switched on is the least it deserves. --}}
            <x-ui.card title="Yang Perlu Anda Ketahui">
                <ul class="space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <li class="flex gap-2">
                        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Mengaktifkan ini mengirim <strong>teks pesan warga</strong> ke penyedia yang Anda pilih. Foto, lokasi, dan nomor telepon tidak pernah dikirim. Pilih Ollama lokal bila tidak boleh keluar dari server Anda.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Model tidak pernah memutuskan apa pun sendiri. Pengaduan tetap dibuat kode yang sama, dan tebakan menu hanya diterima bila cocok dengan pilihan yang benar-benar ada.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="circle-help" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Jawaban disusun <strong>hanya</strong> dari isi FAQ yang ditemukan. Bila tidak ada yang menjawab, model diminta berkata tidak tahu — dan pertanyaannya diteruskan ke petugas seperti biasa.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Bila model lambat atau gagal, bot langsung memakai cara lamanya. Percakapan tidak pernah menunggu lebih dari {{ config('ai.timeout') }} detik.</span>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </div>

    <form id="lupakan-kunci" method="POST" action="{{ route('admin.bot.ai.forget') }}" class="hidden">@csrf</form>
</x-layouts.admin>
