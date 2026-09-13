<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiSettings;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly AiSettings $settings,
        private readonly AiAssistant $assistant,
        private readonly AuditLogger $audit,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('viewAny', BotChannel::class);

        return view('admin.bot.ai', [
            'settings' => $this->settings,
            'providers' => config('ai.providers'),
            'available' => $this->assistant->available(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', new BotChannel());

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'provider' => ['required', 'string', Rule::in(array_keys(config('ai.providers')))],
            // Only a model the chosen provider offers. The form is a dropdown,
            // so anything else arrived by hand.
            'model' => ['nullable', 'string', Rule::in(array_keys(
                config('ai.providers.'.$request->input('provider').'.models', []),
            ))],
            'api_key' => ['nullable', 'string', 'max:300'],
            'feature_intent' => ['nullable', 'boolean'],
            'feature_answers' => ['nullable', 'boolean'],
        ]);

        $values = [
            'enabled' => $request->boolean('enabled'),
            'provider' => $data['provider'],
            'model' => $data['model'] ?? null,
            'feature_intent' => $request->boolean('feature_intent'),
            'feature_answers' => $request->boolean('feature_answers'),
        ];

        $key = trim((string) ($data['api_key'] ?? ''));

        // Empty means "leave it": the form never shows the stored key, so a
        // blank box cannot be read as a request to clear it.
        if ($key !== '' && $key !== $this->settings->keyHint()) {
            $values['api_key'] = $key;
        }

        $this->settings->put($values);

        // The names of what changed, never the key itself.
        $this->audit->record('update', 'settings.ai', null, null, [
            'enabled' => $values['enabled'],
            'provider' => $values['provider'],
            'kunci_diubah' => array_key_exists('api_key', $values),
        ]);

        return back()->with('success', 'Pengaturan AI disimpan.');
    }

    public function forget(): RedirectResponse
    {
        $this->authorize('update', new BotChannel());

        $this->settings->put(['api_key' => null]);
        $this->audit->record('update', 'settings.ai', null, null, ['kunci_dihapus' => true]);

        return back()->with('success', 'Kunci API dihapus.');
    }

    /** Asks the model to answer something trivial, which is the only real proof. */
    public function test(): RedirectResponse
    {
        $this->authorize('update', new BotChannel());

        if (! $this->assistant->available()) {
            return back()->with('warning', 'Lengkapi dulu penyedia, alamat API, model, dan kunci.');
        }

        $reply = $this->assistant->provider()->complete([
            ['role' => 'system', 'content' => 'Balas dengan satu kata: SIAP'],
            ['role' => 'user', 'content' => 'Uji koneksi.'],
        ], 0.0, 12);

        return $reply === null
            ? back()->with('warning', 'Model tidak menjawab. Periksa kunci, alamat, dan nama modelnya.')
            : back()->with('success', 'Model menjawab: '.mb_substr($reply, 0, 80));
    }
}
