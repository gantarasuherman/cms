<?php

namespace App\Services\Bot\Actions;

use App\Models\Bot\BotMessage;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sends a message back to whoever filed a complaint.
 *
 * Queued to the outbox rather than sent inline, for the same reason the
 * officer notification is: the request that writes the reply must not fail
 * because a platform was briefly unreachable, and a reply nobody received is
 * worse than one that arrives a minute late.
 *
 * This is not the same thing as answering inside the conversation transcript,
 * which deliberately has no reply box. That would cut across whatever question
 * the flow is in the middle of asking. A reply about a filed complaint is
 * out-of-band — it arrives like the officer notification does, changes no
 * session state, and is what the person was told to expect when they got their
 * ticket.
 */
class ReporterReplier
{
    /** @return bool false when there is nobody reachable to reply to */
    public function send(Complaint $complaint, string $body, ?string $mediaPath = null, ?User $by = null): bool
    {
        $contact = $complaint->contact;

        if (! $contact || blank($contact->external_id) || ! $contact->channel) {
            return false;
        }

        DB::transaction(function () use ($complaint, $contact, $body, $mediaPath, $by) {
            DB::table('bot_outbox')->insert([
                'channel' => $contact->channel->key,
                'destination' => $contact->external_id,
                'type' => $mediaPath ? 'image' : 'text',
                'body' => $body,
                'media_path' => $mediaPath,
                'payload' => json_encode(['complaint_id' => $complaint->getKey()]),
                'status' => 'pending',
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Written onto the transcript too, so the conversation reads as one
            // exchange rather than the reporter's half of it.
            if ($complaint->bot_conversation_id) {
                BotMessage::create([
                    'bot_conversation_id' => $complaint->bot_conversation_id,
                    'direction' => BotMessage::OUT,
                    'type' => $mediaPath ? 'image' : 'text',
                    'body' => $body,
                    'media_path' => $mediaPath,
                    'node_key' => 'admin',
                ]);
            }

            $complaint->updates()->create([
                'from_status' => $complaint->status,
                'to_status' => $complaint->status,
                'note' => $body,
                'user_id' => $by?->getKey(),
                'source' => 'admin',
                'source_actor' => $by?->name,
            ]);
        });

        return true;
    }

    /** The wording sent when a status changes and the reporter is to be told. */
    public function statusMessage(Complaint $complaint, ?string $note = null): string
    {
        $lines = [
            'Kabar pengaduan Anda.',
            '',
            'Tiket: *'.$complaint->ticket.'*',
            'Status: *'.$complaint->statusLabel().'*',
        ];

        if (filled($note)) {
            $lines[] = '';
            $lines[] = $note;
        }

        return implode("\n", $lines);
    }
}
