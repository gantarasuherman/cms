<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\NodeResult;

/**
 * One node type's behaviour, in two halves.
 *
 * `enter()` runs when the conversation arrives at the node. `receive()` runs
 * when a reply comes back — only for nodes whose `enter()` said it waits.
 * Splitting them is what lets the engine walk several nodes in one turn and
 * stop at the first that actually asks something.
 */
interface NodeHandler
{
    public function enter(BotNode $node, BotConversation $conversation): NodeResult;

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult;
}
