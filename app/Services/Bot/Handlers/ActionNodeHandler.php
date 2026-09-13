<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Models\Complaint;
use App\Services\Bot\Actions\ComplaintFiler;
use App\Models\Bot\BotDataSource;
use App\Services\Bot\Actions\OfficerNotifier;
use App\Services\Ai\AiAssistant;
use App\Services\Bot\ContentLister;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Bot\TemplateRenderer;

/**
 * Does something, then reports what happened.
 *
 * Actions are a fixed vocabulary rather than anything an administrator can
 * type: a node that could name arbitrary code would turn the flow editor into
 * a way to run it.
 */
class ActionNodeHandler implements NodeHandler
{
    public function __construct(
        private readonly ComplaintFiler $filer,
        private readonly OfficerNotifier $notifier,
        private readonly ContentLister $lister,
        private readonly AiAssistant $ai,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        return match ($node->setting('action')) {
            'create_complaint' => $this->createComplaint($node, $conversation),
            'lookup_complaint' => $this->lookupComplaint($node, $conversation),
            'notify_officers' => $this->notifyOnly($node, $conversation),
            'answer_question' => $this->answerQuestion($node, $conversation),
            'list_my_complaints' => $this->listMyComplaints($node, $conversation),
            default => $this->failed($node, 'Aksi belum dikenali.'),
        };
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        // An action never waits, so nothing is ever addressed to it.
        return $this->enter($node, $conversation);
    }

    private function createComplaint(BotNode $node, BotConversation $conversation): NodeResult
    {
        $complaint = $this->filer->file($conversation, $node->setting('category_slug'));

        if (! $complaint) {
            return $this->failed($node, 'Pengaduan belum dapat disimpan.');
        }

        if ($node->setting('notify', true)) {
            $this->notifier->notify($complaint);
        }

        return NodeResult::next('valid', [OutgoingMessage::text(
            $this->renderer->render($node->setting('success_message'), $this->values($complaint)),
            $node->key,
        )], ['ticket' => $complaint->ticket]);
    }

    /**
     * Lists the complaints this person filed.
     *
     * Scoped to the contact the conversation belongs to — never a search. The
     * ticket is deliberately unguessable precisely so that knowing one reveals
     * nothing; listing by anything other than "who is asking" would give that
     * away for free.
     */
    private function listMyComplaints(BotNode $node, BotConversation $conversation): NodeResult
    {
        $contactId = $conversation->bot_contact_id;

        $complaints = $contactId
            ? Complaint::where('bot_contact_id', $contactId)->latest('id')->limit(10)->get()
            : collect();

        if ($complaints->isEmpty()) {
            return NodeResult::next('invalid', [OutgoingMessage::text(
                $this->renderer->render($node->setting('not_found_message'))
                    ?: 'Belum ada pengaduan atas nama Anda. Masukkan nomor tiket bila Anda memilikinya.',
                $node->key,
            )]);
        }

        $lines = ['Pengaduan Anda:', ''];

        foreach ($complaints as $index => $complaint) {
            $lines[] = sprintf(
                '%d. %s — %s (%s)',
                $index + 1,
                $complaint->ticket,
                $complaint->statusLabel(),
                $complaint->created_at?->translatedFormat('d M Y') ?? '',
            );
        }

        $lines[] = '';
        $lines[] = 'Balas nomornya untuk melihat rincian, atau ketik nomor tiket lain.';

        return NodeResult::next('valid',
            [OutgoingMessage::text(implode("\n", $lines), $node->key)],
            // Remembered so "2" resolves to the second row actually shown, not
            // to whatever is second a minute later.
            ['_my_tickets' => $complaints->pluck('ticket')->all()],
        );
    }

    private function lookupComplaint(BotNode $node, BotConversation $conversation): NodeResult
    {
        $answer = trim((string) $conversation->answer($node->setting('from', 'ticket')));
        $ticket = $this->resolveTicket($answer, $conversation);
        $complaint = $ticket === null ? null : Complaint::where('ticket', $ticket)->first();

        // Says "not found" whether the ticket never existed or belongs to
        // somebody else. Distinguishing the two would let anyone discover which
        // tickets are real by trying them.
        if (! $complaint) {
            return $this->failed($node, 'Nomor tiket tidak ditemukan.');
        }

        return NodeResult::next('valid', [OutgoingMessage::text(
            $this->renderer->render($node->setting('success_message'), $this->values($complaint)),
            $node->key,
        )]);
    }

    private function notifyOnly(BotNode $node, BotConversation $conversation): NodeResult
    {
        $complaint = Complaint::where('ticket', $conversation->answer('ticket'))->first();

        if ($complaint) {
            $this->notifier->notify($complaint);
        }

        return NodeResult::next('valid');
    }

    /**
     * Tries to answer a question from published content.
     *
     * Found → the answer, and the flow ends. Not found → `invalid`, which is
     * what the flow routes onward to file the question as something a person
     * will read. A bot that says "I don't know" and stops is a bot that wastes
     * whoever asked; the point of not finding an answer is to pass it on.
     */
    private function answerQuestion(BotNode $node, BotConversation $conversation): NodeResult
    {
        $question = trim((string) $conversation->answer($node->setting('from', 'question')));
        $source = BotDataSource::active()->where('slug', $node->setting('data_source'))->first();

        if ($question === '' || ! $source) {
            return NodeResult::next('invalid', [], ['answer_found' => false]);
        }

        $matches = $this->lister->search($source, $question);

        if ($matches === []) {
            $message = $this->renderer->render($node->setting('not_found_message'))
                ?: 'Pertanyaan Anda belum ada jawabannya di sini. Akan kami teruskan kepada petugas.';

            // Remembered so the complaint filed next carries the question as
            // its description without asking the person to type it again.
            return NodeResult::next('invalid',
                [OutgoingMessage::text($message, $node->key)],
                ['answer_found' => false, 'description' => $question],
            );
        }

        // A sentence written from what was found reads better than three
        // pasted entries — but only when the model produced one. Otherwise the
        // entries themselves are the answer, exactly as before.
        if ($written = $this->ai->answer($question, $matches)) {
            return NodeResult::next('valid',
                [OutgoingMessage::text($written, $node->key)],
                ['answer_found' => true, 'answered_by' => 'ai'],
            );
        }

        $lines = [];

        foreach ($matches as $index => $match) {
            $lines[] = $this->lister->detail($source, $match, $index + 1);
        }

        return NodeResult::next('valid',
            [OutgoingMessage::text(implode("\n\n———\n\n", $lines), $node->key)],
            ['answer_found' => true],
        );
    }

    /**
     * A ticket, from either a ticket number or a row of the list just shown.
     *
     * The row numbers only resolve against that person's own list, so they can
     * never be used to reach somebody else's complaint.
     */
    private function resolveTicket(string $answer, BotConversation $conversation): ?string
    {
        if ($answer === '') {
            return null;
        }

        if (ctype_digit($answer)) {
            $mine = (array) ($conversation->answer('_my_tickets') ?? []);

            return $mine[(int) $answer - 1] ?? null;
        }

        return strtoupper($answer);
    }

    /** @return array<string, string> */
    private function values(Complaint $complaint): array
    {
        $latest = $complaint->updates()->first();

        return [
            'ticket' => $complaint->ticket,
            'category' => $complaint->category?->name ?? 'Tanpa kategori',
            'status' => $complaint->statusLabel(),
            'created_at' => $complaint->created_at?->translatedFormat('d M Y H:i') ?? '',
            'latest_update' => $latest?->note ?? 'Belum ada perkembangan.',
        ];
    }

    private function failed(BotNode $node, string $fallback): NodeResult
    {
        return NodeResult::next('invalid', [OutgoingMessage::text(
            $this->renderer->render($node->setting('failure_message')) ?: $fallback,
            $node->key,
        )]);
    }
}
