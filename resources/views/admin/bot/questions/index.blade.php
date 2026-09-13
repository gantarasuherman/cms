<x-layouts.admin title="Pertanyaan Sering Muncul">
    <x-ui.page-header title="Pertanyaan Sering Muncul"
                      description="Pertanyaan yang masuk lewat WhatsApp dan Telegram, dikelompokkan menurut maksudnya.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.faq.index')" variant="secondary" icon="circle-help">Kelola FAQ</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-6 !p-4">
        <p class="text-sm leading-relaxed text-muted-foreground">
            Dihitung langsung dari percakapan, dari node
            @foreach ($nodes as $node)<code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ $node }}</code>@if (! $loop->last), @endif @endforeach
            — yaitu tempat orang menulis pertanyaan bebas. Menjadikannya FAQ menjawabnya sekali untuk
            semua: FAQ terbit di situs publik <em>dan</em> menjadi sumber jawaban chatbot, jadi
            pertanyaan itu tidak lagi dikarang ulang setiap kali ditanyakan.
        </p>
    </x-ui.card>

    <div class="mb-6 flex flex-wrap items-end gap-4">
        <nav class="flex gap-1 rounded-lg border border-border p-1" aria-label="Saringan status">
            @foreach ([
                \App\Models\BotQuestionTopic::NEW => 'Belum ditangani',
                \App\Models\BotQuestionTopic::PROMOTED => 'Sudah jadi FAQ',
                \App\Models\BotQuestionTopic::IGNORED => 'Disingkirkan',
            ] as $key => $label)
                <a href="{{ route('admin.bot.questions.index', ['status' => $key, 'minimal' => $minimum]) }}"
                   @class([
                       'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                       'bg-primary text-primary-foreground' => $status === $key,
                       'text-muted-foreground hover:bg-accent' => $status !== $key,
                   ])
                   @if ($status === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span class="tabular-nums opacity-70">({{ $counts[$key] }})</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" class="flex items-end gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <div>
                <label for="minimal" class="mb-1.5 block text-xs font-medium text-muted-foreground">Minimal ditanyakan</label>
                <select id="minimal" name="minimal" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                    @foreach ([1 => '1 kali', 2 => '2 kali', 3 => '3 kali', 5 => '5 kali', 10 => '10 kali'] as $value => $label)
                        <option value="{{ $value }}" @selected($minimum === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="secondary" icon="filter">Saring</x-ui.button>
        </form>
    </div>

    @forelse ($topics as $topic)
        <x-ui.card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold">{{ $topic['question'] }}</h2>

                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Ditanyakan <strong class="tabular-nums text-foreground">{{ $topic['asks'] }}&times;</strong>
                        oleh {{ $topic['askers'] }} orang &middot;
                        terakhir {{ $topic['last_asked_at']->diffForHumans() }} &middot;
                        pertama {{ $topic['first_asked_at']->translatedFormat('d M Y') }}
                    </p>

                    @if (count($topic['variants']) > 1)
                        {{-- The wordings folded into this topic. Shown because
                             the grouping is a judgement the editor should be
                             able to check, not a black box. --}}
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                                {{ count($topic['variants']) }} cara penulisan digabung
                            </summary>
                            <ul class="mt-2 space-y-1 border-l-2 border-border pl-3 text-sm text-muted-foreground">
                                @foreach ($topic['variants'] as $variant)
                                    <li>{{ $variant }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if ($faq = $topic['topic']?->faq)
                        <p class="mt-3 text-sm">
                            <a href="{{ route('admin.faq.edit', $faq) }}" class="font-medium text-primary hover:underline">
                                Buka FAQ-nya
                            </a>
                            @if (! $faq->is_active)
                                <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    belum terbit — jawabannya masih kosong
                                </span>
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($status === \App\Models\BotQuestionTopic::NEW)
                        <form method="POST" action="{{ route('admin.bot.questions.promote') }}">
                            @csrf
                            <input type="hidden" name="question" value="{{ $topic['question'] }}">
                            <x-ui.button type="submit" icon="plus">Jadikan FAQ</x-ui.button>
                        </form>

                        <form method="POST" action="{{ route('admin.bot.questions.ignore') }}">
                            @csrf
                            <input type="hidden" name="question" value="{{ $topic['question'] }}">
                            <x-ui.button type="submit" variant="secondary" icon="eye-off">Singkirkan</x-ui.button>
                        </form>
                    @elseif ($topic['topic'])
                        <form method="POST" action="{{ route('admin.bot.questions.restore', $topic['topic']) }}">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="secondary" icon="undo-2">Kembalikan ke daftar</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @empty
        <x-ui.card>
            <p class="text-sm text-muted-foreground">
                @if ($status === \App\Models\BotQuestionTopic::NEW)
                    Belum ada pertanyaan yang memenuhi saringan ini. Pertanyaan terkumpul sendiri saat
                    orang bertanya lewat chatbot.
                @else
                    Belum ada yang ditandai seperti ini.
                @endif
            </p>
        </x-ui.card>
    @endforelse
</x-layouts.admin>
