<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingService;
use App\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One controller for every key-value settings screen. Which screen is being
 * edited comes from the route definition, and what it contains comes from
 * SettingsSchema — so adding a setting never means touching this class.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('viewAny', Setting::class);

        $group = $this->group();

        return view('admin.settings.edit', [
            'group' => $group,
            'schema' => SettingsSchema::group($group),
            'values' => $this->settings->group($group),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', new Setting());

        $group = $this->group();
        $schema = SettingsSchema::group($group);
        $validated = $request->validate(SettingsSchema::rules($group));

        $current = $this->settings->group($group);
        $values = [];

        foreach ($schema['fields'] as $key => $field) {
            $values[$key] = match ($field['type']) {
                // An unchecked box submits nothing, so absence means false.
                'boolean' => $request->boolean($key),
                'image' => $this->resolveImage($request, $key, $current[$key] ?? null),
                default => $validated[$key] ?? null,
            };
        }

        $this->settings->put($group, $values, SettingsSchema::types($group));

        $this->audit->record('update', 'settings.'.$group, null, $current, $values);
        $this->cache->flushAll();

        return back()->with('success', $schema['title'].' berhasil disimpan.');
    }

    /**
     * Keeps the stored path unless a new file arrives or removal is requested,
     * so saving the form without re-picking a logo does not wipe it.
     */
    private function resolveImage(Request $request, string $key, ?string $current): ?string
    {
        if ($request->boolean('remove_'.$key)) {
            $this->media->delete($current);

            return null;
        }

        if ($request->hasFile($key)) {
            return $this->media->replacePublic($current, $request->file($key), 'settings');
        }

        return $current;
    }

    private function group(): string
    {
        return (string) request()->route()->defaults['group'];
    }
}

