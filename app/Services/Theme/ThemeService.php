<?php

namespace App\Services\Theme;

use App\Services\Settings\SettingService;
use App\Support\Color;

/**
 * Turns the two choices an administrator makes — a primary colour and a
 * typeface — into the custom properties the public site is painted with.
 *
 * The public views were already written against Tailwind's `teal-*` utilities,
 * and in Tailwind v4 every one of those compiles to `var(--color-teal-NNN)`.
 * Redefining those variables for public pages therefore retints the whole site
 * without editing a hundred class attributes — and, more importantly, without
 * a second colour system that could drift out of step with the first. The
 * names stay `teal` because that is what the utilities are called; what they
 * resolve to is whatever the administrator chose.
 */
class ThemeService
{
    /**
     * Used when nothing has been saved yet, and when a stored value fails
     * validation — a bad row in `settings` must not be able to leave the site
     * unpainted.
     */
    public const DEFAULT_PRIMARY = '#02468B';

    public const DEFAULT_SECONDARY = '#EF8519';

    public const DEFAULT_FOOTER = '#F8FAFC';

    public const DEFAULT_FONT = 'inter';

    /**
     * Only self-hosted and system stacks are offered. Loading a typeface from
     * a third-party CDN would hand every visitor's IP address to that CDN,
     * which is the same reason the visitor statistics store no IP at all.
     *
     * @var array<string, array{label: string, stack: string}>
     */
    public const FONTS = [
        'inter' => [
            'label' => 'Inter — modern, netral (bawaan)',
            'stack' => "'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        ],
        'system' => [
            'label' => 'Sistem — mengikuti perangkat pengunjung',
            'stack' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
        ],
        'serif' => [
            'label' => 'Serif — formal, bergaya cetak',
            'stack' => "ui-serif, Georgia, Cambria, 'Times New Roman', serif",
        ],
    ];

    /**
     * Font choices as the settings form needs them.
     *
     * @return array<string, string>
     */
    public static function fontOptions(): array
    {
        return array_map(fn (array $font) => $font['label'], self::FONTS);
    }

    public function __construct(private readonly SettingService $settings)
    {
    }

    public function primary(): string
    {
        return $this->color('primary_color', self::DEFAULT_PRIMARY);
    }

    public function secondary(): string
    {
        return $this->color('secondary_color', self::DEFAULT_SECONDARY);
    }

    public function footer(): string
    {
        return $this->color('footer_color', self::DEFAULT_FOOTER);
    }

    /** @return array{label: string, stack: string} */
    public function font(): array
    {
        $key = (string) ($this->stored()['font_family'] ?? self::DEFAULT_FONT);

        return self::FONTS[$key] ?? self::FONTS[self::DEFAULT_FONT];
    }

    /** @return array<string, string> Custom property name => value */
    public function variables(): array
    {
        $primary = $this->primary();
        $secondary = $this->secondary();
        $variables = [];

        // The ramp the existing utilities read through.
        foreach (Color::ramp($primary) as $step => $hex) {
            $variables['--color-teal-'.$step] = $hex;
        }

        $variables['--brand-primary'] = $primary;
        $variables['--brand-secondary'] = $secondary;

        // What may sit *on* each brand colour, and what each may be written
        // *in*. Derived rather than assumed: white on the default orange is
        // 2.61:1 and fails, so the pairing has to be measured every time.
        $variables['--brand-on-primary'] = Color::ink($primary);
        $variables['--brand-on-secondary'] = Color::ink($secondary);
        $variables['--brand-primary-ink'] = Color::readable($primary);
        $variables['--brand-secondary-ink'] = Color::readable($secondary);

        // The footer may be painted light or dark, so none of its text
        // colours can be fixed: each is derived against the chosen ground and
        // moved until it clears AA. A literal slate would be invisible the
        // moment someone picks a dark navy.
        $footer = $this->footer();
        $footerInk = Color::ink($footer);

        $variables['--footer-bg'] = $footer;
        $variables['--footer-ink'] = $footerInk;
        $variables['--footer-muted'] = Color::against($footer, Color::mix($footerInk, $footer, 0.72));
        $variables['--footer-link'] = Color::against($footer, $primary);
        // Separators are not text; they only have to be perceivable.
        $variables['--footer-border'] = Color::mix($footerInk, $footer, 0.16);
        $variables['--footer-surface'] = Color::mix($footerInk, $footer, 0.06);

        $variables['--font-public'] = $this->font()['stack'];

        return $variables;
    }

    /**
     * The `<style>` body for the public layout head.
     *
     * The selector is doubled on purpose. Tailwind declares its own
     * `:root { --color-teal-*: … }` inside the compiled stylesheet, and a
     * plain `:root` here would only win by coming later in the document —
     * which it does in a production build but not under the dev server, where
     * Vite injects the stylesheet from JavaScript after this block. `:root:root`
     * outranks it on specificity, so the theme holds in both.
     */
    public function css(): string
    {
        $declarations = '';

        foreach ($this->variables() as $property => $value) {
            $declarations .= $property.':'.$value.';';
        }

        return ':root:root{'.$declarations.'}';
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return $this->settings->group('appearance');
    }

    /**
     * Normalised on both paths, so callers and tests never have to care
     * whether a value came from the database or from the constant.
     */
    private function color(string $key, string $fallback): string
    {
        $value = $this->stored()[$key] ?? null;

        return Color::normalise(Color::isHex($value) ? $value : $fallback);
    }
}
