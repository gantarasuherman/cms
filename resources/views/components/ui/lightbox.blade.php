{{--
    One image viewer for the whole panel.

    A native <dialog>, so the focus trap, Escape and making the page behind
    inert are the browser's work rather than ours. Rendered once per page;
    every [data-lightbox] link on the page opens it.

    Opening a photograph in a new tab — which is what these links did — loses
    the complaint you were reading and gives you a bare image on a white page
    with no way back but the browser's own button.
--}}
<dialog data-lightbox-dialog
        class="m-auto max-h-[calc(100dvh-2rem)] w-[min(64rem,calc(100vw-2rem))] rounded-2xl bg-card p-0 text-card-foreground shadow-2xl backdrop:bg-slate-950/80">

    <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
        <p data-lightbox-title class="min-w-0 truncate text-sm font-medium"></p>

        <div class="flex shrink-0 items-center gap-1">
            <span data-lightbox-counter class="mr-2 text-xs tabular-nums text-muted-foreground"></span>

            <button type="button" data-lightbox-prev
                    class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <span class="sr-only">Gambar sebelumnya</span>
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>

            <button type="button" data-lightbox-next
                    class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <span class="sr-only">Gambar berikutnya</span>
                <x-icon name="chevron-right" class="h-4 w-4" />
            </button>

            <a data-lightbox-open href="#" target="_blank" rel="noopener"
               class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <span class="sr-only">Buka ukuran penuh di tab baru</span>
                <x-icon name="external-link" class="h-4 w-4" />
            </a>

            <button type="button" data-lightbox-close autofocus
                    class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <span class="sr-only">Tutup</span>
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>

    <div class="flex max-h-[calc(100dvh-8rem)] items-center justify-center overflow-auto bg-muted/40 p-4">
        <img data-lightbox-image src="" alt="" class="max-h-full max-w-full rounded-lg object-contain">
    </div>
</dialog>
