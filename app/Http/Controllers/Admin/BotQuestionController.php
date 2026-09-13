<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotQuestionTopic;
use App\Models\Faq;
use App\Services\Audit\AuditLogger;
use App\Services\Bot\QuestionDigest;
use App\Services\Cache\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Questions people keep asking the bot, and what to do about them.
 *
 * A question asked once is a conversation. The same question asked eleven
 * times is a gap in the published FAQ — and every one of those eleven times,
 * the bot improvised an answer from whatever it could find. Turning it into an
 * FAQ answers it once, in writing, for the website *and* for the bot: the AI
 * node reads the FAQ as one of its sources, so a promoted question stops being
 * improvised the moment it is published.
 */
class BotQuestionController extends Controller
{
    public function __construct(
        private readonly QuestionDigest $digest,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Faq::class);

        $status = in_array($request->string('status')->toString(), [BotQuestionTopic::PROMOTED, BotQuestionTopic::IGNORED], true)
            ? $request->string('status')->toString()
            : BotQuestionTopic::NEW;

        $minimum = max(1, min(20, (int) $request->integer('minimal', 1)));

        return view('admin.bot.questions.index', [
            'topics' => $this->digest->topics($status, $minimum),
            'status' => $status,
            'minimum' => $minimum,
            'counts' => [
                BotQuestionTopic::NEW => $this->digest->topics(BotQuestionTopic::NEW)->count(),
                BotQuestionTopic::PROMOTED => BotQuestionTopic::where('status', BotQuestionTopic::PROMOTED)->count(),
                BotQuestionTopic::IGNORED => BotQuestionTopic::where('status', BotQuestionTopic::IGNORED)->count(),
            ],
            'nodes' => $this->digest->questionNodes(),
        ]);
    }

    /**
     * Turns a question into an FAQ entry and opens it for an answer.
     *
     * The entry is created inactive with the question filled in and the answer
     * empty: only a person can write the answer, and publishing an empty one
     * would put a blank card on the public site. The editor writes it and
     * switches it on.
     */
    public function promote(Request $request): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
        ]);

        $fingerprint = $this->digest->fingerprint($data['question']);

        abort_if($fingerprint === '', 422, 'Pertanyaan ini tidak punya kata yang bisa dijadikan topik.');

        $existing = BotQuestionTopic::where('fingerprint', $fingerprint)->first();

        if ($existing?->faq) {
            // Already promoted, by whoever got there first. Open theirs rather
            // than making a second FAQ saying the same thing.
            return redirect()
                ->route('admin.faq.edit', $existing->faq)
                ->with('success', 'Pertanyaan ini sudah pernah dijadikan FAQ.');
        }

        $faq = Faq::create([
            'question' => $data['question'],
            'answer' => '',
            'is_active' => false,
            'sort_order' => (int) Faq::max('sort_order') + 10,
        ]);

        BotQuestionTopic::updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'sample' => mb_substr($data['question'], 0, 500),
                'status' => BotQuestionTopic::PROMOTED,
                'faq_id' => $faq->getKey(),
                'decided_by' => $request->user()->getKey(),
            ],
        );

        $this->audit->record('create', 'faq', $faq->getKey());
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()
            ->route('admin.faq.edit', $faq)
            ->with('success', 'Pertanyaan disalin ke FAQ. Tulis jawabannya, lalu aktifkan agar tampil di situs dan dipakai chatbot.');
    }

    /** Sets a question aside without answering it. */
    public function ignore(Request $request): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $fingerprint = $this->digest->fingerprint($data['question']);

        abort_if($fingerprint === '', 422);

        BotQuestionTopic::updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'sample' => mb_substr($data['question'], 0, 500),
                'status' => BotQuestionTopic::IGNORED,
                'faq_id' => null,
                'decided_by' => $request->user()->getKey(),
            ],
        );

        return back()->with('success', 'Pertanyaan disingkirkan dari daftar.');
    }

    /** Puts a set-aside question back in the list. */
    public function restore(BotQuestionTopic $topic): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        // Deleted outright rather than set back to "new": with no row at all,
        // the question is simply counted from the messages again, which is
        // where the count lives anyway.
        $topic->delete();

        return back()->with('success', 'Pertanyaan dikembalikan ke daftar.');
    }
}
