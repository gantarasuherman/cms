<x-layouts.admin :title="'Percakapan · '.($conversation->contact?->displayName() ?? '')">
    @php $contact = $conversation->contact; @endphp

    {{-- The header a messaging app shows: who this is, how to reach them, and
         where they wrote from. An operator ringing somebody back should not
         have to go looking for the number. --}}
    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-border bg-card p-4">
        <span class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 text-lg font-semibold text-primary">
            @if ($avatar = $contact?->avatarUrl())
                <img src="{{ $avatar }}" alt="" class="h-full w-full object-cover">
            @else
                {{-- Every WhatsApp contact lands here: the Cloud API does not
                     expose profile pictures at all. --}}
                {{ $contact?->initials() ?? '?' }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate text-lg font-semibold">{{ $contact?->displayName() ?? 'Tanpa nama' }}</p>

            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                @if ($contact?->username)
                    <span>&#64;{{ $contact->username }}</span>
                @endif

                @if ($number = $contact?->contactNumber())
                    {{-- Whole here, masked in the list. Opening one conversation
                         is a deliberate act; a list sits open on a desk all day,
                         and an operator cannot dial dots. --}}
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $number) }}"
                       class="inline-flex items-center gap-1 font-mono hover:text-foreground hover:underline">
                        <x-icon name="phone" class="h-3.5 w-3.5" />{{ $number }}
                    </a>
                @endif

                <span class="inline-flex items-center gap-1">
                    <x-icon name="send" class="h-3.5 w-3.5" />{{ $conversation->channel?->name ?? '—' }}
                </span>

                @if ($contact?->last_seen_at)
                    <span>terakhir terlihat {{ $contact->last_seen_at->diffForHumans() }}</span>
                @endif
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @include('admin.bot.conversations.partials.status', ['conversation' => $conversation])
            <x-ui.button :href="route('admin.bot.conversations.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </div>
    </div>

    <div class="grid max-w-6xl gap-6 lg:grid-cols-3">
        {{-- The transcript is capped to the window and scrolls inside itself,
             the way a chat does. Left to grow it pushed the session details and
             everything else off the bottom of a long conversation, and an
             operator had to scroll the whole page to read one exchange. --}}
        <x-ui.card class="lg:col-span-2 !p-0">
            {{-- A transcript, not a live chat: there is no reply box, because
                 an operator answering here would cut across the flow the person
                 is in the middle of. Replies go through the flow or a command. --}}
            <ol data-transcript tabindex="0" role="log" aria-label="Isi percakapan"
                class="max-h-[calc(100dvh-19rem)] min-h-64 space-y-3 overflow-y-auto overscroll-contain p-5
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                @php $lastDay = null; @endphp

                @forelse ($conversation->messages as $message)
                    @php
                        $incoming = $message->isIncoming();
                        $day = $message->created_at?->toDateString();
                        $newDay = $day !== $lastDay;
                        $lastDay = $day;
                    @endphp

                    @if ($newDay && $message->created_at)
                        {{-- A day marker, as every messaging app has: without it
                             a long transcript is one undated wall. --}}
                        <li class="flex justify-center py-1">
                            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                {{ $message->created_at->translatedFormat('l, d F Y') }}
                            </span>
                        </li>
                    @endif

                    <li class="flex items-end gap-2 {{ $incoming ? 'justify-start' : 'justify-end' }}">
                        @if ($incoming)
                            <span class="grid h-7 w-7 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 text-[10px] font-semibold text-primary"
                                  aria-hidden="true">
                                @if ($avatar = $contact?->avatarUrl())
                                    <img src="{{ $avatar }}" alt="" class="h-full w-full object-cover">
                                @else
                                    {{ $contact?->initials() ?? '?' }}
                                @endif
                            </span>
                        @endif

                        <div class="max-w-[80%] rounded-2xl px-4 py-2.5 {{ $incoming ? 'rounded-bl-sm bg-muted text-foreground' : 'rounded-br-sm bg-primary text-primary-foreground' }}">
                            @if ($message->media_path)
                                <a href="{{ $message->mediaUrl() }}"
                                   @if ($message->isImage())
                                       data-lightbox="Lampiran dari {{ $contact?->displayName() ?? 'percakapan' }}, {{ $message->created_at?->translatedFormat('d M Y H:i') }}"
                                   @else
                                       target="_blank" rel="noopener"
                                   @endif
                                   class="mb-2 block overflow-hidden rounded-lg">
                                    @if ($message->isImage())
                                        <img src="{{ $message->mediaUrl() }}" alt="Lampiran dari percakapan"
                                             class="max-h-64 w-full object-cover">
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-sm underline">
                                            <x-icon name="paperclip" class="h-4 w-4" />Buka lampiran
                                        </span>
                                    @endif
                                </a>
                            @endif

                            @if ($message->hasLocation())
                                <div class="mb-2 w-64 max-w-full">
                                    <x-ui.map-preview :latitude="$message->latitude" :longitude="$message->longitude"
                                                      label="yang dikirim pelapor" :compact="true" />
                                </div>
                            @endif

                            @if (filled($message->body))
                                <p class="whitespace-pre-line text-sm leading-relaxed">{{ $message->body }}</p>
                            @endif

                            <p class="mt-1 flex items-center justify-end gap-1 text-[11px] {{ $incoming ? 'text-muted-foreground' : 'text-primary-foreground/70' }}">
                                @if ($message->node_key === 'admin')
                                    {{-- Written by a person from the panel, not
                                         by the flow. Worth telling apart. --}}
                                    <span class="font-medium">Operator</span>·
                                @endif
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('H:i') }}</time>
                                @if ($message->node_key && $message->node_key !== 'admin')
                                    · <span class="font-mono">{{ $message->node_key }}</span>
                                @endif
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-muted-foreground">Belum ada pesan.</li>
                @endforelse
            </ol>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Sesi">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Node saat ini</dt>
                        <dd class="font-mono text-xs">{{ $conversation->current_node ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Versi alur</dt>
                        <dd>{{ $conversation->flow_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Dimulai</dt>
                        <dd>{{ $conversation->created_at?->translatedFormat('d M Y, H:i') }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            @php $answers = data_get($conversation->state, 'answers', []); @endphp
            @if ($answers)
                <x-ui.card title="Jawaban Terkumpul">
                    <dl class="space-y-2 text-sm">
                        @foreach ($answers as $key => $value)
                            {{-- Internal bookkeeping (the options offered, the
                                 list shown) is not an answer the person gave. --}}
                            @continue(str_starts_with($key, '_'))
                            <div>
                                <dt class="font-mono text-xs text-muted-foreground">{{ $key }}</dt>
                                <dd class="break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>
            @endif
        </div>
    </div>

    <x-ui.lightbox />
</x-layouts.admin>
