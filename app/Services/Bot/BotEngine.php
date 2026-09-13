<?php

namespace App\Services\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotFlow;
use App\Models\Bot\BotMessage;
use App\Models\Bot\BotNode;
use App\Services\Bot\Handlers\ActionNodeHandler;
use App\Services\Bot\Handlers\AiNodeHandler;
use App\Services\Bot\Handlers\DataSourceNodeHandler;
use App\Services\Bot\Handlers\EndNodeHandler;
use App\Services\Bot\Handlers\InputNodeHandler;
use App\Services\Bot\Handlers\MenuNodeHandler;
use App\Services\Bot\Handlers\MessageNodeHandler;
use App\Services\Bot\Handlers\NodeHandler;
use App\Services\Bot\Handlers\StartNodeHandler;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Support\BotNodes;
use Illuminate\Support\Facades\DB;

/**
 * Runs a conversation.
 *
 * One turn is: find where this person was, let that node read their reply, then
 * walk edges — executing every node that does not need an answer — until one
 * asks something or the flow ends. Everything said in either direction is
 * written to the transcript as it happens.
 *
 * The engine sends nothing itself. It returns messages, and a transport posts
 * them. That is what makes a whole conversation testable without a network,
 * and what lets a failed send be retried without re-running the flow.
 */
class BotEngine
{
    /** Words that take someone back to the top from wherever they are. */
    private const RESTART_WORDS = ['menu', 'mulai', 'start', '/start', '/menu', 'batal', '/batal'];

    public function __construct(
        private readonly StartNodeHandler $start,
        private readonly MessageNodeHandler $message,
        private readonly MenuNodeHandler $menu,
        private readonly InputNodeHandler $input,
        private readonly DataSourceNodeHandler $dataSource,
        private readonly ActionNodeHandler $action,
        private readonly AiNodeHandler $ai,
        private readonly EndNodeHandler $end,
    ) {
    }

    /**
     * Handles one incoming message.
     *
     * @return array<int, OutgoingMessage>
     */
    public function handle(BotChannel $channel, IncomingMessage $incoming): array
    {
        $contact = $this->contact($channel, $incoming);

        if ($contact->is_blocked) {
            return [];
        }

        $conversation = $this->conversation($channel, $contact, $incoming);
        $this->record($conversation, $incoming);

        $replies = $this->advance($conversation, $incoming);

        foreach ($replies as $reply) {
            $this->recordOutgoing($conversation, $reply);
        }

        $conversation->last_message_at = now();
        $conversation->expires_at = now()->addMinutes((int) config('bot.session.timeout_minutes'));
        $conversation->save();

        return $replies;
    }

    /* ---------------------------------------------------------- the walk */

    /** @return array<int, OutgoingMessage> */
    private function advance(BotConversation $conversation, IncomingMessage $incoming): array
    {
        $flow = $conversation->flow;

        if (! $flow) {
            return [OutgoingMessage::text('Layanan percakapan belum disiapkan.')];
        }

        $nodes = $flow->nodes->keyBy('key');
        $messages = [];

        // Where the reply is addressed. A conversation that has just been
        // created has not asked anything yet, so it starts at the beginning.
        $current = $conversation->current_node ? $nodes->get($conversation->current_node) : null;

        if ($current === null) {
            return $this->walkFrom($conversation, $flow->startNode(), $nodes, $messages);
        }

        $result = $this->handlerFor($current->type)->receive($current, $conversation, $incoming);
        $this->apply($conversation, $result);
        $messages = array_merge($messages, $result->messages);

        if ($result->waits) {
            // Only a *failed* answer counts against the retry limit. A node
            // may legitimately hold its turn after succeeding — the AI node
            // waits for a follow-up — and counting those as failures ended a
            // working conversation after three good answers.
            if ($result->outcome !== 'invalid') {
                $conversation->retry_count = 0;

                return $messages;
            }

            $conversation->retry_count++;

            if ($conversation->retry_count >= $this->maxRetries($current)) {
                $conversation->retry_count = 0;

                return array_merge($messages, $this->followRetry($conversation, $current, $nodes));
            }

            // From the second failure on, ask the question again. On a phone
            // the original has usually scrolled away behind the attempts, and
            // telling someone they are wrong without showing them the question
            // is how a conversation stalls.
            if ($conversation->retry_count >= 2 && $result->outcome === 'invalid') {
                $repeat = $this->handlerFor($current->type)->enter($current, $conversation);
                $this->apply($conversation, $repeat);
                $messages = array_merge($messages, $repeat->messages);
            }

            return $messages;
        }

        $conversation->retry_count = 0;

        return $this->walkFrom($conversation, $this->nextNode($flow, $current->key, $result->outcome, $nodes), $nodes, $messages);
    }

    /**
     * Runs nodes until one waits for an answer or the flow ends.
     *
     * @return array<int, OutgoingMessage>
     */
    private function walkFrom(BotConversation $conversation, ?BotNode $node, $nodes, array $messages): array
    {
        $flow = $conversation->flow;
        // A cheap cycle guard: a flow that loops without ever asking anything
        // would otherwise spin here forever.
        $steps = 0;

        while ($node !== null && $steps++ < 50) {
            $result = $this->handlerFor($node->type)->enter($node, $conversation);
            $this->apply($conversation, $result);
            $messages = array_merge($messages, $result->messages);

            if ($result->waits) {
                $conversation->current_node = $node->key;

                return $messages;
            }

            if ($node->type === BotNodes::END || $result->outcome === null) {
                $conversation->current_node = null;
                $conversation->status = BotConversation::COMPLETED;

                return $messages;
            }

            $node = $this->nextNode($flow, $node->key, $result->outcome, $nodes);
        }

        if ($steps >= 50) {
            // Reaching here means the flow itself is wrong; say so plainly
            // rather than leaving the person waiting on silence.
            $conversation->current_node = null;

            return array_merge($messages, [OutgoingMessage::text(
                'Terjadi kesalahan pada alur percakapan. Silakan hubungi petugas.',
            )]);
        }

        $conversation->current_node = null;
        $conversation->status = BotConversation::COMPLETED;

        return $messages;
    }

    /**
     * Where somebody goes after failing the same question too many times.
     *
     * An `exhausted` edge drawn on the graph wins over the node's `on_invalid`
     * setting. The editor is the place an administrator says what should
     * happen, so a line they drew must not be overruled by a default they
     * never looked at.
     *
     * @return array<int, OutgoingMessage>
     */
    private function followRetry(BotConversation $conversation, BotNode $node, $nodes): array
    {
        // Looked up directly, not through nextNode(): that falls back to the
        // unconditional edge when nothing matches, which would send somebody
        // who gave up forward as though they had answered correctly.
        $edge = $conversation->flow->edges
            ->where('from_node', $node->key)
            ->firstWhere('condition', 'exhausted');

        $drawn = $edge ? $nodes->get($edge->to_node) : null;

        $target = $drawn ?? match ($node->setting('on_invalid', 'repeat')) {
            'main_menu' => $nodes->get('menu_utama') ?? $conversation->flow->startNode(),
            'end' => $nodes->first(fn (BotNode $n) => $n->type === BotNodes::END),
            'previous' => $conversation->flow->startNode(),
            // `repeat` means stay put: the question has just been asked again
            // by the retry logic above, so there is nothing more to do.
            default => null,
        };

        return $target ? $this->walkFrom($conversation, $target, $nodes, []) : [];
    }

    private function nextNode(BotFlow $flow, string $from, ?string $outcome, $nodes): ?BotNode
    {
        $edges = $flow->edges->where('from_node', $from);

        $edge = $edges->firstWhere('condition', $outcome)
            // An edge with no condition is the unconditional next step, and is
            // what an outcome nothing matched falls through to.
            ?? $edges->firstWhere('condition', null);

        return $edge ? $nodes->get($edge->to_node) : null;
    }

    private function maxRetries(BotNode $node): int
    {
        return (int) $node->setting('max_retries', config('bot.session.max_retries'));
    }

    private function apply(BotConversation $conversation, NodeResult $result): void
    {
        foreach ($result->remember as $key => $value) {
            $conversation->remember($key, $value);
        }
    }

    private function handlerFor(string $type): NodeHandler
    {
        return match ($type) {
            BotNodes::START => $this->start,
            BotNodes::MENU => $this->menu,
            BotNodes::INPUT => $this->input,
            BotNodes::DATA_SOURCE => $this->dataSource,
            BotNodes::ACTION => $this->action,
            BotNodes::AI => $this->ai,
            BotNodes::END => $this->end,
            default => $this->message,
        };
    }

    /* ------------------------------------------------------ bookkeeping */

    private function contact(BotChannel $channel, IncomingMessage $incoming): BotContact
    {
        $contact = BotContact::firstOrNew([
            'bot_channel_id' => $channel->getKey(),
            'external_id' => $incoming->from,
        ]);

        // Kept current, because people change their display name and the
        // operator list should show what they are called now.
        $contact->name = $incoming->senderName ?: $contact->name;
        $contact->username = $incoming->senderUsername ?: $contact->username;
        $contact->phone = $contact->phone ?: ($channel->key === BotChannel::WHATSAPP ? $incoming->from : null);

        // Only when it actually changed: the bot service already skips the
        // download when the reference matches, and rewriting the row on every
        // message would be a write per turn for nothing.
        if (filled($incoming->senderAvatarPath) && $incoming->senderAvatarRef !== $contact->avatar_ref) {
            $contact->avatar_path = $incoming->senderAvatarPath;
            $contact->avatar_ref = $incoming->senderAvatarRef;
        }
        $contact->last_seen_at = now();
        $contact->save();

        return $contact;
    }

    private function conversation(BotChannel $channel, BotContact $contact, IncomingMessage $incoming): BotConversation
    {
        $open = BotConversation::open()
            ->where('bot_contact_id', $contact->getKey())
            ->latest('id')
            ->first();

        // "menu" from anywhere starts over. Without this, somebody stuck
        // halfway through a form has no way out but to wait for the timeout.
        if ($open && in_array(mb_strtolower($incoming->body()), self::RESTART_WORDS, true)) {
            $open->update(['status' => BotConversation::COMPLETED]);
            $open = null;
        }

        if ($open) {
            return $open;
        }

        $flow = $channel->flow ?: BotFlow::active()->where('is_default', true)->first();

        return BotConversation::create([
            'bot_channel_id' => $channel->getKey(),
            'bot_contact_id' => $contact->getKey(),
            'bot_flow_id' => $flow?->getKey(),
            // Pinned, so republishing a flow cannot move somebody to a
            // different question in the middle of answering this one.
            'flow_version' => $flow?->version,
            'current_node' => null,
            'state' => ['answers' => []],
            'status' => BotConversation::ACTIVE,
            'last_message_at' => now(),
            'expires_at' => now()->addMinutes((int) config('bot.session.timeout_minutes')),
        ]);
    }

    private function record(BotConversation $conversation, IncomingMessage $incoming): void
    {
        BotMessage::create([
            'bot_conversation_id' => $conversation->getKey(),
            'direction' => BotMessage::IN,
            'type' => $incoming->type,
            'body' => $incoming->text,
            'media_path' => $incoming->mediaPath,
            'media_mime' => $incoming->mediaMime,
            'latitude' => $incoming->latitude,
            'longitude' => $incoming->longitude,
            'node_key' => $conversation->current_node,
            'external_id' => $incoming->externalId,
            'payload' => $incoming->raw ?: null,
        ]);
    }

    private function recordOutgoing(BotConversation $conversation, OutgoingMessage $message): void
    {
        BotMessage::create([
            'bot_conversation_id' => $conversation->getKey(),
            'direction' => BotMessage::OUT,
            'type' => $message->type,
            'body' => $message->body,
            'media_path' => $message->mediaPath,
            'node_key' => $message->nodeKey,
        ]);
    }
}
