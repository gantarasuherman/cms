@props(['code', 'heading', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $code }} &middot; {{ config('app.name') }}</title>

    {{-- Same pre-paint theme switch as the admin shell, so an error page does
         not flash white at a dark-mode user. --}}
    <script>
        (() => {
            try {
                const stored = localStorage.getItem('admin-theme');
                const dark = stored ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) { /* light is a safe default */ }
        })();
    </script>

    @vite(['resources/css/app.css'])
</head>
<body class="admin-shell min-h-svh bg-background text-foreground antialiased">
    <main class="mx-auto flex h-svh w-full max-w-lg flex-col items-center justify-center gap-2 px-6 text-center">
        {{-- The code is decorative at this size; the heading below is the real
             message, so it is the <h1> and the number is hidden from
             assistive technology. --}}
        <p class="text-[7rem] font-bold leading-tight" aria-hidden="true">{{ $code }}</p>

        <h1 class="font-medium">{{ $heading }}</h1>

        <p class="text-muted-foreground">{{ $message }}</p>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-4">
            {{-- history.back() needs JS; the link beside it always works, so
                 there is never a dead end. --}}
            <button type="button" onclick="history.back()"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
                Kembali
            </button>

            <a href="{{ url('/') }}"
               class="inline-flex items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90">
                Ke Beranda
            </a>
        </div>
    </main>
</body>
</html>
