<x-layouts.public title="Pratinjau Pengumuman" :noindex="true" :announcements="false">
    {{-- Deliberately includes notices that are switched off or still scheduled,
         and forces the modal open regardless of what this browser has already
         dismissed — the point is to check one before it goes live. --}}
    <x-public.announcements :announcements="$announcements" :force="true" />

    <div class="mx-auto max-w-3xl px-4 py-16">
        <p class="mb-2 text-sm font-semibold uppercase tracking-widest text-teal-700">Pratinjau</p>
        <h1 class="text-3xl font-extrabold text-slate-900">Pengumuman</h1>
        <p class="mt-3 text-slate-600">
            Halaman ini menampilkan seluruh pengumuman, termasuk yang belum aktif dan yang masih
            terjadwal. Strip di atas adalah tampilan yang menetap setelah modal ditutup.
        </p>

        <ul class="mt-8 space-y-3">
            @forelse ($announcements as $announcement)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-900">{{ $announcement->title }}</p>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $announcement->isLive() ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700' }}">
                            {{ $announcement->isLive() ? 'Tayang' : 'Tidak tayang' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ $announcement->tickerText() }}</p>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
                    Belum ada pengumuman.
                </li>
            @endforelse
        </ul>
    </div>
</x-layouts.public>
