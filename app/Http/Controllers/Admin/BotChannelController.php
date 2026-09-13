<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Models\Bot\BotFlow;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Where the platform keys are entered.
 *
 * Secrets get three protections here, because a settings screen is the easiest
 * place in an application to leak one:
 *
 *  1. Stored encrypted (`encrypted:array` on the model).
 *  2. Never echoed back into the form — the field shows a hint like ••••1234
 *     and an empty box means "leave it as it is", so a secret cannot be read
 *     out of the page source by anyone who can open the screen.
 *  3. Never written to the audit log. `AuditLogger` records the before and
 *     after of a change, which for a token would be the token itself in clear
 *     text, readable by anybody with audit access.
 */
class BotChannelController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', BotChannel::class);

        return view('admin.bot.channels.index', [
            'channels' => BotChannel::with('flow')->orderBy('name')->get(),
            'flows' => BotFlow::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, BotChannel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $fields = $channel->credentialFields();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'bot_flow_id' => ['nullable', 'integer', 'exists:bot_flows,id'],
            'greeting' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($fields as $field => $meta) {
            $rules[$field] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        $credentials = $channel->credentials ?? [];
        $changed = [];

        foreach ($fields as $field => $meta) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value === '') {
                // Empty means "leave it": the form never shows the current
                // value, so a blank box cannot be read as "clear this".
                continue;
            }

            if ($value === $channel->credentialHint($field)) {
                // The masked hint came back unchanged.
                continue;
            }

            $credentials[$field] = $value;
            // The name of what changed, never its value.
            $changed[] = $field;
        }

        $channel->fill([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
            'bot_flow_id' => $data['bot_flow_id'] ?? null,
            'settings' => array_merge($channel->settings ?? [], ['greeting' => $data['greeting'] ?? null]),
            'credentials' => $credentials,
        ]);

        $wasActive = $channel->getOriginal('is_active');
        $channel->save();

        // Only what changed, by name. A token must never reach this table.
        $this->audit->record('update', 'bot_channel', $channel->getKey(),
            ['is_active' => (bool) $wasActive],
            ['is_active' => $channel->is_active, 'kredensial_diubah' => $changed],
        );

        if ($channel->is_active && ! $channel->credentialsPresent()) {
            return back()->with('warning', 'Kanal diaktifkan, tetapi masih ada kredensial yang kosong: '
                .implode(', ', $channel->missingCredentials()).'. Kanal tidak akan menjawab sampai terisi.');
        }

        return back()->with('success', $channel->name.' disimpan.');
    }

    /** Clears one stored key, so a leaked one can be taken out of use at once. */
    public function forget(Request $request, BotChannel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $field = (string) $request->input('field');

        abort_unless(array_key_exists($field, $channel->credentialFields()), 404);

        $credentials = $channel->credentials ?? [];
        unset($credentials[$field]);
        $channel->update(['credentials' => $credentials]);

        $this->audit->record('update', 'bot_channel', $channel->getKey(), null, ['kredensial_dihapus' => $field]);

        return back()->with('success', 'Nilai dihapus. Bila server masih menyetelnya lewat .env, nilai itu yang dipakai.');
    }

    /**
     * Asks the platform who this bot is.
     *
     * The only honest way to tell an administrator a key works: a stored token
     * that is merely present proves nothing.
     */
    public function test(BotChannel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        if (! $channel->credentialsPresent()) {
            return back()->with('warning', 'Lengkapi dulu: '.implode(', ', $channel->missingCredentials()).'.');
        }

        try {
            $response = match ($channel->key) {
                BotChannel::TELEGRAM => Http::timeout(10)
                    ->get('https://api.telegram.org/bot'.$channel->credential('token').'/getMe'),
                BotChannel::WHATSAPP => Http::timeout(10)
                    ->withToken((string) $channel->credential('token'))
                    ->get('https://graph.facebook.com/'.config('bot.whatsapp.version').'/'.$channel->credential('phone_number_id'),
                        ['fields' => 'display_phone_number,verified_name']),
                default => null,
            };
        } catch (\Throwable $e) {
            $channel->update(['last_error' => mb_substr($e->getMessage(), 0, 500), 'verified_at' => null]);

            return back()->with('warning', 'Tidak dapat menghubungi platform: '.$e->getMessage());
        }

        if (! $response || $response->failed()) {
            // The token travels in the URL for Telegram, so an echoed error
            // could carry it straight back onto the screen.
            $message = str_replace(
                (string) $channel->credential('token'),
                '[token]',
                (string) ($response?->json('description') ?? $response?->json('error.message') ?? 'Gagal.'),
            );

            $channel->update(['last_error' => mb_substr($message, 0, 500), 'verified_at' => null]);

            return back()->with('warning', 'Kredensial ditolak platform: '.$message);
        }

        $name = $response->json('result.username')
            ?? $response->json('verified_name')
            ?? $response->json('display_phone_number')
            ?? 'terhubung';

        $channel->update(['verified_at' => now(), 'last_error' => null]);

        return back()->with('success', $channel->name.' terhubung sebagai '.$name.'.');
    }
}
