@props(['label', 'name', 'options' => [], 'value' => null, 'hint' => null, 'icons' => [], 'columns' => 1])

@php
    $id = $attributes->get('id') ?? $name;
    $selected = old($name, $value);

    // One must always be chosen, so if nothing matches the first option leads
    // rather than leaving a group with no answer selected.
    if (! array_key_exists((string) $selected, $options)) {
        $selected = array_key_first($options);
    }
@endphp

{{--
    A radio group, written as one.

    `fieldset` and `legend` rather than a loose label: a screen reader
    announcing "Status, Baru, 1 of 4" is the difference between knowing what
    the choice is about and hearing four unrelated words. Arrow keys move
    between options for free, which a set of styled divs would have to
    reimplement badly.
--}}
<fieldset {{ $attributes->except(['class', 'id']) }}>
    <legend class="mb-1.5 block text-sm font-medium text-foreground">{{ $label }}</legend>

    {{-- One column by default. Two columns in a narrow card wrapped the
         longer labels, which left the rows at different heights — grid can
         equalise within a row but not between them. --}}
    <div class="grid gap-2 {{ $columns > 1 ? 'sm:grid-cols-'.$columns : '' }}"
         @if ($hint) aria-describedby="{{ $id }}-hint" @endif>
        @foreach ($options as $option => $text)
            <label for="{{ $id }}-{{ $loop->index }}"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition
                          hover:bg-accent
                          has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:checked]:font-medium
                          has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring">
                <input type="radio"
                       id="{{ $id }}-{{ $loop->index }}"
                       name="{{ $name }}"
                       value="{{ $option }}"
                       @checked((string) $selected === (string) $option)
                       class="h-4 w-4 shrink-0 border-input text-primary focus:ring-0 focus:ring-offset-0">

                @if ($icon = $icons[$option] ?? null)
                    <x-icon :name="$icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                @endif

                <span>{{ $text }}</span>
            </label>
        @endforeach
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1.5 text-sm text-destructive">{{ $message }}</p>
    @enderror
</fieldset>
