<?php

namespace App\Services\Ai;

use App\Models\Setting;
use App\Services\Settings\SettingService;

/**
 * Where the assistant's configuration comes from.
 *
 * The panel first, the environment second — the same arrangement as the
 * channel keys, so an administrator can set this up without a deployment while
 * an existing `.env` keeps working.
 */
class AiSettings
{
    private const GROUP = 'ai';

    public function __construct(private readonly SettingService $settings)
    {
    }

    public function enabled(): bool
    {
        $stored = $this->value('enabled');

        return $stored === null ? (bool) config('ai.enabled') : (bool) $stored;
    }

    public function provider(): string
    {
        return (string) ($this->value('provider') ?: config('ai.provider', 'groq'));
    }

    /**
     * The address, from the chosen provider. Never typed by hand: a wrong
     * character here produces a bot that silently stops helping, and there is
     * nothing on screen for a person to check it against.
     */
    public function baseUrl(): string
    {
        return (string) (config('ai.base_url')
            ?: config('ai.providers.'.$this->provider().'.base_url', ''));
    }

    public function model(): string
    {
        $stored = (string) $this->value('model');
        $offered = $this->models();

        // Only a model the chosen provider actually offers. Switching provider
        // leaves the old model name behind, and sending it would fail on every
        // request until somebody noticed.
        if ($stored !== '' && array_key_exists($stored, $offered)) {
            return $stored;
        }

        return (string) (config('ai.model') ?: array_key_first($offered) ?: '');
    }

    /** @return array<string, string> */
    public function models(): array
    {
        return config('ai.providers.'.$this->provider().'.models', []);
    }

    /** Whether this provider authenticates at all. A local model does not. */
    public function needsKey(): bool
    {
        return (bool) config('ai.providers.'.$this->provider().'.needs_key', true);
    }

    public function apiKey(): ?string
    {
        return $this->value('api_key') ?: config('ai.api_key');
    }

    public function feature(string $name): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $stored = $this->value('feature_'.$name);

        return $stored === null ? (bool) config('ai.features.'.$name, false) : (bool) $stored;
    }

    /** Enough of the key to recognise it, never enough to use it. */
    public function keyHint(): ?string
    {
        $key = (string) $this->apiKey();

        return $key === '' ? null : str_repeat('•', 8).mb_substr($key, -4);
    }

    public function storedKeyPresent(): bool
    {
        return filled($this->value('api_key'));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->settings->group(self::GROUP);
    }

    public function put(array $values): void
    {
        $this->settings->put(self::GROUP, $values, [
            'enabled' => 'boolean',
            'feature_intent' => 'boolean',
            'feature_answers' => 'boolean',
            // The key is the one value worth protecting; the rest is a URL and
            // a model name that appear in any request anyway.
            'api_key' => 'encrypted',
            'provider' => 'string',
            'model' => 'string',
        ]);
    }

    private function value(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }
}
