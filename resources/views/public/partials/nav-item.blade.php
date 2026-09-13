@php
    $children = $item->loadedChildren();
    $hasChildren = $children->isNotEmpty();
    $active = $item->isActiveTrail();
    $menuId = 'nav-menu-'.$item->getKey();
@endphp

<li class="relative" @if ($hasChildren) data-nav-dropdown @endif>
    @if ($hasChildren)
        <button type="button"
                aria-expanded="false"
                aria-controls="{{ $menuId }}"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ $active ? 'text-teal-800' : 'text-slate-700 hover:bg-slate-100' }}">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4" />@endif
            {{ $item->title }}
            <x-icon name="chevron-down" class="h-3.5 w-3.5 opacity-60" />
        </button>

        <ul id="{{ $menuId }}" hidden
            class="absolute left-0 top-full z-50 mt-1 min-w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
            @foreach ($children as $child)
                @include('public.partials.nav-item-child', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @else
        <a href="{{ $item->link() }}" target="{{ $item->target }}"
           @if ($item->isCurrent()) aria-current="page" @endif
           class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ $item->isCurrent() ? 'bg-teal-50 text-teal-800' : 'text-slate-700 hover:bg-slate-100' }}">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4" />@endif
            {{ $item->title }}
        </a>
    @endif
</li>
