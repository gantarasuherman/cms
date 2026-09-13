@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

@php $id = $attributes->get('id') ?? $name; @endphp

<x-ui.field :label="$label" :name="$name" :required="$required" :hint="$hint" :id="$id" :class="$attributes->get('class')">
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @enderror
        {{ $attributes->except(['class', 'id']) }}
        class="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground file:mr-3 file:rounded file:border-0 file:bg-secondary file:px-2 file:py-1 file:text-xs file:font-medium disabled:opacity-50 @error($name) border-destructive @else border-input @enderror">
</x-ui.field>
