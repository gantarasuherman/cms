@props(['search' => null, 'type' => null])

@php $id = uniqid('s'); @endphp

{{--
    Segmented search pill.

    The segments are the two filters this site can actually honour — a keyword
    and a content type. Copying the reference's third segment would mean a
    control that looks operable and answers nothing.
--}}
<form method="GET" action="{{ route('public.search') }}" role="search"
      class="mx-auto flex w-full max-w-3xl flex-col gap-2 rounded-3xl bg-white p-2 shadow-[0_8px_40px_rgb(0_0_0/0.12)] sm:flex-row sm:items-stretch sm:rounded-full sm:p-1.5">

    <div class="min-w-0 flex-1 rounded-2xl px-5 py-3 transition-colors hover:bg-[var(--sarab-cream)] focus-within:bg-[var(--sarab-cream)] sm:rounded-full">
        <label for="q-{{ $id }}" class="block text-xs font-semibold text-[var(--sarab-dark)]">Cari</label>
        <input type="search" id="q-{{ $id }}" name="q" value="{{ $search }}"
               placeholder="Berita, layanan, atau dokumen…"
               class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm text-[#3f3f46] placeholder:text-[#a1a1aa] focus:outline-none">
    </div>

    <div aria-hidden="true" class="hidden w-px self-stretch bg-[#e4e4e7] sm:block"></div>

    <div class="min-w-0 rounded-2xl px-5 py-3 transition-colors hover:bg-[var(--sarab-cream)] focus-within:bg-[var(--sarab-cream)] sm:w-48 sm:rounded-full">
        <label for="t-{{ $id }}" class="block text-xs font-semibold text-[var(--sarab-dark)]">Jenis</label>
        <select id="t-{{ $id }}" name="jenis"
                class="mt-0.5 w-full cursor-pointer border-0 bg-transparent p-0 text-sm text-[#3f3f46] focus:outline-none">
            <option value="">Semua</option>
            @foreach (['berita' => 'Berita', 'layanan' => 'Layanan', 'dokumen' => 'Dokumen'] as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- `self-center` is load-bearing: the button has a fixed height inside a
         stretch row, and a flex item with an explicit size aligns to the start
         of the line, which left it sitting 6px above the centre of the taller
         segments. --}}
    <button type="submit"
            class="flex shrink-0 items-center justify-center gap-2 rounded-full bg-[var(--sarab-primary)] px-6 py-4 font-semibold text-[var(--sarab-on-primary)] transition-colors hover:bg-[color-mix(in_oklab,var(--sarab-primary)_84%,black)] sm:h-14 sm:w-14 sm:self-center sm:px-0 sm:py-0">
        <x-icon name="search" class="h-5 w-5" />
        {{-- The icon is the whole control above sm, so the name lives here. --}}
        <span class="sm:sr-only">Cari</span>
    </button>
</form>
