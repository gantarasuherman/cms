<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiSettings;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Any service that speaks OpenAI's `/chat/completions`.
 *
 * Groq, OpenAI, OpenRouter, Together and a local Ollama all do, so they differ
 * by base URL and model rather than by code. A class per vendor would be four
 * copies of the same twenty lines, each able to drift.
 */
class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(private readonly AiSettings $settings)
    {
    }

    public function configured(): bool
    {
        if (blank($this->settings->baseUrl()) || blank($this->settings->model())) {
            return false;
        }

        // A hosted provider without a key would fail on every request; a model
        // on this server has nobody to authenticate to.
        return ! $this->settings->needsKey() || filled($this->settings->apiKey());
    }

    public function requirement(): string
    {
        return $this->settings->needsKey()
            ? 'Kunci API dari penyedia yang dipilih.'
            : 'Layanan model lokal harus berjalan di alamat yang dikonfigurasi.';
    }

    public function complete(array $messages, float $temperature = 0.2, int $maxTokens = 400): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $request = Http::timeout((int) config('ai.timeout'))
                // One retry only. A person is waiting in a chat; a third
                // attempt costs more than the fallback answer is worth.
                ->retry(1, 200, throw: false)
                ->acceptJson();

            if (filled($key = $this->settings->apiKey())) {
                $request = $request->withToken($key);
            }

            $response = $request->post(rtrim($this->settings->baseUrl(), '/').'/chat/completions', [
                'model' => $this->settings->model(),
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);
        } catch (\Throwable $e) {
            // Never propagate: every caller has a working answer without this.
            Log::warning('AI tidak dapat dihubungi', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('AI menolak permintaan', [
                'status' => $response->status(),
                // The key travels in a header, never in the body, so the
                // message is safe to record as-is.
                'message' => mb_substr((string) $response->json('error.message'), 0, 200),
            ]);

            return null;
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        return $text !== '' ? $text : null;
    }
}
