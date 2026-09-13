<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotContact extends Model
{
    protected $fillable = [
        'bot_channel_id', 'external_id', 'name', 'phone', 'username',
        'avatar_path', 'avatar_ref', 'is_blocked', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(BotChannel::class, 'bot_channel_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(BotConversation::class);
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->username ? '@'.$this->username : $this->maskedPhone());
    }

    /** Served through a guarded route: nobody published this picture. */
    public function avatarUrl(): ?string
    {
        return filled($this->avatar_path) ? route('admin.bot.avatar', ['contact' => $this->getKey()]) : null;
    }

    /**
     * Up to two letters for someone with no picture.
     *
     * Every WhatsApp contact is in this case — the Cloud API does not expose
     * profile pictures — so this is the normal state, not a rare fallback.
     */
    public function initials(): string
    {
        $source = trim((string) ($this->name ?: $this->username));

        if ($source === '') {
            return mb_substr(preg_replace('/\D/', '', (string) $this->external_id) ?: '?', -2);
        }

        $words = preg_split('/\s+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($words) > 1
            ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
            : mb_substr($source, 0, 2));
    }

    /**
     * The number as an operator needs it, unmasked.
     *
     * Masked in lists that sit open on a desk all day; whole here, because
     * opening one conversation is a deliberate act and an operator who has to
     * ring somebody back cannot dial dots. WhatsApp's own id is the number;
     * Telegram only has one if the person shared it.
     */
    public function contactNumber(): ?string
    {
        if (filled($this->phone)) {
            return $this->phone;
        }

        return $this->channel?->key === BotChannel::WHATSAPP ? $this->external_id : null;
    }

    /**
     * A phone number with its middle hidden.
     *
     * Operators need to recognise a reporter across screens; they do not need
     * the whole number on a list that is open on a desk all day.
     */
    public function maskedPhone(): string
    {
        $phone = (string) ($this->phone ?: $this->external_id);

        if (strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 4).str_repeat('•', max(3, strlen($phone) - 7)).substr($phone, -3);
    }
}
