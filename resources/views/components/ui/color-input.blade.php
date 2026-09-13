@props(['label', 'name', 'value' => null, 'hint' => null])

@php
    $id = $name.'-'.uniqid();
    $current = old($name, $value) ?: '#000000';
@endphp

{{--
    A colour swatch and its hex value, bound to each other.

    The native picker alone is not enough: it has no accessible text value, so
    a keyboard or screen-reader user gets a control they cannot read back or
    type into. The text field is the real input — it carries the label, the
    validation error and the value that is submitted. The swatch beside it is
    a convenience that writes into it.
--}}
<div data-color-field>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-foreground">{{ $label }}</label>

    <div class="flex items-center gap-2">
        <input type="color" value="{{ $current }}" data-color-picker tabindex="-1" aria-hidden="true"
               class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-border bg-card p-1">

        <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ old($name, $value) }}"
               data-color-text inputmode="text" spellcheck="false" autocomplete="off"
               placeholder="#02468B" pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
               aria-describedby="{{ $hint ? $id.'-hint' : '' }}"
               @error($name) aria-invalid="true" @enderror
               class="w-36 rounded-lg border border-border bg-card px-3 py-2 font-mono text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1.5 text-sm text-destructive">{{ $message }}</p>
    @enderror
</div>
