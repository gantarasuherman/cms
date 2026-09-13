@props(['announcements', 'force' => false])

@php
    $announcements = collect($announcements)->filter(fn ($a) => filled($a->title))->values();
    /* A visitor who dismissed one set of notices should still meet the next
       one, so the remembered decision is keyed by what was actually shown. */
    $signature = $announcements->map(fn ($a) => $a->getKey().':'.$a->updated_at?->timestamp)->implode('|');
    $opening = $announcements->first(fn ($a) => $a->hasDetail()) ?? $announcements->first();
@endphp

@if ($announcements->isNotEmpty())
    {{--
        One record, two surfaces.

        The strip is the permanent one: it sits above the header and stays
        there whether or not the modal was ever opened. The modal is the
        attention-grabbing one, and it is shown at most once per visitor per
        set of notices — reopened on demand from the strip.
    --}}
    <div data-announcements data-signature="{{ sha1($signature) }}" @if ($force) data-force @endif>

        <aside aria-label="Pengumuman" data-ticker
               class="relative z-50 bg-[var(--brand-primary,#02468B)] text-[var(--brand-on-primary,#fff)]">
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">

                @if ($badge = $announcements->first()?->badge)
                    <span class="hidden shrink-0 rounded-full bg-[var(--brand-secondary,#EF8519)] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.1em] text-[var(--brand-on-secondary,#0b1b2b)] sm:inline-block">
                        {{ $badge }}
                    </span>
                @endif

                {{-- Fixed height with hidden overflow: this is the window the
                     lines travel through, so it must not grow with them. --}}
                <div class="relative h-11 min-w-0 flex-1 overflow-hidden">
                    <ul data-ticker-track class="absolute inset-x-0 top-0">
                        @foreach ($announcements as $announcement)
                            <li data-ticker-item data-index="{{ $loop->index }}"
                                @if (! $loop->first) aria-hidden="true" @endif
                                class="flex h-11 items-center">
                                @if ($announcement->hasDetail())
                                    <button type="button" data-announcement-open="{{ $loop->index }}"
                                            @if (! $loop->first) tabindex="-1" @endif
                                            class="truncate rounded text-left text-sm font-semibold underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                                        {{ $announcement->tickerText() }}
                                    </button>
                                @else
                                    <span class="truncate text-sm font-semibold">{{ $announcement->tickerText() }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($announcements->count() > 1)
                    {{-- Motion that starts on its own must be stoppable — WCAG
                         2.2.2. Hover only pauses for a pointer, so the control
                         is what makes this reachable by keyboard. --}}
                    <button type="button" data-ticker-toggle aria-pressed="false"
                            class="shrink-0 rounded p-1.5 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                        <span class="sr-only" data-ticker-toggle-label>Hentikan teks berjalan</span>
                        <span data-ticker-icon-pause><x-icon name="pause" class="h-4 w-4" /></span>
                        <span data-ticker-icon-play hidden><x-icon name="play" class="h-4 w-4" /></span>
                    </button>
                @endif
            </div>
        </aside>

        {{-- A native dialog, so the focus trap, the Escape key and making the
             page behind inert are the browser's job rather than ours. --}}
        {{-- `m-auto` is what centres it. A modal dialog is centred by the
             browser through `inset: 0` + `margin: auto` against its
             fit-content size, and Tailwind's preflight zeroes every margin —
             which pins the dialog to the top-left corner instead. --}}
        <dialog data-announcement-modal
                class="m-auto max-h-[calc(100dvh-2rem)] w-[min(34rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl bg-white p-0 text-slate-700 shadow-2xl backdrop:bg-slate-900/60">
            @foreach ($announcements as $announcement)
                <div data-announcement-panel="{{ $loop->index }}" @if (! $loop->first) hidden @endif class="p-7 sm:p-9">
                    @if ($announcement->badge)
                        <p class="mb-3 inline-block rounded-full bg-[var(--brand-secondary,#EF8519)] px-3.5 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.1em] text-[var(--brand-on-secondary,#0b1b2b)]">
                            {{ $announcement->badge }}
                        </p>
                    @endif

                    {{-- Its own id, and the dialog is pointed at whichever
                         panel is showing — a single fixed id would leave the
                         dialog labelled by a hidden heading. --}}
                    {{-- Not focusable: it is a title, not a control. Giving it
                         tabindex="-1" and focusing it drew the global focus
                         ring across the heading. showModal() already focuses
                         the close button, and the dialog is announced through
                         aria-labelledby pointing here. --}}
                    <h2 id="announcement-title-{{ $loop->index }}" data-announcement-heading
                        class="text-2xl font-extrabold text-slate-900 sm:text-3xl">
                        {{ $announcement->title }}
                    </h2>

                    @if ($announcement->body)
                        <p class="mt-3 text-base leading-relaxed">{{ $announcement->body }}</p>
                    @endif

                    @if ($announcement->code)
                        <div class="mt-6 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50 px-4 py-4 text-center">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-800">Kode Promo</p>
                            <p class="mt-1 font-mono text-xl font-extrabold tracking-wider text-emerald-900">{{ $announcement->code }}</p>
                            <button type="button" data-announcement-copy="{{ $announcement->code }}"
                                    class="mt-2 rounded text-xs font-semibold text-emerald-800 underline underline-offset-2 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                Salin kode
                            </button>
                            {{-- Announced, not just recoloured: a confirmation
                                 that only changes colour says nothing to a
                                 screen reader (WCAG 1.4.1). --}}
                            <p role="status" aria-live="polite" data-announcement-copied class="mt-1 text-xs font-semibold text-emerald-800"></p>
                        </div>
                    @endif

                    @if ($announcement->hasAction())
                        <a href="{{ $announcement->link }}" data-announcement-action
                           class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-[var(--brand-primary,#02468B)] px-6 py-3.5 font-bold text-[var(--brand-on-primary,#fff)] transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary,#02468B)]">
                            {{ $announcement->button_text }}
                        </a>
                    @endif
                </div>
            @endforeach

            <button type="button" data-announcement-close autofocus
                    class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                <span class="sr-only">Tutup pengumuman</span>
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </dialog>
    </div>
@endif
