<section class="sarab-section bg-[var(--sarab-dark)] text-white">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            @if ($section->title)
                <h2 class="!text-white">{{ $section->title }}</h2>
            @endif
            @if ($section->subtitle)
                <p class="sarab-lead mt-4 text-white/80">{{ $section->subtitle }}</p>
            @endif
        </div>

        @if ($section->content)
            <p class="text-white/80">{{ $section->content }}</p>
        @endif
    </div>
</section>
