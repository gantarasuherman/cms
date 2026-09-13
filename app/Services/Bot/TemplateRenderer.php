<?php

namespace App\Services\Bot;

use App\Services\Public\SiteContext;

/**
 * Fills `{placeholder}` slots in the wording an administrator typed.
 *
 * Deliberately not Blade. These templates are written in an admin form by
 * someone who is not a developer, and Blade would execute PHP from that form —
 * a settings screen that runs code is a settings screen that grants shell.
 * Here an unknown placeholder is left alone rather than being an error, so a
 * typo shows up in the chat instead of breaking the conversation.
 */
class TemplateRenderer
{
    public function __construct(private readonly SiteContext $site)
    {
    }

    /** @param array<string, mixed> $values */
    public function render(?string $template, array $values = []): string
    {
        if (blank($template)) {
            return '';
        }

        $values += $this->siteValues();

        return preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            function (array $matches) use ($values) {
                $key = strtolower($matches[1]);

                // An unknown placeholder stays visible as written: silently
                // deleting it would hide the mistake from whoever typed it.
                return array_key_exists($key, $values)
                    ? (string) $values[$key]
                    : $matches[0];
            },
            $template,
        ) ?? $template;
    }

    /** @return array<string, string> */
    private function siteValues(): array
    {
        $general = $this->site->general();

        return [
            'site_name' => $this->site->siteName(),
            'site_email' => (string) ($general['email'] ?? ''),
            'site_phone' => (string) ($general['phone'] ?? ''),
            'site_url' => config('app.url'),
        ];
    }
}
