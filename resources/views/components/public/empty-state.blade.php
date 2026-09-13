@props(['title' => 'Belum ada data', 'description' => null, 'icon' => 'search'])

<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
    <span class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-white text-slate-400">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <p class="text-base font-semibold text-slate-900">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-1.5 max-w-md text-sm text-slate-600">{{ $description }}</p>
    @endif
</div>
