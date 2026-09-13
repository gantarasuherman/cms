<section class="sarab-section">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($section->title)
            @include('public.partials.section-heading', [
                'title' => $section->title,
                'subtitle' => $section->subtitle,
            ])
        @endif

        @if ($section->content)
            {{-- Escaped: section content is plain text entered by an administrator,
                 not trusted markup. --}}
            <div class="content-body mx-auto max-w-3xl">{!! nl2br(e($section->content)) !!}</div>
        @endif
    </div>
</section>
