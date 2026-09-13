<?php

namespace App\Services\Captcha;

interface CaptchaServiceInterface
{
    /** Whether a challenge should be rendered and verified at all. */
    public function enabled(): bool;

    /** Public key handed to the browser widget, or null when the driver has none. */
    public function siteKey(): ?string;

    /** Name of the request field carrying the challenge response. */
    public function responseField(): string;

    /**
     * Issues a fresh challenge posed server-side, or null when the provider
     * draws its own widget.
     *
     * Single-use: calling this replaces any challenge already outstanding.
     */
    public function challenge(): ?Challenge;

    /** Verifies a challenge response server-side. */
    public function verify(?string $token, ?string $ip = null): bool;
}

