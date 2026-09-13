<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\NodeResult;

/** The entry point: says nothing of its own, just hands over. */
class StartNodeHandler implements NodeHandler
{
    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        return NodeResult::next();
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        return $this->enter($node, $conversation);
    }
}
