@props(['label', 'name', 'checked' => false, 'hint' => null, 'value' => 1])

@php $id = $attributes->get('id') ?? $name; @endphp

<div>
    <label for="{{ $id }}" class="flex items-start gap-2.5 text-sm">
        {{-- Paired hidden input so an unchecked box still submits a value. --}}
        <input type="hidden" name="{{ $name }}" value="0">
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked(old($name, $checked))
            @if ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except(['class', 'id']) }}
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-input text-primary">
        <span class="leading-relaxed">{{ $label }}</span>
    </label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 ml-6.5 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 ml-6.5 text-sm text-destructive">{{ $message }}</p>
    @enderror
</div>
