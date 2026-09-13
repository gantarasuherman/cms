@php
    $flashes = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ]);
    $styles = [
        'success' => ['border-success/30 bg-success/10 text-success', 'circle-check'],
        'error' => ['border-destructive/30 bg-destructive/10 text-destructive', 'circle-alert'],
        'warning' => ['border-warning/30 bg-warning/10 text-warning', 'triangle-alert'],
        'info' => ['border-border bg-muted text-foreground', 'info'],
    ];
@endphp

@foreach ($flashes as $type => $message)
    @php([$classes, $icon] = $styles[$type])
    {{-- Status is carried by icon and wording as well as colour, so the message
         is not colour-dependent. --}}
    <div data-dismissable role="status"
         class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
        <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0" />
        <p class="flex-1">{{ $message }}</p>
        <button type="button" data-dismiss aria-label="Tutup pesan" class="rounded p-1 transition-colors hover:bg-foreground/10">
            <x-icon name="x" class="h-4 w-4" />
        </button>
    </div>
@endforeach

@if ($errors->any() && ! $errors->has('email'))
    <div role="alert" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        <p class="flex items-center gap-2 font-medium"><x-icon name="circle-alert" class="h-4 w-4" /> Periksa kembali isian berikut:</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
