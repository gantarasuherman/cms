<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileCaptchaService implements CaptchaServiceInterface
{
    public function __construct(
        private readonly ?string $siteKey,
        private readonly ?string $secretKey,
        private readonly string $verifyUrl,
        private readonly int $timeout = 5,
    ) {
    }

    public function enabled(): bool
    {
        return filled($this->siteKey) && filled($this->secretKey);
    }

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }

    public function responseField(): string
    {
        return 'cf-turnstile-response';
    }

    /** Turnstile draws its own widget, so there is no question to render. */
    public function challenge(): ?Challenge
    {
        return null;
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post($this->verifyUrl, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed to reach provider.', ['message' => $e->getMessage()]);

            // Fail closed: an unreachable provider must not become a bypass.
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}

