@props(['name' => 'circle', 'class' => 'h-5 w-5'])

@php
    // Rendered server-side so icons need no JavaScript. The body comes from the
    // icon bank (cached as a single map); an unknown name degrades to a neutral
    // glyph rather than leaving a hole in the interface.
    $body = app(App\Services\Icons\IconRepository::class)->body($name);
@endphp

<svg class="{{ $class }} inline-block shrink-0" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">{!! $body !!}</svg>
