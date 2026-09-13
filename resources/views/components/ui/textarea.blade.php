@props(['label', 'name', 'value' => null, 'rows' => 4, 'required' => false, 'hint' => null])

@php $id = $attributes->get('id') ?? $name; @endphp

<x-ui.field :label="$label" :name="$name" :required="$required" :hint="$hint" :id="$id" :class="$attributes->get('class')">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @enderror
        {{ $attributes->except(['class', 'id']) }}
        class="flex w-full rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground disabled:opacity-50 @error($name) border-destructive @else border-input @enderror">{{ old($name, $value) }}</textarea>
</x-ui.field>
