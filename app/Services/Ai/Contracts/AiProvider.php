<?php

namespace App\Services\Ai\Contracts;

interface AiProvider
{
    public function configured(): bool;

    /** What an administrator must supply, for the status screen. */
    public function requirement(): string;

    /**
     * One completion. Returns null on any fault — never throws, because every
     * caller has a working answer without the model and must be free to use it.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function complete(array $messages, float $temperature = 0.2, int $maxTokens = 400): ?string;
}
