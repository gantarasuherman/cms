<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\NullProvider;
use App\Services\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Support\Str;

/**
 * The two things a language model is actually allowed to do here.
 *
 * Both are advisory. `intent()` may return null and the menu then behaves
 * exactly as it did before; `answer()` may return null and the keyword search
 * answers instead. Nothing downstream is written on a model's say-so alone —
 * a complaint is still filed by the same code whether or not a model helped
 * read the sentence.
 *
 * What the model is never allowed to do: invent an answer. `answer()` is given
 * the retrieved text and told to reply only from it, and to say so plainly when
 * it cannot. "I don't know" routes the question to a person, which is a far
 * better outcome than a confident wrong answer from a government service.
 */
class AiAssistant
{
    public function __construct(private readonly AiSettings $settings)
    {
    }

    public function provider(): AiProvider
    {
        return $this->settings->enabled()
            ? new OpenAiCompatibleProvider($this->settings)
            : new NullProvider();
    }

    public function available(): bool
    {
        return $this->settings->enabled() && $this->provider()->configured();
    }

    /**
     * Which menu option a free-text message was reaching for.
     *
     * Returns the option's value, or null when the model is unavailable or
     * unsure. Unsure must mean null: routing somebody to the wrong branch on a
     * guess is worse than showing them the menu again.
     *
     * @param  array<int, array{value: string, label: string}>  $options
     */
    public function intent(string $message, array $options): ?string
    {
        if (! $this->settings->feature('intent') || $options === []) {
            return null;
        }

        $message = Str::limit(trim($message), (int) config('ai.max_input_chars'), '');

        if (mb_strlen($message) < 4) {
            return null;
        }

        $menu = collect($options)
            ->map(fn (array $option) => "{$option['value']} = {$option['label']}")
            ->implode("\n");

        $reply = $this->provider()->complete([
            ['role' => 'system', 'content' =>
                "Anda memetakan pesan warga ke satu pilihan menu layanan publik.\n".
                "Jawab HANYA dengan angka pilihan, atau kata TIDAK bila tidak ada yang cocok.\n".
                "Jangan menjelaskan apa pun.\n\nPilihan:\n".$menu,
            ],
            ['role' => 'user', 'content' => $message],
        ], 0.0, 8);

        if ($reply === null) {
            return null;
        }

        $answer = trim(preg_replace('/[^0-9A-Za-z]/', '', $reply) ?? '');

        // Only a value the menu actually offered. A model returning "4" for a
        // three-item menu must change nothing.
        return collect($options)->contains('value', $answer) ? $answer : null;
    }

    /**
     * A reply in a running conversation, grounded in the site's own content.
     *
     * The difference from `answer()` is memory: the last few turns are sent
     * along, so "berapa biayanya?" after a question about permits is understood
     * as being about permits. Without that every message is a stranger and the
     * exchange feels like a search box with extra steps.
     *
     * Returns null when the model is unavailable or says it cannot help, which
     * is what routes the person to somebody who can.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, array<string, mixed>>  $context
     */
    public function converse(string $question, array $context, array $history = [], ?string $persona = null): ?string
    {
        if (! $this->settings->feature('answers')) {
            return null;
        }

        $system = trim(($persona ?: 'Anda petugas layanan informasi sebuah instansi pemerintah daerah.')."\n\n".
            "Hari ini ".now()->translatedFormat('l, d F Y').".\n\n".
            "Jawab HANYA berdasarkan sumber di bawah. Jangan menambahkan apa pun yang tidak tertulis di sana,\n".
            "dan jangan menebak angka, biaya, tenggat, atau syarat yang tidak disebutkan.\n".
            "Setiap entri diawali jenisnya dan tanggalnya. Bila ditanya tentang satu jenis tertentu —\n".
            "misalnya berita — pakai HANYA entri berjenis itu, dan sebutkan tanggalnya bila relevan.\n".
            "Bila diminta daftar, tuliskan sebagai daftar bernomor singkat, satu baris per entri.\n".
            "Bila tidak ada entri yang cocok dengan yang ditanyakan, balas persis: TIDAK TAHU\n".
            "Bahasa Indonesia yang hangat dan ringkas. Untuk pertanyaan biasa maksimal empat kalimat.\n\n".
            "Sumber:\n".$this->renderContext($context));

        $messages = [['role' => 'system', 'content' => $system]];

        // The last few turns only: enough for a follow-up to make sense,
        // little enough that an old topic stops steering a new one.
        foreach (array_slice($history, -6) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = ['role' => 'user', 'content' => Str::limit(trim($question), (int) config('ai.max_input_chars'), '')];

        $reply = $this->provider()->complete($messages, 0.3, 400);

        if ($reply === null || Str::contains(Str::upper($reply), 'TIDAK TAHU')) {
            return null;
        }

        return $reply;
    }

    /**
     * The retrieved rows, written so the model can tell them apart.
     *
     * Grouped by kind and stamped with a date, because without either the
     * model sees one undifferentiated list: asked "ada berita apa saja hari
     * ini" it has no way to know which entries are news, and no date to judge
     * "today" against. Both were missing, and the answers wandered.
     *
     * @param  array<int, array<string, mixed>>  $context
     */
    private function renderContext(array $context): string
    {
        $grouped = collect($context)->groupBy(fn (array $item) => $item['_source'] ?? 'Informasi');
        $lines = [];
        $n = 0;

        foreach ($grouped as $kind => $items) {
            $lines[] = '## '.$kind;

            foreach ($items as $item) {
                $date = trim((string) ($item['date'] ?? ''));

                $lines[] = sprintf(
                    "[%d] (%s%s) %s\n%s",
                    ++$n,
                    $kind,
                    $date !== '' ? ' · '.$date : '',
                    $item['title'] ?? '',
                    Str::limit((string) ($item['excerpt'] ?? ''), 400),
                );
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * An answer written from the retrieved content, or null.
     *
     * @param  array<int, array<string, mixed>>  $context  rows from ContentLister
     */
    public function answer(string $question, array $context): ?string
    {
        if (! $this->settings->feature('answers') || $context === []) {
            return null;
        }

        $sources = collect($context)
            ->take(5)
            ->map(fn (array $item, int $i) => sprintf(
                "[%d] %s\n%s", $i + 1, $item['title'] ?? '', Str::limit((string) ($item['excerpt'] ?? ''), 600),
            ))
            ->implode("\n\n");

        $reply = $this->provider()->complete([
            ['role' => 'system', 'content' =>
                "Anda menjawab pertanyaan warga untuk sebuah layanan publik.\n".
                "Jawab HANYA berdasarkan sumber di bawah. Jangan menambahkan apa pun yang tidak tertulis di sana.\n".
                "Bila sumbernya tidak menjawab pertanyaan itu, balas persis: TIDAK TAHU\n".
                "Gunakan bahasa Indonesia yang sopan dan ringkas, maksimal empat kalimat.\n\n".
                "Sumber:\n".$sources,
            ],
            ['role' => 'user', 'content' => Str::limit(trim($question), (int) config('ai.max_input_chars'), '')],
        ], 0.2, 350);

        if ($reply === null) {
            return null;
        }

        // Saying it does not know is a useful answer here: it is what sends the
        // question to a person instead of guessing at one.
        if (Str::contains(Str::upper($reply), 'TIDAK TAHU')) {
            return null;
        }

        return $reply;
    }
}
