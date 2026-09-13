@props([
    'series',
    'title',
    'description' => null,
    'valueKey' => 'value',
    'labelKey' => 'label',
    'secondaryKey' => null,
    'secondaryLabel' => null,
    'valueLabel' => 'Nilai',
    'height' => 180,
])

@php
    $rows = collect($series)->values();
    $values = $rows->pluck($valueKey)->map(fn ($v) => (int) $v);
    $max = (int) ($values->max() ?? 0);
    $peakIndex = $max > 0 ? $values->search($max) : null;

    /*
     | A "nice" ceiling strictly above the peak, so the tallest bar never touches
     | the top rule — a bar flush against the frame reads as clipped, and the
     | axis rounds to 0 / 25 / 50 rather than 0 / 23 / 46.
     */
    $ceiling = 1;
    if ($max > 0) {
        $magnitude = 10 ** (int) floor(log10($max));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $factor) {
            $candidate = (int) ceil($factor * $magnitude);
            if ($candidate > $max) {
                $ceiling = $candidate;
                break;
            }
        }
        $ceiling = max($ceiling, $max + 1);
    }

    $count = max(1, $rows->count());
    $plotHeight = (int) $height;
    $chartId = 'chart-'.uniqid();
@endphp

<figure class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm">
    <figcaption>
        <h3 id="{{ $chartId }}-title" class="text-sm font-semibold">{{ $title }}</h3>
        @if ($description)
            <p class="mt-0.5 text-xs text-muted-foreground">{{ $description }}</p>
        @endif
    </figcaption>

    @if ($values->sum() === 0)
        <p class="py-12 text-center text-sm text-muted-foreground">Belum ada data pada rentang ini.</p>
    @else
        {{--
            Bars are HTML boxes, not a stretched SVG. An SVG scaled with
            preserveAspectRatio="none" distorts its own geometry: the 4px
            corner radius and the 2px gaps come out squashed at wide container
            widths. CSS boxes keep both exact at every size.
        --}}
        <div class="mt-4 flex gap-3" aria-hidden="true">
            <ul class="flex shrink-0 flex-col justify-between text-right text-[11px] tabular-nums text-muted-foreground"
                style="height: {{ $plotHeight }}px">
                <li class="leading-none">{{ number_format($ceiling) }}</li>
                <li class="leading-none">{{ number_format((int) round($ceiling / 2)) }}</li>
                <li class="leading-none">0</li>
            </ul>

            <div class="min-w-0 flex-1">
                <div class="relative" style="height: {{ $plotHeight }}px">
                    {{-- Recessive grid: solid hairlines one step off the surface. --}}
                    @foreach ([0, 50, 100] as $offset)
                        <span class="absolute inset-x-0 block border-t border-border"
                              style="top: {{ $offset }}%"></span>
                    @endforeach

                    {{-- gap-0.5 is the 2px surface gap that separates adjacent bars;
                         no border is drawn around them. --}}
                    <div class="absolute inset-0 flex items-end gap-0.5">
                        @foreach ($rows as $row)
                            @php
                                $value = (int) $row[$valueKey];
                                $percent = $ceiling > 0 ? ($value / $ceiling) * 100 : 0;
                            @endphp
                            <div class="flex min-w-0 flex-1 justify-center">
                                @if ($value > 0)
                                    {{-- max-w caps the bar so the slot keeps its air;
                                         min-height keeps "1 visit" visible instead of
                                         indistinguishable from none. --}}
                                    <div class="w-full max-w-6 rounded-t bg-chart-1"
                                         style="height: {{ max($percent, 1.5) }}%; min-height: 3px"
                                         title="{{ $row[$labelKey] }}: {{ number_format($value) }} {{ $valueLabel }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- The axis band lives inside the card, so nothing is clipped. --}}
                <ul class="mt-2 flex gap-0.5 text-[11px] text-muted-foreground">
                    @foreach ($rows as $index => $row)
                        <li class="min-w-0 flex-1 truncate text-center">
                            <span class="{{ $count > 10 && $index % 2 !== 0 ? 'invisible' : '' }}">{{ $row[$labelKey] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <p class="sr-only">
            {{ $title }}.
            @if ($peakIndex !== null)
                Tertinggi {{ number_format($max) }} {{ $valueLabel }} pada {{ $rows[$peakIndex][$labelKey] }}.
            @endif
            Total {{ number_format($values->sum()) }} {{ $valueLabel }} selama {{ $count }} hari.
            Rincian tiap hari ada pada tabel di bawah.
        </p>

        {{-- The table view. Every chart has one: it is how the values are read
             without depending on colour, hover, or pointing accuracy. --}}
        <details class="mt-4">
            <summary class="inline-block cursor-pointer rounded text-xs font-medium hover:underline">
                Lihat sebagai tabel
            </summary>

            <div class="mt-3 max-h-72 overflow-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">{{ $title }} dalam bentuk tabel</caption>
                    <thead class="border-b border-border text-xs font-medium text-muted-foreground">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-semibold">Tanggal</th>
                            <th scope="col" class="py-2 pr-3 text-right font-semibold">{{ ucfirst($valueLabel) }}</th>
                            @if ($secondaryKey)
                                <th scope="col" class="py-2 text-right font-semibold">{{ $secondaryLabel }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row" class="py-2 pr-3 text-left font-normal">{{ $row[$labelKey] }}</th>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ number_format((int) $row[$valueKey]) }}</td>
                                @if ($secondaryKey)
                                    <td class="py-2 text-right tabular-nums text-muted-foreground">{{ number_format((int) $row[$secondaryKey]) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
