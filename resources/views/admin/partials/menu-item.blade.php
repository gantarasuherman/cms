@php
    $children = $item->loadedChildren();
    $hasChildren = $children->isNotEmpty();
    $active = $item->isActiveTrail();
    $panelId = 'menu-panel-'.$item->getKey();

    // Literal class names: Tailwind scans source text, so the indent step must
    // not be built by string concatenation.
    $indent = match (min($depth, 3)) {
        0 => 'pl-2',
        1 => 'pl-6',
        2 => 'pl-9',
        default => 'pl-12',
    };

    // Inside a flyout the rows start at the edge again — the indent that shows
    // nesting in the wide rail would only waste space in a 12rem panel.
    $inFlyout = $inFlyout ?? false;
    if ($inFlyout) {
        $indent = 'pl-2';
    }
@endphp

@if ($hasChildren)
    @if ($depth === 0)
        {{-- Top-level groups are section headings, not buttons: the label names
             the group and its items stay visible. --}}
        <div class="sidebar-group-label px-2 pb-1 pt-4">
            <p class="px-2 text-xs font-medium text-muted-foreground">{{ $item->title }}</p>
        </div>
        <div class="space-y-0.5">
            @foreach ($children as $child)
                @include('admin.partials.menu-item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @else
        {{-- sidebar-node is the hover/focus target the flyout anchors to. --}}
        <div class="sidebar-node mb-0.5">
            <button type="button"
                    data-menu-toggle
                    aria-controls="{{ $panelId }}"
                    aria-expanded="{{ $active ? 'true' : 'false' }}"
                    title="{{ $item->title }}"
                    class="sidebar-item sidebar-group-toggle flex w-full items-center gap-2 rounded-md {{ $indent }} py-2 pr-2 text-sm transition-colors hover:bg-accent {{ $active ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground' }}">
                <x-icon :name="$item->icon ?? 'circle'" class="h-4 w-4 shrink-0" />
                <span class="sidebar-label flex-1 truncate text-left">{{ $item->title }}</span>
                <span data-menu-chevron class="sidebar-chevron transition-transform {{ $active ? 'rotate-90' : '' }}">
                    <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                </span>
            </button>

            {{-- One panel, two jobs: an inline disclosure while the rail is wide,
                 a floating menu once it is collapsed. --}}
            <div id="{{ $panelId }}" @unless ($active) hidden @endunless
                 class="sidebar-subpanel sidebar-flyout mt-0.5 space-y-0.5">
                <p class="sidebar-flyout-title border-b border-border px-2.5 pb-1.5 pt-1 text-xs font-semibold">
                    {{ $item->title }}
                </p>

                @foreach ($children as $child)
                    @include('admin.partials.menu-item', [
                        'item' => $child,
                        'depth' => $depth + 1,
                        'inFlyout' => true,
                    ])
                @endforeach
            </div>
        </div>
    @endif
@else
    <div class="sidebar-node mb-0.5">
        <a href="{{ $item->link() }}"
           target="{{ $item->target }}"
           @if ($item->isCurrent()) aria-current="page" @endif
           @class([
               'sidebar-item flex items-center gap-2 rounded-md py-2 pr-2 text-sm transition-colors',
               $indent,
               'bg-accent font-medium text-accent-foreground' => $item->isCurrent(),
               'text-muted-foreground hover:bg-accent hover:text-foreground' => ! $item->isCurrent(),
           ])
           title="{{ $item->title }}">
            <x-icon :name="$item->icon ?? 'circle'" class="h-4 w-4 shrink-0" />
            <span class="sidebar-label flex-1 truncate">{{ $item->title }}</span>
        </a>

        {{-- A leaf has nothing to open, so its flyout is just the name it lost
             when the rail narrowed. Skipped inside a flyout, where the label is
             already visible. --}}
        @unless ($inFlyout)
            <span class="sidebar-flyout sidebar-flyout--label px-2.5 py-1.5 text-sm whitespace-nowrap">
                {{ $item->title }}
            </span>
        @endunless
    </div>
@endif
