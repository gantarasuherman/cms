<?php

namespace App\Services\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use Illuminate\Support\Facades\DB;

/**
 * Handles officers replying from their own phone or from a Telegram group.
 *
 * Authority comes from the `bot_recipients` row the message arrived from, not
 * from anything in the message itself: a destination that was registered and
 * ticked `can_command` may change a status, and nothing else may. A group
 * where a complaint was merely announced cannot resolve it.
 *
 * Officers are also only allowed to act on complaints in the categories they
 * cover — otherwise being told about a road defect would let someone close an
 * irrigation report they were never shown.
 */
class CommandHandler
{
    private const COMMANDS = [
        'proses' => 'diproses',
        'selesai' => 'selesai',
        'tolak' => 'ditolak',
    ];

    /**
     * @return array<int, OutgoingMessage>|null null when this is not a command
     *                                          message from an authorised place
     */
    public function handle(BotChannel $channel, IncomingMessage $message, BotRecipient $recipient): ?array
    {
        $command = $message->command();

        if ($command === null || ! isset(self::COMMANDS[$command])) {
            return null;
        }

        if (! $recipient->can_command) {
            return [OutgoingMessage::text('Nomor atau grup ini tidak berwenang mengubah status pengaduan.')];
        }

        $ticket = $this->ticket($message);

        if ($ticket === null) {
            return [OutgoingMessage::text('Sertakan nomor tiketnya. Contoh: /'.$command.' ADU-K7M2PQR9')];
        }

        $complaint = Complaint::where('ticket', $ticket)->first();

        if (! $complaint) {
            return [OutgoingMessage::text('Tiket '.$ticket.' tidak ditemukan.')];
        }

        if (! $this->covers($recipient, $complaint)) {
            return [OutgoingMessage::text('Pengaduan '.$ticket.' bukan kategori yang ditugaskan ke Anda.')];
        }

        $status = self::COMMANDS[$command];

        if ($complaint->status === $status) {
            return [OutgoingMessage::text('Pengaduan '.$ticket.' memang sudah berstatus '.$complaint->statusLabel().'.')];
        }

        return [OutgoingMessage::text($this->apply($complaint, $status, $message, $recipient))];
    }

    /** Whether a command from this destination may touch this complaint. */
    public function covers(BotRecipient $recipient, Complaint $complaint): bool
    {
        // A recipient with no categories ticked covers everything; that is how
        // a single duty officer is configured.
        if ($recipient->categories()->count() === 0) {
            return true;
        }

        return $recipient->categories()->whereKey($complaint->complaint_category_id)->exists();
    }

    private function apply(Complaint $complaint, string $status, IncomingMessage $message, BotRecipient $recipient): string
    {
        $from = $complaint->status;
        $note = $this->note($message);

        DB::transaction(function () use ($complaint, $status, $from, $note, $message, $recipient) {
            $complaint->status = $status;
            $complaint->processed_at ??= $status === 'diproses' ? now() : $complaint->processed_at;

            if ($status === 'selesai') {
                $complaint->resolved_at = now();
            }

            $complaint->save();

            // A photograph sent with /selesai is proof of the work, not a
            // second copy of the original report.
            if (filled($message->mediaPath)) {
                ComplaintAttachment::create([
                    'complaint_id' => $complaint->getKey(),
                    'kind' => $status === 'selesai' ? 'resolution' : 'report',
                    'path' => $message->mediaPath,
                    'mime' => $message->mediaMime,
                ]);
            }

            $complaint->updates()->create([
                'from_status' => $from,
                'to_status' => $status,
                'note' => $note,
                'user_id' => $recipient->user_id,
                'source' => $recipient->channel,
                'source_actor' => $recipient->name.' ('.$recipient->destination.')',
            ]);
        });

        $reply = 'Pengaduan '.$complaint->ticket.' ditandai '.$complaint->statusLabel().'.';

        if (filled($message->mediaPath)) {
            $reply .= ' Foto tindak lanjut tersimpan.';
        }

        return $reply;
    }

    /** The ticket named in the command, or the one the reply pointed at. */
    private function ticket(IncomingMessage $message): ?string
    {
        return preg_match('/\bADU-[A-Z0-9]{8}\b/i', $message->body(), $matches)
            ? strtoupper($matches[0])
            : null;
    }

    /** Whatever the officer wrote after the command and the ticket. */
    private function note(IncomingMessage $message): ?string
    {
        $note = trim(preg_replace(
            ['/^\/\S+\s*/', '/\bADU-[A-Z0-9]{8}\b/i'],
            '',
            $message->body(),
        ) ?? '');

        return $note !== '' ? $note : null;
    }
}
