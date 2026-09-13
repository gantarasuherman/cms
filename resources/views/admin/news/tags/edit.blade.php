<x-layouts.admin title="Ubah Tag">
    <x-ui.page-header title="Ubah Tag" />

    <form method="POST" action="{{ route('admin.news.tag.update', $tag) }}" class="max-w-xl">
        @csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input label="Nama" name="name" :value="$tag->name" required />
                <x-ui.input label="Slug" name="slug" :value="$tag->slug" />
            </div>
            <x-slot:footer>
                <x-ui.button :href="route('admin.news.tag.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button icon="save">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
