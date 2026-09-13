<x-layouts.public>
    {{-- The homepage follows the sarab reference; the rest of the public site
         keeps its own treatment. Scoped by this wrapper so the two cannot
         bleed into each other. --}}
    <div class="sarab">
        @forelse ($sections as $entry)
            @include('public.sections.'.$entry['section']->type, [
                'section' => $entry['section'],
                'items' => $entry['items'],
            ])
        @empty
            <div class="sarab-section sarab-cream">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <x-public.empty-state
                        title="Beranda belum disusun"
                        description="Bagian beranda diatur dari panel admin pada menu Website → Beranda."
                        icon="house" />
                </div>
            </div>
        @endforelse
    </div>
</x-layouts.public>
