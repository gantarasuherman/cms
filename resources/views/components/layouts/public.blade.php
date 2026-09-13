@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
    // A page that renders its own announcements (the admin preview) sets this
    // false, or the component would appear twice — two strips, two dialogs,
    // and a duplicated id.
    'announcements' => true,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo.meta :title="$title" :description="$description" :image="$image" :type="$type" :noindex="$noindex" />

    @if ($favicon = data_get($site->general(), 'favicon'))
        <link rel="icon" href="{{ Storage::disk('public')->url($favicon) }}">
    @endif

    {{-- Applied before first paint so a visitor who chose larger text or a
         colour mode never sees the default flash first. --}}
    <script>
        (() => {
            try {
                const prefs = JSON.parse(localStorage.getItem('a11y') ?? '{}');
                const root = document.documentElement;
                if (prefs.scale) root.style.setProperty('--a11y-scale', prefs.scale);
                if (prefs.vision && prefs.vision !== 'normal') root.dataset.vision = prefs.vision;
                if (prefs.highlightLinks) root.dataset.highlightLinks = 'true';
                if (prefs.highlightInteractive) root.dataset.highlightInteractive = 'true';
                if (prefs.reduceMotion) root.dataset.reduceMotion = 'true';
            } catch (e) { /* storage unavailable: defaults are fine */ }
        })();
    </script>

    {{-- The administrator's colour and typeface, as custom properties.

         Inline rather than a linked file, so the very first paint is already
         in the right colours instead of flashing the defaults. The
         `--color-teal-*` names are Tailwind's own: the public views were built
         on those utilities, so redefining what they resolve to retints the
         whole site without a second palette that could drift out of step.

         Values are machine-generated — hex from a validated setting, font
         stacks from a fixed list — which is what makes the unescaped echo into
         a <style> safe. Never widen this to raw setting text. --}}
    <style>{!! $theme->css() !!}</style>

    @vite(['resources/css/app.css', 'resources/js/public.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-white text-slate-800 antialiased">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-md focus:bg-teal-700 focus:px-4 focus:py-2 focus:text-white">
        Lompat ke konten utama
    </a>

    @include('public.partials.color-filters')

    {{-- Above the header on purpose: the strip is the notice's permanent home
         once the modal has been dismissed. --}}
    @if ($announcements)
        <x-public.announcements :announcements="$site->announcements()" />
    @endif

    @include('public.partials.header')

    <main id="main-content" tabindex="-1" class="flex-1">
        {{ $slot }}
    </main>

    @include('public.partials.footer')

    @if (data_get($accessibility, 'accessibility_enabled') && data_get($accessibility, 'show_widget'))
        @include('public.partials.accessibility')
    @endif
</body>
</html>
