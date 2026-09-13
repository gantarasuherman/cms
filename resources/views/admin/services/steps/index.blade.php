<x-layouts.admin title="Tahapan Layanan">
    <x-ui.page-header :title="$service->name" description="Alur yang dilalui permohonan, urut dari awal.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.services.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.services.partials.tabs', ['service' => $service])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Tahapan">
                @if ($items->isEmpty())
                    <p class="py-6 text-center text-sm text-muted-foreground">Belum ada tahapan.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($items as $step)
                            <li class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-muted text-sm font-semibold text-foreground">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-foreground">{{ $step->name }}</p>
                                    @if ($step->description)
                                        <p class="mt-0.5 text-sm text-muted-foreground">{{ $step->description }}</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <a href="{{ route('admin.services.steps.edit', [$service, $step]) }}"
                                       class="rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">Ubah</a>
                                    <x-ui.delete-form :action="route('admin.services.steps.destroy', [$service, $step])" />
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <form method="POST" action="{{ route('admin.services.steps.store', $service) }}">
            @csrf
            <x-ui.card title="Tambah Tahapan">
                <div class="space-y-5">
                    <x-ui.input label="Nama Tahapan" name="name" required hint="Misalnya: Pengajuan, Verifikasi, Selesai." />
                    <x-ui.textarea label="Keterangan" name="description" :rows="3" />
                    <x-ui.icon-select name="icon" hint="Opsional." />
                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0" value="{{ $items->count() * 10 + 10 }}" />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="plus">Tambah</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
