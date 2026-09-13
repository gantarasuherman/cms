@php
    $panelId = 'faq-panel-'.$faq->id;
    $expanded = $expanded ?? false;
@endphp

<div class="bg-white first:rounded-t-2xl last:rounded-b-2xl">
    <h3>
        <button type="button" data-accordion-toggle aria-controls="{{ $panelId }}"
                aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-base font-medium text-slate-900 hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-teal-700">
            <span>{{ $faq->question }}</span>
            <span data-accordion-icon class="shrink-0 text-slate-400 transition-transform {{ $expanded ? 'rotate-180' : '' }}">
                <x-icon name="chevron-down" class="h-5 w-5" />
            </span>
        </button>
    </h3>

    <div id="{{ $panelId }}" @unless ($expanded) hidden @endunless class="px-5 pb-5">
        <div class="content-body text-sm">{!! nl2br(e($faq->answer)) !!}</div>
    </div>
</div>
