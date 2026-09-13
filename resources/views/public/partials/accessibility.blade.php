@php
    $a = $accessibility;
    $position = match ($a['widget_position'] ?? 'bottom-right') {
        'bottom-left' => 'bottom-4 left-4',
        'top-right' => 'top-20 right-4',
        'top-left' => 'top-20 left-4',
        default => 'bottom-4 right-4',
    };
    $panelPosition = str_contains($position, 'left') ? 'left-0' : 'right-0';
    $panelVertical = str_contains($position, 'top') ? 'top-14' : 'bottom-14';
@endphp

{{-- Accessibility controls.

     Every control here is independent and remembered in localStorage, and each
     one is hidden when an administrator has switched it off in
     /admin/settings/accessibility. The panel itself is an ordinary disclosure:
     a button, a labelled region, Escape to close, and focus returned to the
     button — so the thing that provides accessibility is itself accessible. --}}
<div data-a11y-root class="fixed z-50 {{ $position }} print:hidden">
    <button type="button" data-a11y-toggle aria-expanded="false" aria-controls="a11y-panel"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-700 text-white shadow-lg transition hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
        <x-icon name="accessibility" class="h-7 w-7" />
        <span class="sr-only">Buka pengaturan aksesibilitas</span>
    </button>

    <div id="a11y-panel" hidden role="dialog" aria-modal="false" aria-labelledby="a11y-title"
         class="absolute {{ $panelPosition }} {{ $panelVertical }} max-h-[75vh] w-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">

        <div class="mb-4 flex items-center justify-between">
            <h2 id="a11y-title" class="text-base font-bold text-slate-900">Aksesibilitas</h2>
            <button type="button" data-a11y-close
                    class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                <x-icon name="x" class="h-5 w-5" />
                <span class="sr-only">Tutup panel aksesibilitas</span>
            </button>
        </div>

        @if ($a['text_resize_enabled'] ?? true)
            <section class="mb-5">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Ukuran Teks</h3>
                <div class="flex items-center gap-2">
                    <button type="button" data-a11y-text="down"
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        A<span class="text-xs">−</span><span class="sr-only">Perkecil teks</span>
                    </button>
                    <button type="button" data-a11y-text="reset"
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        A<span class="sr-only">Ukuran normal</span>
                    </button>
                    <button type="button" data-a11y-text="up"
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-base font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        A<span class="text-xs">+</span><span class="sr-only">Perbesar teks</span>
                    </button>
                </div>
                <p data-a11y-scale-status role="status" aria-live="polite" class="mt-1.5 text-xs text-slate-500"></p>
            </section>
        @endif

        @if ($a['color_blind_mode_enabled'] ?? true)
            <section class="mb-5">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Penglihatan Warna</h3>
                <div class="space-y-1">
                    @foreach ([
                        'normal' => 'Normal',
                        'protanopia' => 'Protanopia (sulit merah)',
                        'deuteranopia' => 'Deuteranopia (sulit hijau)',
                        'tritanopia' => 'Tritanopia (sulit biru)',
                        'grayscale' => 'Skala abu-abu',
                    ] as $value => $label)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="radio" name="a11y-vision" value="{{ $value }}" data-a11y-vision
                                   @checked($value === 'normal')
                                   class="h-4 w-4 border-slate-300 text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($a['text_to_speech_enabled'] ?? true)
            <section class="mb-5" data-a11y-speech>
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Baca Halaman</h3>

                {{-- Shown only when the browser actually provides speech
                     synthesis; otherwise the unsupported notice replaces it. --}}
                <div data-a11y-speech-controls hidden class="space-y-2">
                    <button type="button" data-a11y-speak="page"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        <x-icon name="volume-2" class="h-4 w-4" />Baca halaman
                    </button>
                    <button type="button" data-a11y-speak="selection"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                        <x-icon name="type" class="h-4 w-4" />Baca teks terpilih
                    </button>
                    <div class="flex gap-2">
                        <button type="button" data-a11y-speak="pause"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            <x-icon name="pause" class="h-4 w-4" />Jeda
                        </button>
                        <button type="button" data-a11y-speak="stop"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            <x-icon name="square" class="h-4 w-4" />Hentikan
                        </button>
                    </div>
                    <p data-a11y-speech-status role="status" aria-live="polite" class="text-xs text-slate-500"></p>
                </div>

                <p data-a11y-speech-unsupported hidden
                   class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    <x-icon name="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    Peramban ini tidak mendukung pembacaan teks. Coba peramban lain, atau gunakan pembaca layar bawaan perangkat Anda.
                </p>
            </section>
        @endif

        @if (($a['highlight_links_enabled'] ?? true) || ($a['reduce_motion_enabled'] ?? true))
            <section class="mb-2">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Tampilan</h3>
                <div class="space-y-1">
                    @if ($a['highlight_links_enabled'] ?? true)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox" data-a11y-option="highlightLinks"
                                   class="h-4 w-4 rounded border-slate-300 text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            Pertegas tautan
                        </label>
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox" data-a11y-option="highlightInteractive"
                                   class="h-4 w-4 rounded border-slate-300 text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            Pertegas tombol dan kolom isian
                        </label>
                    @endif

                    @if ($a['reduce_motion_enabled'] ?? true)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox" data-a11y-option="reduceMotion"
                                   class="h-4 w-4 rounded border-slate-300 text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            Kurangi animasi
                        </label>
                    @endif
                </div>
            </section>
        @endif

        <button type="button" data-a11y-reset
                class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
            Kembalikan ke pengaturan awal
        </button>
    </div>
</div>
