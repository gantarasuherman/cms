<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Bot\TemplateRenderer;

/** Says something and moves straight on. */
class MessageNodeHandler implements NodeHandler
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        $text = $this->renderer->render($node->setting('text'), $conversation->state['answers'] ?? []);

        return NodeResult::next('valid', $text === '' ? [] : [OutgoingMessage::text($text, $node->key)]);
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        // Never waits, so nothing can be addressed to it.
        return $this->enter($node, $conversation);
    }
}
