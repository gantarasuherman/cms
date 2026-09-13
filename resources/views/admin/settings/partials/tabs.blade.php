@php
    $tabs = [
        ['label' => 'Umum', 'route' => 'admin.settings.general.edit', 'icon' => 'settings'],
        ['label' => 'Beranda', 'route' => 'admin.settings.homepage.index', 'icon' => 'house'],
        ['label' => 'Carousel', 'route' => 'admin.settings.carousel.index', 'icon' => 'images'],
        ['label' => 'Media Sosial', 'route' => 'admin.settings.social.index', 'icon' => 'link'],
        ['label' => 'SEO', 'route' => 'admin.settings.seo.edit', 'icon' => 'search'],
        ['label' => 'Aksesibilitas', 'route' => 'admin.settings.accessibility.edit', 'icon' => 'accessibility'],
        ['label' => 'Footer', 'route' => 'admin.settings.footer.edit', 'icon' => 'panels-top-left'],
    ];
@endphp

<nav aria-label="Bagian pengaturan" class="mb-6 border-b border-border">
    <ul class="flex flex-wrap gap-1">
        @foreach ($tabs as $tab)
            @php $isCurrent = request()->routeIs($tab['route']); @endphp
            <li>
                <a href="{{ route($tab['route']) }}"
                   @if ($isCurrent) aria-current="page" @endif
                   class="-mb-px inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm font-medium {{ $isCurrent ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:border-input hover:text-foreground' }}">
                    <x-icon :name="$tab['icon']" class="h-4 w-4" />
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
