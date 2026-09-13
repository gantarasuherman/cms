<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;

/** Used when assistance is switched off. Answers nothing, quickly. */
class NullProvider implements AiProvider
{
    public function configured(): bool
    {
        return false;
    }

    public function requirement(): string
    {
        return 'Bantuan AI sedang dimatikan.';
    }

    public function complete(array $messages, float $temperature = 0.2, int $maxTokens = 400): ?string
    {
        return null;
    }
}
