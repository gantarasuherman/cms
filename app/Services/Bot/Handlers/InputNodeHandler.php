<?php

namespace App\Services\Bot\Handlers;

use App\Models\Bot\BotConversation;
use App\Models\Bot\BotNode;
use App\Models\ComplaintCategory;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use App\Services\Bot\NodeResult;
use App\Services\Bot\ExpectationDescriber;
use App\Services\Bot\TemplateRenderer;

/**
 * Asks for something and checks what comes back.
 *
 * The photo-and-location case is the awkward one: the two arrive as separate
 * messages, so the node collects them across turns and only moves on once it
 * holds everything the chosen category demands. What it demands comes from the
 * category row, not from this class knowing that "jalan" needs a picture.
 */
class InputNodeHandler implements NodeHandler
{
    // Set at the top of receive(), so the per-kind methods can explain a
    // rejection without every one of them taking two more arguments.
    private ?BotConversation $conversation = null;

    private ?IncomingMessage $message = null;

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly ExpectationDescriber $describer,
    ) {
    }

    public function enter(BotNode $node, BotConversation $conversation): NodeResult
    {
        // Jenis pengaduan yang tidak mewajibkan bukti apa pun tidak ditanyai
        // bukti sama sekali.
        //
        // Sebelumnya pertanyaannya tetap muncul dengan tawaran "balas LEWATI".
        // Untuk keluhan yang memang tidak ada wujudnya — pelayanan lambat,
        // antrean tak jelas — itu satu langkah yang hanya bisa dijawab dengan
        // menolaknya, dan setiap langkah tambahan adalah tempat orang berhenti
        // mengetik. Bukti kembali ditanyakan begitu salah satu syaratnya
        // dicentang pada jenis pengaduan itu.
        if ($this->isOptional($node, $conversation)) {
            return NodeResult::next('valid');
        }

        $lines = [$this->renderer->render($node->setting('text'), $this->values($conversation))];

        if ($node->setting('skip_label')) {
            $lines[] = '';
            $lines[] = $this->renderer->render($node->setting('skip_label'));
        }

        return NodeResult::ask([OutgoingMessage::text(implode("\n", array_filter($lines)), $node->key)]);
    }

    /**
     * Nilai yang tersedia untuk `{placeholder}` pada pertanyaan node ini.
     *
     * Jawaban-jawaban percakapan apa adanya — `{category}` sudah ada di sana
     * sejak menu jenis pengaduan dijawab — ditambah `{requirements}`, yang
     * bukan jawaban warga melainkan turunan dari jenis yang dipilihnya.
     *
     * @return array<string, mixed>
     */
    private function values(BotConversation $conversation): array
    {
        return ($conversation->state['answers'] ?? []) + [
            'requirements' => $this->describer->requirements($conversation),
        ];
    }

    public function receive(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        $this->conversation = $conversation;
        $this->message = $message;

        // Jalan keluar bernomor, seperti pada menu.
        //
        // Sebuah node yang hanya menerima teks bebas tidak punya "pilihan 0",
        // jadi tanpa ini satu-satunya cara keluar dari perulangan tanya-jawab
        // adalah mengetik "menu" — sesuatu yang tidak pernah diberitahukan di
        // layar itu. Diperiksa sebelum isinya dinilai, supaya "0" tidak lebih
        // dulu ditolak karena terlalu pendek.
        // Dibandingkan terhadap null, bukan diuji kebenarannya: nilai yang
        // paling wajar untuk kolom ini justru "0", dan "0" itu falsy di PHP —
        // sebuah `if ($back = ...)` di sini tidak akan pernah menyala.
        $back = $node->setting('back_on');

        if ($back !== null && $back !== '' && trim($message->body()) === (string) $back) {
            return NodeResult::next('back');
        }

        return match ($node->setting('input', 'text')) {
            'image_location' => $this->receiveEvidence($node, $conversation, $message),
            'image', 'document' => $this->receiveMedia($node, $message),
            'location' => $this->receiveLocation($node, $message),
            'number' => $this->receiveNumber($node, $message),
            default => $this->receiveText($node, $message),
        };
    }

    /* ------------------------------------------------------------- kinds */

    private function receiveText(BotNode $node, IncomingMessage $message): NodeResult
    {
        $answer = $message->body();
        $min = (int) $node->setting('min_length', 0);
        $pattern = $node->setting('pattern');

        if ($answer === '' || mb_strlen($answer) < $min) {
            return $this->reject($node);
        }

        // Delimited here rather than in the stored value, so an administrator
        // types `^ADU-[A-Z0-9]{8}$` and cannot accidentally pass modifiers.
        if ($pattern && ! preg_match('/'.str_replace('/', '\/', $pattern).'/iu', $answer)) {
            return $this->reject($node);
        }

        return NodeResult::next('valid', [], [$this->key($node) => $answer]);
    }

    private function receiveNumber(BotNode $node, IncomingMessage $message): NodeResult
    {
        $answer = str_replace([' ', '.', ','], '', $message->body());

        return is_numeric($answer)
            ? NodeResult::next('valid', [], [$this->key($node) => $answer + 0])
            : $this->reject($node);
    }

    private function receiveMedia(BotNode $node, IncomingMessage $message): NodeResult
    {
        return filled($message->mediaPath)
            ? NodeResult::next('valid', [], [$this->key($node) => $message->mediaPath])
            : $this->reject($node);
    }

    private function receiveLocation(BotNode $node, IncomingMessage $message): NodeResult
    {
        return $message->hasLocation()
            ? NodeResult::next('valid', [], [$this->key($node) => ['lat' => $message->latitude, 'lng' => $message->longitude]])
            : $this->reject($node);
    }

    /**
     * Collects a photograph and a pin, across as many messages as it takes.
     *
     * Each part is kept the moment it arrives, so somebody who sends the photo
     * first and the pin a minute later is not asked for the photo again.
     */
    private function receiveEvidence(BotNode $node, BotConversation $conversation, IncomingMessage $message): NodeResult
    {
        $key = $this->key($node);
        $held = (array) ($conversation->answer($key) ?? []);
        $required = $this->requirements($node, $conversation);

        if ($message->hasImage()) {
            $held['images'][] = $message->mediaPath;
        }

        if ($message->hasLocation()) {
            $held['lat'] = $message->latitude;
            $held['lng'] = $message->longitude;
        }

        // Free text alongside evidence is kept as a note rather than discarded.
        if ($message->type === 'text' && $message->body() !== '') {
            if ($this->isOptional($node, $conversation) && $this->isSkip($message)) {
                return NodeResult::next('valid', [], [$key => $held]);
            }

            $held['note'] = $message->body();
        }

        $missing = array_values(array_filter([
            in_array('image', $required, true) && empty($held['images']) ? 'foto' : null,
            in_array('location', $required, true) && ! isset($held['lat']) ? 'titik lokasi' : null,
        ]));

        if ($missing !== []) {
            // Remembered anyway: the half that did arrive must not be lost
            // just because the other half has not.
            return new NodeResult(
                [OutgoingMessage::text($this->missingMessage($node, $missing, $message), $node->key)],
                'invalid',
                true,
                [$key => $held],
            );
        }

        return NodeResult::next('valid', [], [$key => $held]);
    }

    /* ------------------------------------------------------------ helpers */

    /** What the chosen category insists on. Nothing is hard-coded per category. */
    private function requirements(BotNode $node, BotConversation $conversation): array
    {
        if ($node->setting('requirement_from') !== 'category') {
            return ['image', 'location'];
        }

        $category = ComplaintCategory::find($conversation->answer('category_id'));

        return $category?->evidenceRequired() ?? [];
    }

    private function isOptional(BotNode $node, BotConversation $conversation): bool
    {
        return $node->setting('input') === 'image_location'
            && $this->requirements($node, $conversation) === [];
    }

    private function isSkip(IncomingMessage $message): bool
    {
        return in_array(mb_strtolower($message->body()), ['lewati', 'skip', 'tidak ada', '-'], true);
    }

    private function missingMessage(BotNode $node, array $missing, IncomingMessage $message): string
    {
        $lines = [];

        // An audio note or a sticker here is a different mistake from sending
        // the photo but forgetting the pin, and deserves a different sentence.
        if ($unexpected = $this->describer->received($node, $message)) {
            $lines[] = $unexpected.'.';
        }

        $configured = $this->renderer->render($node->setting('invalid_message'));

        if ($configured !== '') {
            $lines[] = $configured;
        }

        $lines[] = 'Belum ada: '.implode(' dan ', $missing).'.';

        return implode("\n\n", $lines);
    }

    /**
     * Says no, and says what yes would look like.
     *
     * The node's own wording leads when it has one; what is missing is added
     * only when that wording does not already cover it.
     */
    private function reject(BotNode $node): NodeResult
    {
        return NodeResult::invalid([OutgoingMessage::text(
            $this->describer->explain(
                $node,
                $this->conversation,
                $this->message,
                $this->renderer->render($node->setting('invalid_message')),
            ),
            $node->key,
        )]);
    }

    private function key(BotNode $node): string
    {
        return (string) $node->setting('store_as', $node->key);
    }
}
