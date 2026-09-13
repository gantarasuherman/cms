<x-layouts.admin :title="'Pengaduan '.$complaint->ticket">
    <x-ui.page-header :title="$complaint->ticket" :description="$complaint->category?->name ?? 'Tanpa kategori'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.complaints.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid max-w-6xl gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Laporan">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted-foreground">Status</dt>
                        <dd class="mt-1">@include('admin.complaints.partials.status', ['complaint' => $complaint])</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Masuk</dt>
                        <dd class="mt-1 text-sm">{{ $complaint->created_at?->translatedFormat('d F Y, H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Kanal</dt>
                        <dd class="mt-1 text-sm">{{ App\Models\Bot\BotChannel::KEYS[$complaint->channel] ?? $complaint->channel }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Pelapor</dt>
                        {{-- Masked: operators need to recognise a reporter, not
                             to have a full number on a screen open all day. --}}
                        <dd class="mt-1 text-sm">{{ $complaint->contact?->displayName() ?? $complaint->reporter_name ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-border pt-5">
                    <p class="text-sm text-muted-foreground">Uraian</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $complaint->description }}</p>
                </div>

                @if ($complaint->conversation)
                    <div class="mt-5 border-t border-border pt-5">
                        <a href="{{ route('admin.bot.conversations.show', $complaint->conversation) }}"
                           class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                            <x-icon name="send" class="h-4 w-4" />Lihat percakapan aslinya
                        </a>
                    </div>
                @endif
            </x-ui.card>

            @if ($complaint->hasLocation())
                <x-ui.card title="Lokasi">
                    {{-- The picture is stitched on this server from cached
                         tiles, so the operator's browser never calls a map
                         provider and no provider learns where a complaint is. --}}
                    <x-ui.map-preview :latitude="$complaint->latitude" :longitude="$complaint->longitude"
                                      label="pengaduan {{ $complaint->ticket }}" />

                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button href="https://www.openstreetmap.org/?mlat={{ $complaint->latitude }}&mlon={{ $complaint->longitude }}#map=17/{{ $complaint->latitude }}/{{ $complaint->longitude }}"
                                     target="_blank" rel="noopener noreferrer" variant="secondary" icon="globe">
                            OpenStreetMap
                        </x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            @foreach (['report' => 'Bukti dari Pelapor', 'resolution' => 'Bukti Tindak Lanjut'] as $kind => $title)
                @php $files = $complaint->attachments->where('kind', $kind); @endphp
                @if ($files->isNotEmpty())
                    <x-ui.card :title="$title">
                        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($files as $file)
                                <li>
                                    <a href="{{ $file->url() }}"
                                       data-lightbox="{{ $title }} {{ $loop->iteration }}"
                                       class="block overflow-hidden rounded-lg border border-border transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                                        <img src="{{ $file->url() }}" alt="{{ $title }} {{ $loop->iteration }}"
                                             class="aspect-square w-full object-cover">
                                    </a>
                                    @if ($file->uploader)
                                        <p class="mt-1 text-xs text-muted-foreground">oleh {{ $file->uploader->name }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            @endforeach

            @can('update', $complaint)
                @php $reachable = $complaint->contact?->channel !== null && filled($complaint->contact?->external_id); @endphp

                <x-ui.card title="Balas Pelapor"
                           description="Terkirim ke {{ $reachable ? $complaint->contact->channel->name : 'kanal asal' }} pelapor, dan tercatat pada percakapannya.">
                    @if ($reachable)
                        <form method="POST" action="{{ route('admin.complaints.reply', $complaint) }}"
                              enctype="multipart/form-data" class="space-y-4">
                            @csrf

                            <x-ui.textarea label="Pesan" name="message" :rows="4" required
                                           hint="Ditulis apa adanya ke pelapor. Sebutkan nomor tiketnya bila perlu." />

                            <x-ui.input label="Lampirkan foto" name="attachment" type="file" accept="image/*"
                                        hint="Opsional. Misalnya foto hasil penanganan." />

                            <x-ui.button icon="send">Kirim Balasan</x-ui.button>
                        </form>
                    @else
                        {{-- A complaint that did not arrive through a chat has
                             no address to answer to; saying so is better than a
                             form that fails on submit. --}}
                        <p class="text-sm text-muted-foreground">
                            Pengaduan ini tidak berasal dari WhatsApp atau Telegram, jadi tidak ada tujuan
                            untuk membalas. Hubungi pelapor melalui kontak yang tercatat pada laporannya.
                        </p>
                    @endif
                </x-ui.card>
            @endcan

            <x-ui.card title="Riwayat Perubahan">
                <ol class="space-y-4">
                    @foreach ($complaint->updates as $update)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    {{ $update->from_status ? App\Models\Complaint::STATUSES[$update->from_status].' → ' : '' }}{{ App\Models\Complaint::STATUSES[$update->to_status] ?? $update->to_status }}
                                </p>
                                @if ($update->note)
                                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $update->note }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ $update->actor() }} · {{ $update->created_at?->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        @can('update', $complaint)
            <div>
                <x-ui.card title="Tindak Lanjut">
                    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}"
                          enctype="multipart/form-data" class="space-y-5">
                        @csrf @method('PUT')

                        {{-- Four options, always exactly one true: a radio
                             group says that where a dropdown only implies it,
                             and every choice is visible without opening
                             anything. --}}
                        <x-ui.radio-group label="Status" name="status" :options="$statuses"
                                          :value="$complaint->status"
                                          :icons="[
                                              'baru' => 'circle-alert',
                                              'diproses' => 'clock',
                                              'selesai' => 'circle-check',
                                              'ditolak' => 'circle-slash',
                                          ]" />

                        <x-ui.textarea label="Catatan" name="note" :rows="3"
                                       hint="Tampil pada riwayat perubahan, dan ikut terkirim bila pelapor diberi tahu." />

                        <x-ui.checkbox label="Beri tahu pelapor" name="notify_reporter"
                                       :checked="(bool) $complaint->contact?->channel"
                                       hint="Mengirim kabar perubahan status ini ke kanal tempat pengaduan masuk." />

                        <x-ui.input label="Foto Bukti Selesai" name="proof[]" type="file" accept="image/*" multiple
                                    hint="Boleh lebih dari satu. Disimpan terpisah dari foto pelapor." />

                        <x-ui.button icon="save" class="w-full">Simpan</x-ui.button>
                    </form>
                </x-ui.card>
            </div>
        @endcan
    </div>

    <x-ui.lightbox />
</x-layouts.admin>
