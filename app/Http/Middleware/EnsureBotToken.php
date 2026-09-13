<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the internal bot API.
 *
 * This surface runs the conversation engine and reads complaints, so it is not
 * public in any sense — only the bot service may reach it. The shared secret
 * is compared with `hash_equals`, because a plain `===` on a secret leaks its
 * length and prefix through timing.
 *
 * With no secret configured the endpoint is closed rather than open: an
 * unconfigured deployment must fail shut.
 */
class EnsureBotToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('bot.internal_token');
        $given = (string) ($request->header('X-Bot-Token') ?? '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            abort(401, 'Token internal tidak valid.');
        }

        if (! config('bot.enabled')) {
            abort(503, 'Layanan chatbot sedang dimatikan.');
        }

        return $next($request);
    }
}
