<x-layouts.admin title="Coba Percakapan">
    <x-ui.page-header title="Coba Percakapan"
                      description="Mengirim pesan ke bot lewat mesin yang sama persis dengan yang melayani WhatsApp dan Telegram sungguhan — tanpa perlu ponsel.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.bot.channels.index')" variant="secondary" icon="settings">Pengaturan Chatbot</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $channel)
        <x-ui.card>
            <p class="py-8 text-center text-sm text-muted-foreground">Belum ada saluran chatbot.</p>
        </x-ui.card>
    @else
        <div class="grid max-w-6xl gap-6 lg:grid-cols-[1fr_22rem]">

            {{-- ------------------------------------------------ percakapan --}}
            <div class="space-y-4">
                <x-ui.card>
                    <form method="GET" action="{{ route('admin.bot.simulator.index') }}" class="flex flex-wrap items-end gap-3">
                        <x-ui.select label="Saluran yang dicoba" name="channel"
                                     :options="$channels->pluck('name', 'key')"
                                     :value="$channel->key"
                                     class="min-w-48 flex-1"
                                     hint="Alur dan jawabannya bisa berbeda antar saluran." />
                        <x-ui.button variant="secondary" icon="redo-2">Ganti</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card title="Percakapan" id="percakapan">
                    <x-slot:description>
                        Sebagai {{ auth()->user()->name }} — percakapan uji terpisah dari percakapan warga.
                    </x-slot:description>

                    <div class="space-y-3">
                        @forelse ($messages as $message)
                            @php $incoming = $message->direction === 'in'; @endphp

                            <div class="flex {{ $incoming ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed
                                            {{ $incoming
                                                ? 'rounded-br-sm bg-primary text-primary-foreground'
                                                : 'rounded-bl-sm bg-muted' }}">
                                    <p class="whitespace-pre-line">{{ $message->body }}</p>

                                    {{-- Node yang menjawab: saat balasannya keliru, inilah
                                         yang memberi tahu kotak mana yang harus dibuka di
                                         editor alur. --}}
                                    @if (! $incoming && $message->node_key)
                                        <p class="mt-1.5 text-xs opacity-70">node: <code>{{ $message->node_key }}</code></p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-muted-foreground">
                                Belum ada percakapan. Kirim pesan di bawah — coba <code>menu</code> untuk memulai.
                            </p>
                        @endforelse
                    </div>

                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.bot.simulator.send') }}" class="flex w-full flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="channel" value="{{ $channel->key }}">

                            <x-ui.input label="Pesan" name="text" :value="null" required
                                        class="min-w-48 flex-1"
                                        autocomplete="off"
                                        placeholder="Ketik seperti warga mengetik…" />

                            <x-ui.button icon="send">Kirim</x-ui.button>
                        </form>
                    </x-slot:footer>
                </x-ui.card>

                @if ($messages->isNotEmpty())
                    <form method="POST" action="{{ route('admin.bot.simulator.reset') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="secondary" icon="trash-2">
                            Reset percakapan uji
                        </x-ui.button>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Menghapus percakapan ini beserta pengaduan yang sempat diajukan darinya,
                            supaya laporan pengaduan tidak tercampur data latihan.
                        </p>
                    </form>
                @endif
            </div>

            {{-- ------------------------------------------------- kesiapan --}}
            <div class="space-y-4">
                <x-ui.card title="Kesiapan Konfigurasi"
                           description="Apa yang sudah siap, dan apa akibatnya bila belum.">
                    <ul class="space-y-4">
                        @foreach ($checks as $check)
                            <li class="flex items-start gap-3">
                                {{-- Status dinyatakan dengan kata pada teks di bawahnya,
                                     bukan hanya warna ikon. --}}
                                <span class="mt-0.5 shrink-0 {{ $check['ok'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                    <x-icon :name="$check['ok'] ? 'circle-check' : 'triangle-alert'" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $check['label'] }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ $check['note'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>

                <x-ui.card title="Yang perlu diingat">
                    <ul class="space-y-2 text-xs text-muted-foreground">
                        <li>
                            Percakapan ini <strong>sungguhan</strong>: pengaduan yang diajukan dari sini
                            benar-benar tercatat dan petugas benar-benar dikabari.
                        </li>
                        <li>
                            Kesiapan kredensial di atas menguji <em>kelengkapannya</em>. Untuk menguji
                            apakah platform menerimanya, pakai tombol Uji di Pengaturan Chatbot.
                        </li>
                        <li>
                            Balasan di sini berasal dari alur dan sumber data yang terbit — sama dengan
                            yang diterima warga.
                        </li>
                    </ul>
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layouts.admin>
