<x-layouts.admin title="Media">
    <x-ui.page-header title="Media" description="Gambar, video, dan berkas yang dapat dipakai ulang di seluruh situs." />

    <div class="grid gap-6 lg:grid-cols-4">
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="lg:col-span-1">
            @csrf
            <x-ui.card title="Unggah Berkas">
                <div class="space-y-5">
                    <x-ui.input label="Berkas" name="file" type="file" required
                                hint="Gambar, video, atau dokumen. Maksimal 50 MB." />
                    <x-ui.input label="Teks alternatif" name="alt_text"
                                hint="Menjelaskan isi gambar bagi pengguna pembaca layar." />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="upload">Unggah</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <div class="lg:col-span-3">
            <nav aria-label="Saring jenis media" class="mb-4 flex flex-wrap gap-2">
                @foreach ([['', 'Semua', 'layers'], ['image', 'Gambar', 'image'], ['video', 'Video', 'video'], ['document', 'Dokumen', 'file-text']] as [$value, $label, $icon])
                    <a href="{{ route('admin.media.index', $value ? ['type' => $value] : []) }}"
                       @if ($type === $value) aria-current="page" @endif
                       class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium {{ $type === $value ? 'border-transparent bg-primary text-primary-foreground' : 'border-border bg-card text-foreground hover:bg-muted' }}">
                        <x-icon :name="$icon" class="h-4 w-4" />{{ $label }}
                    </a>
                @endforeach
            </nav>

            @if ($items->isEmpty())
                <x-ui.card>
                    <p class="py-10 text-center text-sm text-muted-foreground">Belum ada berkas.</p>
                </x-ui.card>
            @else
                <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($items as $media)
                        <li class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm">
                            <div class="grid aspect-video place-items-center bg-muted">
                                @if ($media->type === 'image')
                                    <img src="{{ $media->url }}" alt="{{ $media->alt_text ?: $media->name }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <x-icon :name="$media->type === 'video' ? 'video' : 'file-text'" class="h-8 w-8 text-muted-foreground" />
                                @endif
                            </div>

                            <div class="p-3">
                                <p class="truncate text-sm font-medium text-foreground" title="{{ $media->name }}">{{ $media->name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ strtoupper($media->extension) }} · {{ number_format($media->size / 1024, 0) }} KB
                                </p>

                                <div class="mt-2 flex items-center justify-between">
                                    @if ($media->disk === 'public')
                                        <a href="{{ $media->url }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 text-xs font-medium hover:underline">
                                            <x-icon name="external-link" class="h-3.5 w-3.5" />Buka
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <x-icon name="lock" class="h-3.5 w-3.5" />Privat
                                        </span>
                                    @endif

                                    @can('delete', $media)
                                        <x-ui.delete-form :action="route('admin.media.destroy', $media)" label="Hapus" />
                                    @endcan
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.admin>
