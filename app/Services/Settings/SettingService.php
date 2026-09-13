<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to the key-value settings store, grouped by screen
 * (general, seo, accessibility, footer, ...). Reads are cached per group and
 * invalidated on write, which is what keeps the public site cheap.
 */
class SettingService
{
    private const CACHE_PREFIX = 'settings.';

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$group, function () use ($group) {
            return Setting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->typedValue()])
                ->all();
        });
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($group)[$key] ?? $default;
    }

    /**
     * Writes a whole group at once. Types are preserved from the existing row
     * when present, so a checkbox stays a boolean rather than silently
     * becoming the string "1".
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $types
     */
    public function put(string $group, array $values, array $types = []): void
    {
        foreach ($values as $key => $value) {
            $type = $types[$key] ?? $this->inferType($value);

            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $this->encode($value, $type), 'type' => $type],
            );
        }

        $this->forget($group);
    }

    public function forget(?string $group = null): void
    {
        if ($group !== null) {
            Cache::forget(self::CACHE_PREFIX.$group);

            return;
        }

        foreach (Setting::query()->distinct()->pluck('group') as $name) {
            Cache::forget(self::CACHE_PREFIX.$name);
        }
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function encode(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'encrypted' => blank($value) ? null : \Illuminate\Support\Facades\Crypt::encryptString((string) $value),
            default => $value === null ? null : (string) $value,
        };
    }
}

