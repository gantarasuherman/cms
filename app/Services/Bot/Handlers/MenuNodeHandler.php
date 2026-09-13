<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Models\ComplaintCategory;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Ai\AiAssistant;
use App\Services\Bot\ExpectationDescriber;
use App\Services\Bot\TemplateRenderer;

/**
 * A numbered menu.
 *
 * Options come either from the node's own list or from a table — the category
 * menu is filled from `complaint_categories`, so adding a category adds a menu
 * entry without anybody editing the flow. Both kinds are matched the same way
 * on the way back in.
 */
class MenuNodeHandler implements NodeHandler
{
    private const BACK = '0';

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly ExpectationDescriber $describer,
        private readonly AiAssistant $ai,
    ) {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        $options = $this->options($node);
        $lines = [];

        $text = $this->renderer->render($node->setting('text'), $conversation->state['answers'] ?? []);

        if ($text !== '') {
            $lines[] = $text;
            $lines[] = '';
        }

        foreach ($options as $option) {
            $lines[] = $option['value'].'. '.$option['label'];
        }

        if ($footer = $this->renderer->render($node->setting('footer'))) {
            $lines[] = '';
            $lines[] = $footer;
        }

        // The options as offered are remembered, because a menu filled from a
        // table must resolve "2" to the same category it printed — even if a
        // category is added between the question and the answer.
        return NodeResult::ask(
            [OutgoingMessage::text(implode("\n", $lines), $node->key)],
            ['_offered.'.$node->key => $options],
        );
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        $answer = $message->body();
        $offered = $conversation->answer('_offered.'.$node->key) ?? $this->options($node);

        if ($node->setting('include_back') && $answer === self::BACK) {
            return NodeResult::next('back');
        }

        foreach ($offered as $option) {
            if ($this->matches($answer, $option)) {
                // A table-backed menu routes through one `category` edge and
                // carries which row was chosen in the conversation state.
                return $node->setting('options_from')
                    ? NodeResult::next('category', [], [
                        'category_id' => $option['id'] ?? null,
                        'category' => $option['label'],
                    ])
                    : NodeResult::next((string) $option['value']);
            }
        }

        // Nothing matched the way it was written. Before refusing, let the
        // assistant read it as a sentence — somebody typing "jalan depan rumah
        // saya rusak" means option 1, and being told "pilihan tidak dikenali"
        // for saying so plainly is what makes a menu feel like a form.
        if ($guess = $this->ai->intent($answer, $offered)) {
            return $node->setting('options_from')
                ? NodeResult::next('category', [], $this->categoryFrom($offered, $guess))
                : NodeResult::next($guess);
        }

        return NodeResult::invalid([OutgoingMessage::text(
            $this->describer->explain($node, $conversation, $message, $this->renderer->render($node->setting('invalid_message'))),
            $node->key,
        )]);
    }

    /** @return array<string, mixed> */
    private function categoryFrom(array $offered, string $value): array
    {
        $option = collect($offered)->firstWhere('value', $value);

        return ['category_id' => $option['id'] ?? null, 'category' => $option['label'] ?? null];
    }

    /**
     * A reply matches by its number, or by the option's own wording — people
     * type "pengaduan" as readily as they type "1".
     */
    private function matches(string $answer, array $option): bool
    {
        return $answer === (string) $option['value']
            || mb_strtolower($answer) === mb_strtolower((string) $option['label']);
    }

    /** @return array<int, array{value: string, label: string, id?: int}> */
    private function options(BotNode $node): array
    {
        $options = $node->setting('options_from') === 'complaint_categories'
            ? ComplaintCategory::active()->get()
                ->values()
                ->map(fn (ComplaintCategory $category, int $index) => [
                    'value' => (string) ($index + 1),
                    'label' => $category->name,
                    'id' => $category->getKey(),
                ])->all()
            : $node->options();

        if ($node->setting('include_back')) {
            $options[] = ['value' => self::BACK, 'label' => $node->setting('back_label', 'Kembali ke Menu')];
        }

        return $options;
    }
}
