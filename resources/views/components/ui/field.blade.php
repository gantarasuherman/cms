@props(['label', 'name', 'required' => false, 'hint' => null])

@php $id = $attributes->get('id') ?? $name; @endphp

<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium leading-none">
        {{ $label }}
        @if ($required)
            <span class="text-destructive" aria-hidden="true">*</span>
            <span class="sr-only">(wajib diisi)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-center gap-1.5 text-sm text-destructive">
            <x-icon name="circle-alert" class="h-4 w-4" />{{ $message }}
        </p>
    @enderror
</div>
