<?php

namespace App\Services\Bot;

use App\Models\Bot\BotNode;
use App\Models\BotQuestionTopic;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What people keep asking the bot.
 *
 * A question asked once is a conversation; the same question asked eleven
 * times is a gap in the FAQ. This counts the second kind so an editor can
 * answer it once, publicly, instead of the bot improvising an answer every
 * time.
 *
 * Counted from `bot_messages` on every request rather than kept in a table:
 * a stored count is wrong the moment the next message arrives, and the volume
 * here is a few thousand rows at most. Only the editor's *decision* is stored.
 */
class QuestionDigest
{
    /** Below this, two wordings are different questions. */
    private const SIMILARITY = 82.0;

    /**
     * Words that carry no topic. Stripped before comparing, so "berapa lama
     * izin IMB" and "izin IMB berapa lama ya kak" land on one topic.
     *
     * @var array<int, string>
     */
    private const FILLER = [
        'yang', 'yg', 'untuk', 'utk', 'dengan', 'dgn', 'dari', 'pada', 'dan', 'atau',
        'itu', 'ini', 'ada', 'apa', 'apakah', 'adakah', 'bagaimana', 'gimana', 'gmn',
        'kah', 'kak', 'pak', 'bu', 'min', 'admin', 'mohon', 'tolong', 'permisi',
        'saya', 'aku', 'kami', 'anda', 'nya', 'ya', 'yaa', 'dong', 'sih', 'kok',
        'mau', 'ingin', 'bisa', 'boleh', 'kalau', 'kalo', 'klo', 'jika', 'bila',
        'di', 'ke', 'se', 'nih', 'deh', 'aja', 'saja', 'juga', 'gak', 'tidak', 'ga',
    ];

    /**
     * The question topics, most asked first.
     *
     * @return Collection<int, array{
     *     fingerprint: string, question: string, asks: int, askers: int,
     *     first_asked_at: \Illuminate\Support\Carbon, last_asked_at: \Illuminate\Support\Carbon,
     *     variants: array<int, string>, topic: BotQuestionTopic|null
     * }>
     */
    /**
     * @param  CarbonInterface|null  $from  Batas periode; null berarti sejak awal.
     */
    public function topics(
        string $status = BotQuestionTopic::NEW,
        int $minimumAsks = 1,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): Collection {
        $decisions = BotQuestionTopic::with('faq')->get()->keyBy('fingerprint');

        $grouped = $this->group($this->questions($from, $to));

        return $grouped
            ->map(function (array $group) use ($decisions) {
                $group['topic'] = $decisions->get($group['fingerprint']);

                return $group;
            })
            ->filter(function (array $group) use ($status, $minimumAsks) {
                $current = $group['topic']?->status ?? BotQuestionTopic::NEW;

                // A promoted or dismissed topic stays out of the "new" list
                // however often it is asked again; the count still climbs, so
                // reopening it shows the real total.
                return $current === $status && $group['asks'] >= $minimumAsks;
            })
            ->sortByDesc('asks')
            ->values();
    }

    /**
     * The node keys whose answers are questions.
     *
     * Read from the flow rather than hard-coded: an `ai` node exists to be
     * asked things, and an `input` node that stores its answer as `question`
     * has said so in its own configuration. A free-text node that stores a
     * `description` is a complaint, and belongs nowhere near the FAQ.
     *
     * @return array<int, string>
     */
    public function questionNodes(): array
    {
        return BotNode::query()
            ->get(['key', 'type', 'config'])
            ->filter(fn (BotNode $node) => $node->type === 'ai'
                || ($node->type === 'input' && data_get($node->config, 'store_as') === 'question'))
            ->pluck('key')
            ->all();
    }

    /**
     * Every inbound message that was somebody asking something.
     *
     * @return Collection<int, object>
     */
    private function questions(?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $nodes = $this->questionNodes();

        if ($nodes === []) {
            return collect();
        }

        return DB::table('bot_messages')
            ->where('direction', 'in')
            ->whereIn('node_key', $nodes)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->when($from, fn ($query) => $query->where('bot_messages.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('bot_messages.created_at', '<=', $to))
            ->join('bot_conversations', 'bot_conversations.id', '=', 'bot_messages.bot_conversation_id')
            ->select([
                'bot_messages.body',
                'bot_messages.created_at',
                'bot_conversations.bot_contact_id',
            ])
            ->orderBy('bot_messages.id')
            ->get();
    }

    /**
     * Folds the messages into topics.
     *
     * Two passes, because neither alone is enough: an exact fingerprint
     * catches the same sentence typed twice, and a similarity pass then folds
     * the near-misses — a typo, an extra word — into the topic they belong to.
     *
     * @param  Collection<int, object>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    private function group(Collection $messages): Collection
    {
        /** @var array<string, array<string, mixed>> $topics */
        $topics = [];

        foreach ($messages as $message) {
            $body = trim((string) $message->body);
            $fingerprint = $this->fingerprint($body);

            if ($fingerprint === '') {
                continue;
            }

            $key = $this->nearest($topics, $fingerprint) ?? $fingerprint;

            if (! isset($topics[$key])) {
                $topics[$key] = [
                    'fingerprint' => $key,
                    'question' => $body,
                    'asks' => 0,
                    'contacts' => [],
                    'variants' => [],
                    'first_asked_at' => $message->created_at,
                    'last_asked_at' => $message->created_at,
                ];
            }

            $topics[$key]['asks']++;
            $topics[$key]['contacts'][$message->bot_contact_id] = true;
            $topics[$key]['variants'][$body] = true;
            $topics[$key]['last_asked_at'] = $message->created_at;
        }

        return collect($topics)
            ->map(function (array $topic) {
                $variants = array_keys($topic['variants']);

                return [
                    'fingerprint' => $topic['fingerprint'],
                    // The longest wording, which is usually the clearest and
                    // the best starting point for an FAQ entry.
                    'question' => collect($variants)->sortByDesc(fn (string $v) => mb_strlen($v))->first(),
                    'asks' => $topic['asks'],
                    'askers' => count($topic['contacts']),
                    'variants' => $variants,
                    'first_asked_at' => \Illuminate\Support\Carbon::parse($topic['first_asked_at']),
                    'last_asked_at' => \Illuminate\Support\Carbon::parse($topic['last_asked_at']),
                ];
            })
            ->values();
    }

    /**
     * An existing topic close enough to be the same question, or null.
     *
     * @param  array<string, array<string, mixed>>  $topics
     */
    private function nearest(array $topics, string $fingerprint): ?string
    {
        if (isset($topics[$fingerprint])) {
            return $fingerprint;
        }

        foreach (array_keys($topics) as $existing) {
            similar_text($existing, $fingerprint, $percent);

            if ($percent >= self::SIMILARITY) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * The comparable form of a question: lowercase, letters and digits only,
     * filler words removed, remaining words sorted.
     *
     * Sorted on purpose — Indonesian puts the question word at either end
     * freely, and "berapa lama izin" and "izin berapa lama" are one question.
     */
    public function fingerprint(string $question): string
    {
        $words = preg_split('/\s+/', Str::lower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $question)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) > 1 && ! in_array($word, self::FILLER, true),
        ));

        sort($words);

        return mb_substr(implode(' ', array_unique($words)), 0, 191);
    }
}
