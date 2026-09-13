@props(['news'])

<article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:border-teal-700 hover:shadow-lg">
    <a href="{{ route('public.news.show', $news->slug) }}" class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
        @if ($news->featured_image)
            <img src="{{ Storage::disk('public')->url($news->featured_image) }}"
                 alt="" loading="lazy"
                 class="aspect-[16/10] w-full object-cover">
        @else
            <div class="grid aspect-[16/10] w-full place-items-center bg-slate-100">
                <x-icon name="newspaper" class="h-10 w-10 text-slate-300" />
            </div>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        @if ($news->categories->isNotEmpty())
            <ul class="mb-2.5 flex flex-wrap gap-1.5">
                @foreach ($news->categories->take(2) as $category)
                    <li>
                        <a href="{{ route('public.news.index', ['kategori' => $category->slug]) }}"
                           class="inline-block rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800 hover:bg-teal-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <h3 class="text-lg font-semibold leading-snug text-slate-900">
            <a href="{{ route('public.news.show', $news->slug) }}"
               class="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 group-hover:text-teal-800">
                {{ $news->title }}
            </a>
        </h3>

        @if ($news->excerpt)
            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $news->excerpt }}</p>
        @endif

        <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 pt-2 mt-auto">
            <span class="inline-flex items-center gap-1.5">
                <x-icon name="calendar" class="h-3.5 w-3.5" />
                <time datetime="{{ $news->published_at?->toDateString() }}">
                    {{ $news->published_at?->translatedFormat('d F Y') }}
                </time>
            </span>
            @if ($news->author)
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="user" class="h-3.5 w-3.5" />{{ $news->author->name }}
                </span>
            @endif
        </p>
    </div>
</article>
