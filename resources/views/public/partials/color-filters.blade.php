{{--
    Colour-vision simulation filters.

    These are applied to the whole page when a visitor picks a mode, so that
    information carried by colour alone becomes visible to them. Meaning is
    never left to colour anyway — status is always icon + wording + colour —
    but this covers imagery and charts too.

    Matrices from the Machado, Oliveira & Fernandes (2009) model.
--}}
<svg class="absolute h-0 w-0" aria-hidden="true" focusable="false">
    <defs>
        <filter id="a11y-protanopia">
            <feColorMatrix type="matrix" values="0.567 0.433 0     0 0
                                                 0.558 0.442 0     0 0
                                                 0     0.242 0.758 0 0
                                                 0     0     0     1 0" />
        </filter>
        <filter id="a11y-deuteranopia">
            <feColorMatrix type="matrix" values="0.625 0.375 0     0 0
                                                 0.7   0.3   0     0 0
                                                 0     0.3   0.7   0 0
                                                 0     0     0     1 0" />
        </filter>
        <filter id="a11y-tritanopia">
            <feColorMatrix type="matrix" values="0.95  0.05  0     0 0
                                                 0     0.433 0.567 0 0
                                                 0     0.475 0.525 0 0
                                                 0     0     0     1 0" />
        </filter>
        <filter id="a11y-grayscale">
            <feColorMatrix type="matrix" values="0.299 0.587 0.114 0 0
                                                 0.299 0.587 0.114 0 0
                                                 0.299 0.587 0.114 0 0
                                                 0     0     0     1 0" />
        </filter>
    </defs>
</svg>
