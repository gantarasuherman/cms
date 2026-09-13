<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use Illuminate\Http\JsonResponse;

/**
 * Hands the bot service the keys it needs to speak.
 *
 * Without this, credentials entered in the admin panel would sit in the
 * database while the bot kept using whatever its own environment held — the
 * screen would look like it worked and change nothing.
 *
 * Behind the same shared secret as every other internal route, and it returns
 * only channels that are switched on: a channel an administrator disabled must
 * go quiet even if the service is still running.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $channels = BotChannel::where('is_active', true)->get()
            ->filter(fn (BotChannel $channel) => $channel->credentialsPresent())
            ->mapWithKeys(fn (BotChannel $channel) => [
                $channel->key => collect(array_keys($channel->credentialFields()))
                    ->mapWithKeys(fn (string $field) => [$field => $channel->credential($field)])
                    ->filter()
                    ->all(),
            ]);

        return response()->json([
            'channels' => $channels,
            'session' => [
                'timeout_minutes' => (int) config('bot.session.timeout_minutes'),
                'max_retries' => (int) config('bot.session.max_retries'),
            ],
        ]);
    }
}
