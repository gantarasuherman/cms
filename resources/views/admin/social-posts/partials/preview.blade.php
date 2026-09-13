@if ($post->hasImage())
    <a href="{{ $post->imageUrl() }}" data-lightbox="Gambar unggahan {{ $post->platformLabel() }}"
       class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
        <img src="{{ $post->imageUrl() }}" alt="" class="h-12 w-12 rounded-lg object-cover">
    </a>
@else
    {{-- The picture arrives with the next sync; until then say so rather than
         render a broken image. --}}
    <span class="grid h-12 w-12 place-items-center rounded-lg border border-dashed border-border text-muted-foreground"
          title="Gambar belum ada">
        <x-icon name="image" class="h-4 w-4" />
        <span class="sr-only">Belum ada gambar</span>
    </span>
@endif
