@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} &middot; {{ $site->siteName() }}</title>

    @if ($favicon = data_get($site->general(), 'favicon'))
        <link rel="icon" href="{{ Storage::disk('public')->url($favicon) }}">
    @endif

    {{-- Theme is applied before first paint, so a dark-mode user never sees a
         white flash while the stylesheet and scripts load. --}}
    <script>
        (() => {
            try {
                const stored = localStorage.getItem('admin-theme');
                const dark = stored ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);

                // Same reason as the theme: applied before paint so the rail
                // does not visibly jump from wide to narrow on every load.
                if (localStorage.getItem('admin-sidebar') === 'collapsed') {
                    document.documentElement.dataset.sidebarCollapsed = 'true';
                }
            } catch (e) { /* storage blocked: light is a safe default */ }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
{{-- data-sidebar-collapsed is mirrored from <html> by admin.js, so the
     pre-paint script can set it before <body> exists. --}}
<body class="admin-shell min-h-screen bg-background text-foreground antialiased">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground">
        Lompat ke konten utama
    </a>

    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-foreground/40 lg:hidden"></div>

    <aside data-sidebar
           class="admin-sidebar fixed inset-y-0 left-0 z-40 flex -translate-x-full flex-col border-r border-sidebar-border bg-sidebar transition-[transform,width] duration-200 md:translate-x-0">

        {{-- App identity, in the slot shadcn-admin gives its team switcher.

             Two marks, because the sidebar collapses to 4rem: a square one that
             survives the collapse, and the full logo beside it. Both come from
             settings; a panel whose name lives in APP_NAME cannot be renamed by
             anybody who only has a login. --}}
        @php
            $general = $site->general();
            $square = data_get($general, 'favicon') ?: data_get($general, 'logo');
            $wide = data_get($general, 'admin_logo') ?: data_get($general, 'logo');
        @endphp

        <div class="p-2">
            <a href="{{ route('admin.dashboard') }}"
               class="sidebar-item flex items-center gap-2 rounded-lg p-2 transition-colors hover:bg-accent">
                {{-- Only shown when the sidebar is collapsed, if a wide logo is
                     taking the space beside it — otherwise the same mark
                     appears twice, the second one squeezed into a square. --}}
                <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-lg
                             {{ $wide ? 'brand-square' : '' }}
                             {{ $square ? 'bg-transparent' : 'bg-primary text-primary-foreground' }}">
                    @if ($square)
                        <img src="{{ Storage::disk('public')->url($square) }}" alt=""
                             class="h-full w-full object-contain">
                    @else
                        <x-icon name="layout-dashboard" class="h-4 w-4" />
                    @endif
                </span>

                <span class="sidebar-label min-w-0 flex-1 text-left">
                    @if ($wide)
                        <img src="{{ Storage::disk('public')->url($wide) }}"
                             alt="{{ $site->siteName() }}"
                             class="h-10 w-auto max-w-full object-contain object-left">
                        <span class="mt-0.5 block truncate text-xs text-muted-foreground">Panel Admin</span>
                    @else
                        {{-- Nothing uploaded yet: the name still has to be
                             readable, and it comes from settings. --}}
                        <span class="block truncate text-sm font-semibold leading-tight">{{ $site->siteName() }}</span>
                        <span class="block truncate text-xs text-muted-foreground">Panel Admin</span>
                    @endif
                </span>
            </a>
        </div>

        <nav id="admin-sidebar-nav" class="flex-1 overflow-y-auto px-2 pb-4" aria-label="Navigasi admin">
            @forelse ($adminMenu as $item)
                @include('admin.partials.menu-item', ['item' => $item, 'depth' => 0])
            @empty
                <p class="px-3 py-2 text-sm text-muted-foreground">Belum ada menu.</p>
            @endforelse
        </nav>

        {{-- Account block, pinned to the bottom as in the reference. --}}
        <div class="border-t border-sidebar-border p-2">
            <div class="sidebar-item flex items-center gap-2 rounded-lg p-2">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground">
                    {{ Str::of(auth()->user()?->name ?? '?')->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') }}
                </span>
                <span class="sidebar-label min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium leading-tight">{{ auth()->user()?->name }}</span>
                    <span class="block truncate text-xs text-muted-foreground">{{ auth()->user()?->email }}</span>
                </span>

                <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-label">
                    @csrf
                    <button type="submit"
                            class="grid h-8 w-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                        <x-icon name="log-out" class="h-4 w-4" />
                        <span class="sr-only">Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="admin-content">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:px-6">
            <button type="button" data-sidebar-toggle aria-expanded="false" aria-label="Buka navigasi"
                    class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:hidden">
                <x-icon name="panels-top-left" class="h-4.5 w-4.5" />
            </button>

            {{-- Rail collapse. The chevron points the way the rail will move, so
                 the control reads without its tooltip; aria-expanded carries the
                 state and the label names what pressing it will do. --}}
            <button type="button" data-sidebar-collapse aria-expanded="true" aria-controls="admin-sidebar-nav"
                    title="Ciutkan navigasi (Ctrl+B)"
                    class="hidden items-center gap-1 rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:inline-flex">
                <x-icon name="panels-top-left" class="h-4.5 w-4.5" />
                <span data-sidebar-collapse-chevron class="transition-transform">
                    <x-icon name="chevron-left" class="h-3.5 w-3.5" />
                </span>
                <span class="sr-only" data-sidebar-collapse-label>Ciutkan navigasi</span>
            </button>

            <div class="hidden h-4 w-px bg-border md:block"></div>

            <h1 class="truncate text-sm font-medium">{{ $title }}</h1>

            <div class="ml-auto flex items-center gap-1">
                <a href="{{ url('/') }}" target="_blank" rel="noopener"
                   class="hidden items-center gap-2 rounded-md px-2.5 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:inline-flex">
                    <x-icon name="external-link" class="h-4 w-4" /> Lihat situs
                </a>

                {{-- Theme toggle. aria-pressed carries the state, and the label
                     says which mode the button switches to. --}}
                <button type="button" data-theme-toggle aria-pressed="false"
                        class="grid h-9 w-9 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                    <span data-theme-icon-light><x-icon name="moon" class="h-4 w-4" /></span>
                    <span data-theme-icon-dark hidden><x-icon name="sun" class="h-4 w-4" /></span>
                    <span class="sr-only" data-theme-label>Aktifkan mode gelap</span>
                </button>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="px-4 py-6 sm:px-6 lg:px-8">
            @include('admin.partials.flash')
            {{ $slot }}
        </main>
    </div>
</body>
</html>
