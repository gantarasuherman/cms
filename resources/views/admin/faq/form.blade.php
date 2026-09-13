@php $editing = $faq->exists; @endphp

<x-layouts.admin :title="$editing ? 'Ubah FAQ' : 'Tambah FAQ'">
    <x-ui.page-header :title="$editing ? 'Ubah FAQ' : 'Tambah FAQ'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.faq.index')" variant="secondary" icon="chevron-left">Kembali</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('admin.faq.update', $faq) : route('admin.faq.store') }}"
          class="max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Pertanyaan" name="question" :value="$faq->question" required />
                <x-ui.textarea label="Jawaban" name="answer" :value="$faq->answer" :rows="10" required />
                <x-ui.select label="Kategori" name="category_id" :options="$categories" :value="$faq->category_id"
                             placeholder="— Tanpa kategori —" />
                <x-ui.input label="Urutan" name="sort_order" type="number" min="0" :value="$faq->sort_order ?? 0" />
                <x-ui.checkbox label="Aktif" name="is_active" :checked="$faq->is_active ?? true" />
            </div>

            <x-slot:footer>
                <x-ui.button :href="route('admin.faq.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
