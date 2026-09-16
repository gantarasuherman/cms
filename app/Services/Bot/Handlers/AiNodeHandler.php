<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotNode;
use App\Services\Ai\AiAssistant;
use App\Services\Bot\ContentLister;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Bot\TemplateRenderer;

/**
 * A conversation rather than a lookup.
 *
 * The node holds its turn: after answering it waits for the next question, so
 * somebody can follow up — "berapa biayanya?" straight after asking about a
 * permit — instead of being returned to a menu and starting again. That
 * holding is the whole difference between this feeling like a conversation and
 * feeling like a search box with extra steps.
 *
 * Four ways out, all of them deliberate:
 *
 *  - the person replies 0                  → `back`, to the main menu
 *  - the person says they are done         → `valid`, on to the closing node
 *  - the model cannot answer from the site → `invalid`, so the flow can hand
 *                                            the question to a person
 *  - the turn limit is reached             → `exhausted`
 *
 * With assistance switched off or unreachable the node refuses to pretend: it
 * routes to `invalid` immediately, which on the seeded flow files the question
 * for a human. A bot that answers badly is worse than one that admits it needs
 * help.
 */
class AiNodeHandler implements NodeHandler
{
    private const DONE_WORDS = ['selesai', 'sudah', 'cukup', 'tidak', 'terima kasih', 'makasih', 'ok', 'oke', 'no'];

    public function __construct(
        private readonly AiAssistant $ai,
        private readonly ContentLister $lister,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        if (! $this->ai->available()) {
            // Straight past, with nothing said: the flow's own next step asks
            // the question, and the person never learns a feature was off.
            return NodeResult::next('unavailable');
        }

        $text = $this->renderer->render($node->setting('text'), $conversation->state['answers'] ?? [])
            ?: 'Silakan tulis pertanyaan Anda.';

        return NodeResult::ask(
            [OutgoingMessage::text($text, $node->key)],
            ['_ai.'.$node->key => ['turns' => 0, 'history' => []]],
        );
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        $question = $message->body();
        $state = (array) ($conversation->answer('_ai.'.$node->key) ?? ['turns' => 0, 'history' => []]);

        if ($question === '') {
            return NodeResult::invalid([OutgoingMessage::text(
                'Silakan tulis pertanyaannya dalam bentuk teks.', $node->key,
            )]);
        }

        // Nol mengembalikan ke menu utama, persis seperti yang dijanjikan
        // footer menu itu sendiri. Tanpa ini satu-satunya jalan keluar adalah
        // mengetik "menu", yang tidak pernah disebutkan di layar ini.
        if (trim($question) === '0') {
            return NodeResult::next('back');
        }

        // Said they are finished — but only when that is the whole message.
        // "tidak ada biaya?" is a question, not a goodbye.
        if ($this->saidDone($question)) {
            return NodeResult::next('valid', [OutgoingMessage::text(
                $this->renderer->render($node->setting('closing_message'))
                    ?: 'Baik, terima kasih. Balas *menu* kapan saja bila ada yang lain.',
                $node->key,
            )]);
        }

        $reply = $this->ai->converse(
            $question,
            $this->context($node),
            $state['history'] ?? [],
            $node->setting('persona'),
        );

        if ($reply === null) {
            // Remembered so whatever comes next can file the question without
            // asking the person to type it again.
            return NodeResult::next('invalid', [], ['description' => $question, 'question' => $question]);
        }

        $turns = (int) ($state['turns'] ?? 0) + 1;
        $limit = (int) $node->setting('max_turns', 8);

        $history = array_merge($state['history'] ?? [], [
            ['role' => 'user', 'content' => $question],
            ['role' => 'assistant', 'content' => $reply],
        ]);

        if ($turns >= $limit) {
            return NodeResult::next('exhausted',
                [OutgoingMessage::text($reply, $node->key)],
                ['_ai.'.$node->key => ['turns' => $turns, 'history' => $history]],
            );
        }

        // Holds its turn: the node stays where it is, ready for a follow-up.
        return NodeResult::ask(
            [OutgoingMessage::text($reply."\n\n_Ada pertanyaan lagi? Silakan tulis. Balas *0* untuk kembali ke menu utama._", $node->key)],
            ['_ai.'.$node->key => ['turns' => $turns, 'history' => $history]],
        );
    }

    /**
     * Everything the node is allowed to answer from.
     *
     * Read whole rather than keyword-filtered first: a question worded
     * differently from the FAQ entry that answers it would find nothing, and
     * be escalated while the answer sat one row away. Reading the model is
     * what the model is for.
     *
     * @return array<int, array<string, mixed>>
     */
    private function context(BotNode $node): array
    {
        $slugs = (array) $node->setting('sources', []);

        $sources = BotDataSource::active()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->get();

        // A fair share each, and each row carries the name of where it came
        // from. Taking the first forty across all sources let one long FAQ
        // crowd the news out of the prompt entirely.
        $perSource = $sources->isEmpty() ? 0 : (int) max(4, floor(40 / $sources->count()));

        return $sources
            ->flatMap(fn (BotDataSource $source) => collect($this->lister->items($source, $perSource))
                ->map(fn (array $item) => $item + ['_source' => $source->name]))
            ->values()
            ->all();
    }

    private function saidDone(string $message): bool
    {
        $normalised = mb_strtolower(trim($message, " \t\n\r\0\x0B.!?"));

        return in_array($normalised, self::DONE_WORDS, true);
    }
}
