<section class="sarab-section" aria-labelledby="home-services">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-public.card-row
            label="Layanan"
            eyebrow="Pelayanan"
            :title="$section->title ?: 'Layanan'"
            :subtitle="$section->subtitle ?: 'Persyaratan, tarif, dan waktu penyelesaian setiap layanan.'"
            id="home-services">
            @foreach ($items as $service)
                <li class="w-64 shrink-0 snap-start sm:w-72">
                    <x-public.media-card
                        :href="route('public.services.show', $service->slug)"
                        :title="$service->name"
                        :image="$service->image ? Storage::disk('public')->url($service->image) : null"
                        :badge="$service->category?->name"
                        :meta="$service->processing_time"
                        :icon="$service->icon ?: 'briefcase'" />
                </li>
            @endforeach
        </x-public.card-row>

        <p class="mt-10">
            <a href="{{ route('public.services.index') }}" class="sarab-btn sarab-btn-outline">
                Semua Layanan<x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>
