<?php

namespace App\Services\Bot;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Models\ComplaintCategory;
use App\Services\Bot\Messages\IncomingMessage;
use App\Support\BotNodes;

/**
 * Says what the bot was actually waiting for.
 *
 * A bare "jawaban tidak sesuai" tells somebody they are wrong without telling
 * them what right looks like, and on a phone the original question has usually
 * scrolled away. This turns the node's own configuration into that sentence —
 * so a flow an administrator drew explains itself without them writing an
 * error message for every branch.
 */
class ExpectationDescriber
{
    /** What a valid reply to this node would be. */
    public function expected(BotNode $node, BotConversation $conversation): string
    {
        return match ($node->type) {
            BotNodes::MENU => 'salah satu angka pada daftar di atas',
            BotNodes::DATA_SOURCE => 'nomor salah satu item pada daftar, atau 0 untuk kembali',
            BotNodes::INPUT => $this->forInput($node, $conversation),
            default => 'balasan berupa teks',
        };
    }

    /**
     * Names what arrived, when it is plainly not what was asked for.
     *
     * Returns null when the reply was at least the right kind of thing and only
     * the content was wrong — saying "Anda mengirim teks" to someone who sent
     * text would be noise.
     */
    public function received(BotNode $node, IncomingMessage $message): ?string
    {
        $wants = $this->wanted($node);
        $got = $this->kind($message);

        if ($got === null || in_array($got, $wants, true)) {
            return null;
        }

        return match ($got) {
            'image' => 'Anda mengirim gambar',
            'location' => 'Anda mengirim titik lokasi',
            'document' => 'Anda mengirim berkas',
            'audio' => 'Anda mengirim pesan suara',
            'video' => 'Anda mengirim video',
            'sticker' => 'Anda mengirim stiker',
            'contact' => 'Anda mengirim kontak',
            default => 'Anda mengirim '.$got,
        };
    }

    /** The whole sentence a handler sends back on a wrong reply. */
    public function explain(BotNode $node, BotConversation $conversation, ?IncomingMessage $message, ?string $configured): string
    {
        $lines = [];

        if ($message && $received = $this->received($node, $message)) {
            $lines[] = $received.', padahal yang ditunggu adalah '.$this->expected($node, $conversation).'.';
        } else {
            $lines[] = trim((string) $configured) !== ''
                ? trim((string) $configured)
                : 'Balasan belum sesuai.';

            // Only added when the administrator's own wording did not already
            // say it, or the reply repeats itself.
            if (! $this->mentionsExpectation($configured, $node)) {
                $lines[] = 'Yang ditunggu: '.$this->expected($node, $conversation).'.';
            }
        }

        return implode("\n\n", $lines);
    }

    /** @return array<int, string> message kinds this node accepts */
    private function wanted(BotNode $node): array
    {
        if ($node->type !== BotNodes::INPUT) {
            return ['text'];
        }

        return match ($node->setting('input', 'text')) {
            'image' => ['image'],
            'document' => ['document'],
            'location' => ['location'],
            // Free text alongside evidence is kept as a note, so text is not
            // an intruder here.
            'image_location' => ['image', 'location', 'text'],
            default => ['text'],
        };
    }

    private function kind(IncomingMessage $message): ?string
    {
        if ($message->hasLocation()) {
            return 'location';
        }

        return $message->type === 'text' && $message->body() === ''
            ? null
            : $message->type;
    }

    private function forInput(BotNode $node, BotConversation $conversation): string
    {
        return match ($node->setting('input', 'text')) {
            'number' => 'jawaban berupa angka',
            'image' => 'sebuah foto',
            'document' => 'sebuah berkas',
            'location' => 'titik lokasi, melalui menu Lampirkan → Lokasi',
            'image_location' => $this->forEvidence($node, $conversation),
            default => $this->forText($node),
        };
    }

    private function forText(BotNode $node): string
    {
        if ($pattern = $node->setting('pattern')) {
            // A regular expression is not an instruction anyone can follow, so
            // the example the administrator typed is used instead.
            return str_contains((string) $node->setting('text'), 'Contoh')
                ? 'jawaban dengan format seperti contoh di atas'
                : 'jawaban dengan format yang sesuai';
        }

        if ($min = (int) $node->setting('min_length', 0)) {
            return 'penjelasan sepanjang minimal '.$min.' karakter';
        }

        return 'balasan berupa teks';
    }

    private function forEvidence(BotNode $node, BotConversation $conversation): string
    {
        $required = $node->setting('requirement_from') === 'category'
            ? (ComplaintCategory::find($conversation->answer('category_id'))?->evidenceRequired() ?? [])
            : ['image', 'location'];

        return match (true) {
            $required === ['image', 'location'] => 'foto dan titik lokasi',
            $required === ['image'] => 'sebuah foto',
            $required === ['location'] => 'titik lokasi',
            default => 'foto dan titik lokasi bila ada, atau balas LEWATI',
        };
    }

    private function mentionsExpectation(?string $configured, BotNode $node): bool
    {
        $text = mb_strtolower((string) $configured);

        return $text !== '' && (
            str_contains($text, 'yang ditunggu')
            || str_contains($text, 'angka')
            || str_contains($text, 'format')
            || str_contains($text, 'wajib')
        );
    }
}
