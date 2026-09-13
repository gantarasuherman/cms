<?php

namespace App\Services\Captcha;

/**
 * No-op driver: the login form renders no challenge and verification always
 * passes. Used when no provider is configured (tests, first boot).
 */
class NullCaptchaService implements CaptchaServiceInterface
{
    public function enabled(): bool
    {
        return false;
    }

    public function siteKey(): ?string
    {
        return null;
    }

    public function responseField(): string
    {
        return 'captcha';
    }

    public function challenge(): ?Challenge
    {
        return null;
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        return true;
    }
}

