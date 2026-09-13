<?php

namespace App\Services\Captcha;

/**
 * One issued challenge.
 *
 * The question and its answer options travel together because they are
 * produced together: a driver that handed them back through two calls would
 * let a caller render options belonging to a different question.
 */
final readonly class Challenge
{
    /** @param array<int, string> $options Answers to offer, already shuffled. */
    public function __construct(
        public string $question,
        public array $options = [],
    ) {
    }

    public function isMultipleChoice(): bool
    {
        return $this->options !== [];
    }
}

