<?php

namespace App\Services\Bot\Actions;

use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

/**
 * Queues a note to whoever covers this complaint's category.
 *
 * Queued, not sent: this runs inside the request that files the complaint, and
 * a platform being briefly unreachable must not cost the reporter their report.
 * The outbox is drained separately and retried.
 *
 * Nobody covering the category means nobody is told — deliberately. Falling
 * back to "tell everyone" would quietly undo the assignment an administrator
 * set up, and the complaint is still on the admin screen either way.
 */
class OfficerNotifier
{
    public function notify(Complaint $complaint): int
    {
        $recipients = BotRecipient::forCategory($complaint->complaint_category_id)->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $body = $this->compose($complaint);
        $queued = 0;

        DB::transaction(function () use ($recipients, $complaint, $body, &$queued) {
            foreach ($recipients as $recipient) {
                DB::table('bot_outbox')->insert([
                    'channel' => $recipient->channel,
                    'destination' => $recipient->destination,
                    'type' => 'text',
                    'body' => $body,
                    'payload' => json_encode(['complaint_id' => $complaint->getKey()]),
                    'status' => 'pending',
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $queued++;
            }
        });

        return $queued;
    }

    private function compose(Complaint $complaint): string
    {
        $lines = [
            'PENGADUAN BARU',
            '',
            'Tiket: '.$complaint->ticket,
            'Kategori: '.($complaint->category?->name ?? 'Tanpa kategori'),
            'Kanal: '.$complaint->channel,
            '',
            $complaint->description,
        ];

        if ($complaint->hasLocation()) {
            // A plain maps link rather than a pin attachment: it works the same
            // in a Telegram group and a WhatsApp message, and it is readable.
            $lines[] = '';
            $lines[] = 'Lokasi: https://www.google.com/maps?q='.$complaint->latitude.','.$complaint->longitude;
        }

        $photos = $complaint->evidence()->count();

        if ($photos > 0) {
            $lines[] = $photos.' foto terlampir pada panel admin.';
        }

        $lines[] = '';
        $lines[] = 'Balas /proses '.$complaint->ticket.' untuk menandai sedang dikerjakan,';
        $lines[] = 'atau /selesai '.$complaint->ticket.' bila sudah ditangani.';

        return implode("\n", $lines);
    }
}
