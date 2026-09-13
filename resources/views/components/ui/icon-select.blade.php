@props([
    'label' => 'Ikon',
    'name' => 'icon',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => '— Tanpa ikon —',
])

@php
    $id = $attributes->get('id') ?? $name;
    $selected = old($name, $value);
    $groups = app(App\Services\Icons\IconRepository::class)->grouped();
@endphp

{{--
    A native <select>, grouped, with a live preview beside it.

    A custom listbox could show every glyph inline, but it would have to
    re-implement the combobox keyboard contract, focus management and mobile
    behaviour that the browser already gets right. The native control stays
    operable by keyboard, by screen reader and by a phone's own picker; the
    preview supplies the one thing it cannot show.
--}}
<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium leading-none">
        {{ $label }}
        @if ($required)
            <span class="text-destructive" aria-hidden="true">*</span>
            <span class="sr-only">(wajib diisi)</span>
        @endif
    </label>

    <div class="flex items-center gap-2">
        {{-- Mirrors the chosen icon. aria-hidden because the select already
             announces the name; this is the visual half of the same fact. --}}
        <span data-icon-preview-for="{{ $id }}"
              class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-input bg-muted text-muted-foreground">
            <x-icon :name="$selected ?: 'circle'" class="h-4 w-4" />
        </span>

        <select
            id="{{ $id }}"
            name="{{ $name }}"
            data-icon-select
            @if ($required) required @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @enderror
            {{ $attributes->except(['class', 'id']) }}
            class="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition-colors @error($name) border-destructive @else border-input @enderror">
            @unless ($required)
                <option value="">{{ $placeholder }}</option>
            @endunless

            @foreach ($groups as $groupName => $icons)
                <optgroup label="{{ $groupName }}">
                    @foreach ($icons as $icon)
                        <option value="{{ $icon['name'] }}" @selected((string) $selected === $icon['name'])>
                            {{ $icon['label'] }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-center gap-1.5 text-sm text-destructive">
            <x-icon name="circle-alert" class="h-4 w-4" />{{ $message }}
        </p>
    @enderror
</div>
