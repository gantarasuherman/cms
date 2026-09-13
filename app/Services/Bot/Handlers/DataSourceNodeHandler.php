<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotNode;
use App\Services\Bot\ContentLister;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Bot\ExpectationDescriber;
use App\Services\Bot\TemplateRenderer;

/**
 * Reads site content out: a numbered list, then the detail of whatever number
 * comes back.
 *
 * The list as sent is remembered on the conversation, so answering "2" opens
 * the second item that was actually offered — not the second item as the site
 * stands a minute later, which may be a different story entirely.
 */
class DataSourceNodeHandler implements NodeHandler
{
    public function __construct(
        private readonly ContentLister $lister,
        private readonly TemplateRenderer $renderer,
        private readonly ExpectationDescriber $describer,
    ) {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        $source = $this->source($node);

        if (! $source) {
            return NodeResult::next('invalid', [OutgoingMessage::text(
                'Sumber informasi belum disiapkan.', $node->key,
            )]);
        }

        $items = $this->lister->items($source);

        if ($items === []) {
            return NodeResult::next('valid', [OutgoingMessage::text(
                'Belum ada '.mb_strtolower($source->name).' yang dapat ditampilkan.', $node->key,
            )]);
        }

        $body = $source->name."\n\n".$this->lister->list($source, $items)
            ."\n\nBalas nomornya untuk melihat rincian, atau 0 untuk kembali.";

        return NodeResult::ask(
            [OutgoingMessage::text($body, $node->key)],
            ['_list.'.$node->key => $items],
        );
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        $answer = $message->body();

        if ($answer === '0') {
            return NodeResult::next('valid');
        }

        $items = (array) ($conversation->answer('_list.'.$node->key) ?? []);
        $index = ctype_digit($answer) ? (int) $answer : 0;

        if ($index < 1 || ! isset($items[$index - 1])) {
            return NodeResult::invalid([OutgoingMessage::text(
                $this->describer->explain($node, $conversation, $message, $this->renderer->render($node->setting('invalid_message'))),
                $node->key,
            )]);
        }

        $source = $this->source($node);

        return NodeResult::next('valid', [OutgoingMessage::text(
            $this->lister->detail($source, $items[$index - 1], $index),
            $node->key,
        )]);
    }

    private function source(BotNode $node): ?BotDataSource
    {
        return BotDataSource::active()->where('slug', $node->setting('data_source'))->first();
    }
}
