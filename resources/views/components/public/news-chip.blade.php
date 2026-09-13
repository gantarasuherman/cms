@props(['category', 'index' => 0, 'solid' => false])

@php
    // Pastel rotation from the source theme; the chip carries dark ink so the
    // label clears contrast on whichever pastel it lands.
    $tone = $solid ? 'news-chip-solid' : 'news-chip-'.(($index % 4) + 1);
@endphp

<a href="{{ route('public.news.index', ['kategori' => $category->slug]) }}"
   class="news-chip {{ $tone }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#db3700]">
    {{ $category->name }}
</a>
