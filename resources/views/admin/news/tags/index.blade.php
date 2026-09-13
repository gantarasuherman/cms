<x-layouts.admin title="Tag">
    <x-ui.page-header title="Tag" description="Label bebas untuk mengelompokkan berita lintas kategori." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.data-table
                :url="route('admin.news.tag.data')"
                caption="Daftar tag"
                :columns="[
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'news_total', 'label' => 'Jumlah Berita', 'orderable' => false, 'searchable' => false],
                    ['key' => 'actions', 'label' => 'Aksi', 'orderable' => false, 'searchable' => false, 'raw' => true, 'class' => 'text-right'],
                ]" />
        </div>

        @can('create', App\Models\Tag::class)
            <form method="POST" action="{{ route('admin.news.tag.store') }}">
                @csrf
                <x-ui.card title="Tambah Tag">
                    <div class="space-y-5">
                        <x-ui.input label="Nama" name="name" required />
                        <x-ui.input label="Slug" name="slug" hint="Biarkan kosong untuk dibuat otomatis." />
                    </div>
                    <x-slot:footer>
                        <x-ui.button icon="plus">Tambah</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        @endcan
    </div>
</x-layouts.admin>
