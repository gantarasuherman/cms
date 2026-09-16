<?php

namespace App\Services\Bot;

use App\Models\Bot\BotDataSource;
use App\Models\ComplaintCategory;
use App\Models\Page;
use App\Services\Public\DocumentReader;
use App\Services\Public\FaqReader;
use App\Services\Public\NewsReader;
use App\Services\Public\ServiceReader;
use Illuminate\Support\Str;

/**
 * Turns published site content into numbered chat lines.
 *
 * Reads through the same public readers the website uses, so the bot can never
 * answer with a draft or an archived item — the visibility rules live in one
 * place and this borrows them rather than re-implementing them.
 *
 * Which content is reachable is a fixed vocabulary in `BotNodes::dataSources()`.
 * A free-text model name on the data-source row would be a ready-made way to
 * read the users table over WhatsApp.
 */
class ContentLister
{
    /**
     * Bagian terkecil dari kata yang ditanyakan yang harus benar-benar cocok
     * sebelum sesuatu dianggap sebagai jawaban.
     *
     * Sepertiga: cukup longgar untuk kalimat bertele-tele ("saya mau tanya
     * berapa lama…"), cukup ketat untuk menolak pengaduan panjang yang hanya
     * berbagi satu dua kata umum dengan sebuah entri FAQ.
     */
    private const RELEVANCE_FLOOR = 1 / 3;

    public function __construct(
        private readonly NewsReader $news,
        private readonly ServiceReader $services,
        private readonly DocumentReader $documents,
        private readonly FaqReader $faqs,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function items(BotDataSource $source, ?int $override = null): array
    {
        $limit = max(1, min(50, $override ?? $source->limit));

        $rows = match ($source->source) {
            'news' => $this->news->latest($limit)->map(fn ($item) => [
                'title' => $item->title,
                'date' => $item->published_at?->translatedFormat('d M Y') ?? '',
                'excerpt' => Str::limit(strip_tags((string) $item->excerpt ?: (string) $item->content), 300),
                'url' => route('public.news.show', $item->slug),
            ]),
            // `name`, not `title`: a service is named, not headlined. Reading
            // the wrong attribute here printed a numbered list with nothing
            // beside the numbers.
            'services' => $this->services->highlighted($limit)->map(fn ($item) => [
                'title' => $item->name,
                'date' => '',
                'excerpt' => Str::limit(strip_tags((string) $item->description), 300),
                'url' => route('public.services.show', $item->slug),
            ]),
            'documents' => $this->documents->latest($limit)->map(fn ($item) => [
                'title' => $item->title,
                'date' => $item->created_at?->translatedFormat('d M Y') ?? '',
                'excerpt' => Str::limit(strip_tags((string) $item->description), 300),
                'url' => route('public.documents.show', $item->slug),
            ]),
            'faqs' => $this->faqs->highlighted($limit)->map(fn ($item) => [
                'title' => $item->question,
                'date' => '',
                'excerpt' => Str::limit(strip_tags((string) $item->answer), 500),
                'url' => route('public.faq.index'),
            ]),
            'pages' => Page::published()->orderBy('title')->limit($limit)->get()->map(fn (Page $item) => [
                'title' => $item->title,
                'date' => $item->published_at?->translatedFormat('d M Y') ?? '',
                'excerpt' => Str::limit(strip_tags((string) ($item->excerpt ?: $item->content)), 300),
                'url' => route('public.pages.show', $item->slug),
            ]),
            // Jenis pengaduan sebagai bacaan, bukan sebagai pilihan.
            //
            // Node menu sudah bisa menawarkannya lewat `options_from` untuk
            // dipilih; sumber data ini untuk keperluan lain — menjawab "jenis
            // pengaduan apa saja yang dilayani, dan apa yang perlu disiapkan"
            // tanpa memaksa orang masuk ke alur pengaduan lebih dulu.
            'complaint_categories' => ComplaintCategory::active()->limit($limit)->get()
                ->map(fn (ComplaintCategory $item) => [
                    'title' => $item->name,
                    'date' => '',
                    'excerpt' => trim(implode(' ', array_filter([
                        $item->description,
                        match (true) {
                            $item->requires_photo && $item->requires_location => 'Siapkan foto dan titik lokasi.',
                            $item->requires_photo => 'Siapkan foto.',
                            $item->requires_location => 'Siapkan titik lokasi.',
                            default => 'Cukup uraian, tanpa foto.',
                        },
                    ]))),
                    'url' => '',
                ]),
            default => collect(),
        };

        return $rows->values()->all();
    }

    /**
     * Looks for an answer to something somebody typed.
     *
     * Matching is deliberately plain: every significant word of the question is
     * looked for in the title and the body, and rows are ranked by how many
     * they contain. No fuzzy scoring, no stemming — a search nobody can predict
     * is worse than one that plainly finds nothing, because "no match" is a
     * useful answer here: it is what sends the question to a person.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(BotDataSource $source, string $question, int $limit = 3): array
    {
        $words = collect(preg_split('/\W+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY))
            // Short words match everything and rank nothing.
            ->filter(fn (string $word) => mb_strlen($word) >= 4)
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return [];
        }

        // A wider net than the list shows: an answer that exists must be found
        // even when it would sit below the display limit.
        $scored = collect($this->items($source, 50))
            ->map(function (array $item) use ($words) {
                $haystack = mb_strtolower(($item['title'] ?? '').' '.($item['excerpt'] ?? ''));
                $item['_score'] = $words->filter(fn (string $word) => str_contains($haystack, $word))->count();

                return $item;
            })
            ->filter(fn (array $item) => $item['_score'] > 0)
            ->sortByDesc('_score');

        if ($scored->isEmpty()) {
            return [];
        }

        // Sebuah jawaban harus cocok dengan sebagian berarti dari yang
        // ditanyakan, bukan sekadar punya satu kata yang sama.
        //
        // Tanpa ambang ini, sebuah pengaduan panjang — "Irigasi manggis lokasi
        // sudimampir sampai singaraja macet total…" — cocok dengan FAQ tentang
        // izin mendirikan bangunan hanya karena dua kata umum kebetulan sama,
        // lalu dijawab dengan kutipan yang tidak ada hubungannya. Yang paling
        // merugikan bukan jawaban kelirunya: pengaduan itu dianggap sudah
        // terjawab, sehingga tidak pernah diteruskan kepada petugas.
        //
        // "Tidak menemukan apa-apa" adalah jawaban yang berguna di sini — itu
        // yang mengirim pertanyaan kepada manusia.
        if ($scored->first()['_score'] / $words->count() < self::RELEVANCE_FLOOR) {
            return [];
        }

        // Only the entries that match as well as the best one.
        //
        // Without this, "Berapa lama proses izin mendirikan bangunan?" answers
        // with the entry about processing times — correct — and then pastes two
        // more that merely happen to contain the word "bangunan" somewhere,
        // because taking the top three ignores how far behind they scored. A
        // person asking one question reads that as not being answered at all.
        //
        // Ties are kept: entries that match equally well are genuinely
        // ambiguous, and showing them beats picking one arbitrarily.
        $best = $scored->first()['_score'];

        return $scored
            ->filter(fn (array $item) => $item['_score'] === $best)
            ->take($limit)
            ->values()
            ->all();
    }

    /** The numbered list a person picks from. */
    public function list(BotDataSource $source, array $items): string
    {
        $template = $source->list_template ?: '{index}. {title}';

        $lines = [];

        foreach ($items as $index => $item) {
            $lines[] = trim($this->renderer->render($template, $item + ['index' => $index + 1]));
        }

        return implode("\n", $lines);
    }

    public function detail(BotDataSource $source, array $item, int $index): string
    {
        $template = $source->detail_template ?: "*{title}*\n\n{excerpt}\n\n{url}";

        return trim($this->renderer->render($template, $item + ['index' => $index]));
    }
}
