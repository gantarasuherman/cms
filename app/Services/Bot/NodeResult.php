<?php

namespace App\Services\Bot;

use App\Services\Bot\Messages\OutgoingMessage;

/**
 * What a node did.
 *
 * `outcome` is the name of the edge to follow next — a menu option's value,
 * or `valid` / `invalid` / `back` / `category`. `waits` says whether the node
 * has now asked something and the engine should stop and let the person answer.
 *
 * Separating "what to say" from "where to go" is what keeps the graph walking
 * in one place instead of inside every handler.
 */
final readonly class NodeResult
{
    /** @param array<int, OutgoingMessage> $messages */
    public function __construct(
        public array $messages = [],
        public ?string $outcome = null,
        public bool $waits = false,
        /** Answers to fold into the conversation state. */
        public array $remember = [],
    ) {
    }

    /** The node asked something and is now waiting for a reply. */
    public static function ask(array $messages, array $remember = []): self
    {
        return new self($messages, null, true, $remember);
    }

    /** The node is done; follow the edge named by `$outcome`. */
    public static function next(string $outcome = 'valid', array $messages = [], array $remember = []): self
    {
        return new self($messages, $outcome, false, $remember);
    }

    /** The answer did not validate; the node stays where it is. */
    public static function invalid(array $messages): self
    {
        return new self($messages, 'invalid', true);
    }

    /** Nothing more to do and nowhere to go: the conversation is over. */
    public static function stop(array $messages = []): self
    {
        return new self($messages, null, false);
    }
}
