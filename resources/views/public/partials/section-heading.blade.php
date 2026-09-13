@php
    // A plain include, not a component, so defaults are declared here rather
    // than with @props (which only has meaning inside a component).
    $eyebrow = $eyebrow ?? null;
    $subtitle = $subtitle ?? null;
    $center = $center ?? true;
    $id = $id ?? null;
@endphp

<div class="{{ $center ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl' }} mb-12">
    @if ($eyebrow)
        <span class="sarab-eyebrow mb-3">{{ $eyebrow }}</span>
    @endif

    <h2 @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>

    @if ($subtitle)
        <p class="sarab-lead mt-4">{{ $subtitle }}</p>
    @endif
</div>
