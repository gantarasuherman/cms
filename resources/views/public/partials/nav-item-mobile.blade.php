@php
    $children = $item->loadedChildren();
    $hasChildren = $children->isNotEmpty();
    $active = $item->isActiveTrail();
    $panelId = 'mobile-nav-panel-'.$item->getKey();
    $indent = match (min($depth, 3)) { 0 => 'pl-3', 1 => 'pl-7', 2 => 'pl-11', default => 'pl-14' };
@endphp

<li>
    @if ($hasChildren)
        <button type="button" data-menu-toggle aria-controls="{{ $panelId }}"
                aria-expanded="{{ $active ? 'true' : 'false' }}"
                class="flex w-full items-center gap-2.5 rounded-lg {{ $indent }} py-2.5 pr-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4" />@endif
            <span class="flex-1 text-left">{{ $item->title }}</span>
            <span data-menu-chevron class="transition-transform {{ $active ? 'rotate-90' : '' }}">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </span>
        </button>

        <ul id="{{ $panelId }}" @unless ($active) hidden @endunless class="space-y-0.5">
            @foreach ($children as $child)
                @include('public.partials.nav-item-mobile', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @else
        <a href="{{ $item->link() }}" target="{{ $item->target }}"
           @if ($item->isCurrent()) aria-current="page" @endif
           class="flex items-center gap-2.5 rounded-lg {{ $indent }} py-2.5 pr-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ $item->isCurrent() ? 'bg-teal-50 text-teal-800' : 'text-slate-700 hover:bg-slate-100' }}">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4" />@endif
            {{ $item->title }}
        </a>
    @endif
</li>
