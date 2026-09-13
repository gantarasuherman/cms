@props(['label', 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $selected = old($name, $value);
@endphp

<x-ui.field :label="$label" :name="$name" :required="$required" :hint="$hint" :id="$id" :class="$attributes->get('class')">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @enderror
        {{ $attributes->except(['class', 'id']) }}
        class="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition-colors disabled:opacity-50 @error($name) border-destructive @else border-input @enderror">
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-ui.field>
