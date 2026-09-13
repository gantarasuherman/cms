<x-layouts.admin title="Pengaturan Chatbot">
    <x-ui.page-header title="Pengaturan Chatbot"
                      description="Kredensial WhatsApp dan Telegram, status kanal, dan alur yang dijalankannya." />

    <div class="grid max-w-6xl gap-6 lg:grid-cols-2">
        @foreach ($channels as $channel)
            <x-ui.card>
                <div class="mb-5 flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold">{{ $channel->name }}</h2>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            @if ($channel->isReady())
                                <span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                                    <x-icon name="circle-check" class="h-3.5 w-3.5" />Aktif dan lengkap
                                </span>
                            @elseif ($channel->is_active)
                                <span class="inline-flex items-center gap-1 text-amber-700 dark:text-amber-400">
                                    <x-icon name="circle-alert" class="h-3.5 w-3.5" />Aktif tetapi belum lengkap
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="circle-slash" class="h-3.5 w-3.5" />Nonaktif
                                </span>
                            @endif

                            @if ($channel->verified_at)
                                · terhubung {{ $channel->verified_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>

                    @can('update', $channel)
                        <form method="POST" action="{{ route('admin.bot.channels.test', $channel) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" icon="activity">Uji Koneksi</x-ui.button>
                        </form>
                    @endcan
                </div>

                @if ($channel->last_error)
                    <p class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ $channel->last_error }}
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.bot.channels.update', $channel) }}" class="space-y-5">
                    @csrf @method('PUT')

                    <x-ui.input label="Nama" name="name" :value="old('name', $channel->name)" required />

                    <x-ui.select label="Alur yang dijalankan" name="bot_flow_id"
                                 :options="$flows->toArray()" :value="old('bot_flow_id', $channel->bot_flow_id)"
                                 placeholder="Pakai alur bawaan"
                                 hint="Kosongkan agar mengikuti alur yang ditandai bawaan." />

                    <x-ui.input label="Sapaan" name="greeting"
                                :value="old('greeting', $channel->setting('greeting'))"
                                hint="Ditampilkan pada profil bot, bukan bagian dari alur." />

                    <div class="space-y-5 rounded-lg border border-border p-4">
                        <p class="text-sm font-medium">Kredensial</p>

                        @foreach ($channel->credentialFields() as $field => $meta)
                            @php
                                $hint = $channel->credentialHint($field);
                                $fromEnv = $hint && ! $channel->credentialIsStored($field);
                            @endphp

                            <div>
                                <x-ui.input
                                    :label="$meta['label']"
                                    :name="$field"
                                    :type="$meta['secret'] ? 'password' : 'text'"
                                    autocomplete="off"
                                    :placeholder="$hint ?: 'Belum diisi'"
                                    :hint="$meta['hint']" />

                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    @if ($hint)
                                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                                            <x-icon name="circle-check" class="h-3 w-3" />
                                            Terisi <code>{{ $hint }}</code>
                                            @if ($fromEnv) (dari <code>.env</code> server) @endif
                                        </span>

                                        @if ($channel->credentialIsStored($field))
                                            <button type="submit" form="forget-{{ $channel->id }}-{{ $field }}"
                                                    class="rounded text-xs font-medium text-destructive underline underline-offset-2">
                                                Hapus nilai ini
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-muted-foreground">Belum diisi.</span>
                                    @endif
                                </p>
                            </div>
                        @endforeach

                        {{-- The field never shows what is stored, so an empty box
                             can only mean "leave it alone". --}}
                        <p class="text-xs leading-relaxed text-muted-foreground">
                            Nilai yang sudah tersimpan tidak pernah ditampilkan ulang — biarkan kosong untuk
                            mempertahankannya. Semua kredensial disimpan terenkripsi dan tidak pernah masuk
                            ke audit log.
                        </p>
                    </div>

                    <x-ui.checkbox label="Aktifkan kanal" name="is_active" :checked="old('is_active', $channel->is_active)"
                                   hint="Kanal aktif tanpa kredensial lengkap tidak akan menjawab siapa pun." />

                    <x-ui.button icon="save" class="w-full">Simpan {{ $channel->name }}</x-ui.button>
                </form>

                @foreach ($channel->credentialFields() as $field => $meta)
                    @if ($channel->credentialIsStored($field))
                        <form id="forget-{{ $channel->id }}-{{ $field }}" method="POST"
                              action="{{ route('admin.bot.channels.forget', $channel) }}" class="hidden">
                            @csrf
                            <input type="hidden" name="field" value="{{ $field }}">
                        </form>
                    @endif
                @endforeach
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card title="Cara Layanan Bot Membacanya" class="mt-6 max-w-6xl">
        <p class="text-sm leading-relaxed text-muted-foreground">
            Layanan Python mengambil kredensial ini dari Laravel saat dijalankan, lewat API internal yang
            sama dengan jalur pesan — jadi mengubahnya di sini cukup diikuti dengan menjalankan ulang
            layanan bot, tanpa menyentuh berkas di server. Nilai yang masih diatur lewat <code>.env</code>
            tetap berlaku sebagai cadangan bila kolom di atas dikosongkan.
        </p>
    </x-ui.card>
</x-layouts.admin>
