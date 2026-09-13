<x-layouts.admin title="Persyaratan Layanan">
    <x-ui.page-header :title="$service->name" description="Persyaratan yang harus dipenuhi pemohon.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.services.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.services.partials.tabs', ['service' => $service])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Daftar Persyaratan">
                @if ($items->isEmpty())
                    <p class="py-6 text-center text-sm text-muted-foreground">Belum ada persyaratan.</p>
                @else
                    <ol class="divide-y divide-border">
                        @foreach ($items as $requirement)
                            <li class="flex items-start gap-3 py-3">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-foreground">{{ $requirement->name }}</p>
                                    @if ($requirement->description)
                                        <p class="mt-0.5 text-sm text-muted-foreground">{{ $requirement->description }}</p>
                                    @endif
                                    <p class="mt-1 text-xs">
                                        @if ($requirement->is_required)
                                            <span class="inline-flex items-center gap-1 text-destructive"><x-icon name="circle-alert" class="h-3.5 w-3.5" /> Wajib</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-muted-foreground"><x-icon name="circle-slash" class="h-3.5 w-3.5" /> Opsional</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <a href="{{ route('admin.services.requirements.edit', [$service, $requirement]) }}"
                                       class="rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">Ubah</a>
                                    <x-ui.delete-form :action="route('admin.services.requirements.destroy', [$service, $requirement])" label="Hapus" />
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <form method="POST" action="{{ route('admin.services.requirements.store', $service) }}">
            @csrf
            <x-ui.card title="Tambah Persyaratan">
                <div class="space-y-5">
                    <x-ui.input label="Nama" name="name" required />
                    <x-ui.textarea label="Keterangan" name="description" :rows="3" />
                    <x-ui.input label="Urutan" name="sort_order" type="number" min="0" value="{{ $items->count() * 10 + 10 }}" />
                    <x-ui.checkbox label="Wajib dipenuhi" name="is_required" :checked="true" />
                </div>
                <x-slot:footer>
                    <x-ui.button icon="plus">Tambah</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
