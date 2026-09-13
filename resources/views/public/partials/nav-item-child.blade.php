@php
    $children = $item->loadedChildren();
    $hasChildren = $children->isNotEmpty();
    $menuId = 'nav-menu-'.$item->getKey();
@endphp

<li class="relative" @if ($hasChildren) data-nav-dropdown @endif>
    @if ($hasChildren)
        {{-- Nested level: opens to the side on wide screens, and is reachable
             with Enter/Space like any other disclosure button. --}}
        <button type="button" aria-expanded="false" aria-controls="{{ $menuId }}"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4 text-slate-400" />@endif
            <span class="flex-1">{{ $item->title }}</span>
            <x-icon name="chevron-right" class="h-3.5 w-3.5 opacity-60" />
        </button>

        <ul id="{{ $menuId }}" hidden
            class="z-50 mt-0.5 rounded-lg border border-slate-200 bg-white p-1.5 shadow-lg lg:absolute lg:left-full lg:top-0 lg:mt-0 lg:min-w-52">
            @foreach ($children as $child)
                @include('public.partials.nav-item-child', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @else
        <a href="{{ $item->link() }}" target="{{ $item->target }}"
           @if ($item->isCurrent()) aria-current="page" @endif
           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 {{ $item->isCurrent() ? 'bg-teal-50 font-medium text-teal-800' : 'text-slate-700' }}">
            @if ($item->icon)<x-icon :name="$item->icon" class="h-4 w-4 text-slate-400" />@endif
            {{ $item->title }}
        </a>
    @endif
</li>
