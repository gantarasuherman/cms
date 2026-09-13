<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotConversation extends Model
{
    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'bot_channel_id', 'bot_contact_id', 'bot_flow_id', 'flow_version', 'current_node',
        'state', 'status', 'retry_count', 'last_message_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'retry_count' => 'integer',
            'flow_version' => 'integer',
            'last_message_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(BotChannel::class, 'bot_channel_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(BotContact::class, 'bot_contact_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BotMessage::class)->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** An answer gathered earlier in the conversation. */
    public function answer(string $key, mixed $default = null): mixed
    {
        return data_get($this->state, 'answers.'.$key, $default);
    }

    public function remember(string $key, mixed $value): void
    {
        $state = $this->state ?? [];
        data_set($state, 'answers.'.$key, $value);
        $this->state = $state;
    }
}
