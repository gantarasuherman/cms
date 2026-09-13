@php
    $general = $site->general();
    $footer = $site->footer();
    $social = $site->socialLinks();
@endphp

{{-- Every colour here comes from the footer ground chosen in
     /admin/settings/appearance. The text steps are derived against that
     ground rather than fixed, so a dark footer keeps legible lettering
     instead of near-invisible slate. --}}
<footer class="mt-16 border-t"
        style="background: var(--footer-bg, #F8FAFC); color: var(--footer-muted, #475569); border-color: var(--footer-border, #e2e8f0);">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-3">
            <div>
                <h2 class="text-base font-bold" style="color: var(--footer-ink, #0f172a);">{{ $site->siteName() }}</h2>
                @if ($about = $footer['footer_about'] ?? null)
                    <p class="mt-3 text-sm leading-relaxed">{{ $about }}</p>
                @endif

                @if (($footer['show_social'] ?? true) && $social->isNotEmpty())
                    <ul class="mt-5 flex flex-wrap gap-2">
                        @foreach ($social as $link)
                            <li>
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                                   style="border-color: var(--footer-border, #e2e8f0); background: var(--footer-surface, #fff);"
                                   class="inline-flex h-10 w-10 items-center justify-center rounded-lg border transition hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                                    <x-icon :name="$link->icon ?: 'link'" class="h-5 w-5" />
                                    <span class="sr-only">{{ $link->label ?: $link->platform }} (buka di tab baru)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <nav aria-labelledby="footer-nav-heading">
                <h2 id="footer-nav-heading" class="text-sm font-semibold" style="color: var(--footer-ink, #0f172a);">Navigasi</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($publicMenu as $item)
                        <li>
                            <a href="{{ $item->link() }}"
                               class="text-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                                {{ $item->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div>
                <h2 class="text-sm font-semibold" style="color: var(--footer-ink, #0f172a);">Kontak</h2>
                <address class="mt-3 space-y-2 text-sm not-italic">
                    @if ($address = $general['address'] ?? null)
                        <p class="flex items-start gap-2">
                            <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 opacity-70" />
                            <span>{{ $address }}</span>
                        </p>
                    @endif
                    @if ($phone = $general['phone'] ?? null)
                        <p class="flex items-center gap-2">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 opacity-70" />
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                               class="underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
                               style="color: var(--footer-link, #02468B);">{{ $phone }}</a>
                        </p>
                    @endif
                    @if ($email = $general['email'] ?? null)
                        <p class="flex items-center gap-2">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 opacity-70" />
                            <a href="mailto:{{ $email }}"
                               class="underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
                               style="color: var(--footer-link, #02468B);">{{ $email }}</a>
                        </p>
                    @endif
                </address>
            </div>
        </div>

        @if ($extra = $footer['footer_text'] ?? null)
            <p class="mt-10 text-sm">{{ $extra }}</p>
        @endif

        <p class="mt-10 border-t pt-6 text-sm" style="border-color: var(--footer-border, #e2e8f0);">
            {{ ($general['copyright'] ?? null) ?: '© '.date('Y').' '.$site->siteName() }}
        </p>
    </div>
</footer>
