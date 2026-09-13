<x-layouts.admin :title="$schema['title']">
    <x-ui.page-header :title="$schema['title']" :description="$schema['description']" />

    @include('admin.settings.partials.tabs')

    <form method="POST" action="{{ route('admin.settings.'.$group.'.update') }}"
          enctype="multipart/form-data" class="max-w-3xl">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="space-y-6">
                @foreach ($schema['fields'] as $key => $field)
                    @php $value = $values[$key] ?? null; @endphp

                    @switch($field['type'])
                        @case('boolean')
                            <x-ui.checkbox :label="$field['label']" :name="$key"
                                           :checked="(bool) $value" :hint="$field['hint'] ?? null" />
                            @break

                        @case('textarea')
                            <x-ui.textarea :label="$field['label']" :name="$key" :value="$value"
                                           :rows="4" :hint="$field['hint'] ?? null" />
                            @break

                        @case('color')
                            <x-ui.color-input :label="$field['label']" :name="$key" :value="$value"
                                              :hint="$field['hint'] ?? null" />
                            @break

                        @case('select')
                            <x-ui.select :label="$field['label']" :name="$key"
                                         :options="$field['options']" :value="$value"
                                         :hint="$field['hint'] ?? null" />
                            @break

                        @case('image')
                            <div>
                                <p class="mb-1.5 text-sm font-medium text-foreground">{{ $field['label'] }}</p>

                                @if ($value)
                                    <div class="mb-3 flex items-center gap-3 rounded-lg border border-border bg-muted p-3">
                                        <img src="{{ Storage::disk('public')->url($value) }}"
                                             alt="{{ $field['label'] }} saat ini"
                                             class="h-12 w-auto max-w-32 object-contain">
                                        <x-ui.checkbox label="Hapus saat menyimpan" :name="'remove_'.$key" />
                                    </div>
                                @endif

                                <x-ui.input :label="$value ? 'Ganti berkas' : 'Unggah berkas'" :name="$key"
                                            type="file" accept="image/*" :hint="$field['hint'] ?? null" />
                            </div>
                            @break

                        @default
                            <x-ui.input :label="$field['label']" :name="$key" :value="$value"
                                        :type="in_array($field['type'], ['email', 'url', 'number']) ? $field['type'] : 'text'"
                                        :required="in_array('required', $field['rules'] ?? [])"
                                        :hint="$field['hint'] ?? null" />
                    @endswitch
                @endforeach
            </div>

            <x-slot:footer>
                <x-ui.button icon="save">Simpan Pengaturan</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
