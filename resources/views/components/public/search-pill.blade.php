@props(['search' => null, 'type' => null])

@php $id = uniqid('hs'); @endphp

{{--
    The header's search control.

    Same route and same field names as the large search on the homepage, so a
    visitor's query means the same thing wherever they type it; only the
    proportions differ, which is why this is its own component rather than a
    mode flag on that one.
--}}
<form method="GET" action="{{ route('public.search') }}" role="search"
      class="flex h-11 w-full items-center rounded-full bg-slate-100 pl-5 pr-1.5 transition-colors focus-within:bg-white focus-within:ring-2 focus-within:ring-teal-700/30">

    <label for="q-{{ $id }}" class="sr-only">Cari di situs ini</label>
    <input type="search" id="q-{{ $id }}" name="q" value="{{ $search }}"
           placeholder="Cari berita, layanan, atau dokumen…"
           class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none">

    <span aria-hidden="true" class="mx-2 hidden h-5 w-px bg-slate-300 sm:block"></span>

    <label for="t-{{ $id }}" class="sr-only">Jenis yang dicari</label>
    <select id="t-{{ $id }}" name="jenis"
            class="mr-1.5 hidden cursor-pointer border-0 bg-transparent py-0 pl-0 pr-5 text-sm font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-700/40 sm:block">
        <option value="">Semua</option>
        @foreach (['berita' => 'Berita', 'layanan' => 'Layanan', 'dokumen' => 'Dokumen'] as $value => $label)
            <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
        @endforeach
    </select>

    {{-- The icon is the entire control, so its name is carried as text for
         anyone who cannot see the glyph. --}}
    <button type="submit"
            class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--brand-secondary,#EF8519)] text-[var(--brand-on-secondary,#0b1b2b)] transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary,#02468B)]">
        <span class="sr-only">Cari</span>
        <x-icon name="search" class="h-4 w-4" />
    </button>
</form>
