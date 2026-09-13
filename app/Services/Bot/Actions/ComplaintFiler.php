<?php

namespace App\Services\Bot\Actions;

use App\Models\Bot\BotConversation;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use Illuminate\Support\Facades\DB;

/**
 * Turns the answers a conversation gathered into a complaint.
 *
 * Written in one transaction: a complaint whose photographs are missing because
 * the second insert failed is worse than no complaint at all, because the
 * reporter has already been told their report was received.
 */
class ComplaintFiler
{
    /**
     * @param  string|null  $categorySlug  pins the category when the flow knows
     *                                     it — a question has no menu to pick from
     */
    public function file(BotConversation $conversation, ?string $categorySlug = null): ?Complaint
    {
        $description = trim((string) $conversation->answer('description'));

        if ($description === '') {
            return null;
        }

        $evidence = (array) ($conversation->answer('evidence') ?? []);
        $category = $categorySlug
            ? ComplaintCategory::where('slug', $categorySlug)->first()
            : ComplaintCategory::find($conversation->answer('category_id'));
        $contact = $conversation->contact;

        return DB::transaction(function () use ($conversation, $description, $evidence, $category, $contact) {
            $complaint = Complaint::create([
                'ticket' => Complaint::newTicket(),
                'complaint_category_id' => $category?->getKey(),
                'bot_contact_id' => $contact?->getKey(),
                'bot_conversation_id' => $conversation->getKey(),
                'channel' => $conversation->channel?->key ?? 'whatsapp',
                'reporter_name' => $contact?->name,
                'reporter_phone' => $contact?->phone,
                'description' => $description,
                'latitude' => $evidence['lat'] ?? null,
                'longitude' => $evidence['lng'] ?? null,
                'status' => 'baru',
            ]);

            foreach ((array) ($evidence['images'] ?? []) as $path) {
                ComplaintAttachment::create([
                    'complaint_id' => $complaint->getKey(),
                    'kind' => 'report',
                    'path' => $path,
                    // Worked out from the file: the platform's own answer is
                    // often `application/octet-stream`, which a browser
                    // downloads rather than shows.
                    'mime' => \App\Services\Media\MediaService::mimeFor($path),
                ]);
            }

            $complaint->updates()->create([
                'to_status' => 'baru',
                'note' => 'Dilaporkan melalui '.($conversation->channel?->name ?? 'bot').'.',
                'source' => 'system',
            ]);

            return $complaint;
        });
    }
}
