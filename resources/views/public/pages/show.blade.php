<x-layouts.public
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description ?: $page->excerpt">

    <x-public.page-hero :title="$page->title" :description="$page->excerpt"
                        :breadcrumbs="[$page->title => null]" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($page->featured_image)
            <img src="{{ Storage::disk('public')->url($page->featured_image) }}"
                 alt="" class="mb-8 w-full rounded-2xl object-cover">
        @endif

        <div class="content-body">{!! nl2br(e($page->content)) !!}</div>
    </div>
</x-layouts.public>
