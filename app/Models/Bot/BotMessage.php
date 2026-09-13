<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BotMessage extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected $fillable = [
        'bot_conversation_id', 'direction', 'type', 'body', 'media_path', 'media_mime',
        'latitude', 'longitude', 'node_key', 'external_id', 'delivery_status', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BotConversation::class, 'bot_conversation_id');
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::IN;
    }

    /**
     * Media arriving from a chat is on the private disk, so this is a signed
     * admin route rather than a public URL. A photograph attached to somebody's
     * complaint is not something to leave guessable.
     */
    public function mediaUrl(): ?string
    {
        return $this->media_path ? route('admin.bot.media', ['message' => $this->getKey()]) : null;
    }

    /**
     * Whether this attachment is a picture.
     *
     * By the file as well as the recorded type: Telegram reports photographs
     * as `application/octet-stream`, and trusting that alone left every photo
     * in a transcript rendered as a "buka lampiran" link.
     */
    public function isImage(): bool
    {
        return str_starts_with(\App\Services\Media\MediaService::mimeFor($this->media_path, $this->media_mime), 'image/');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
