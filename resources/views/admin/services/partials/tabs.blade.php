@php
    $tabs = [
        ['label' => 'Detail', 'route' => 'admin.services.edit', 'icon' => 'briefcase'],
        ['label' => 'Persyaratan', 'route' => 'admin.services.requirements.index', 'icon' => 'clipboard-list'],
        ['label' => 'Tarif', 'route' => 'admin.services.tariffs.index', 'icon' => 'banknote'],
        ['label' => 'Tahapan', 'route' => 'admin.services.steps.index', 'icon' => 'workflow'],
    ];
@endphp

<nav aria-label="Bagian layanan" class="mb-6 border-b border-border">
    <ul class="flex flex-wrap gap-1">
        @foreach ($tabs as $tab)
            @php $isCurrent = request()->routeIs($tab['route']); @endphp
            <li>
                <a href="{{ route($tab['route'], $service) }}"
                   @if ($isCurrent) aria-current="page" @endif
                   class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium {{ $isCurrent ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:border-input hover:text-foreground' }}">
                    <x-icon :name="$tab['icon']" class="h-4 w-4" />
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
