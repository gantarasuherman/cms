<?php

namespace App\Models\Bot;

use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A number or group that hears about new complaints, and may be allowed to
 * command the ones it hears about.
 */
class BotRecipient extends Model
{
    protected $fillable = ['name', 'channel', 'destination', 'is_active', 'can_command', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_command' => 'boolean',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ComplaintCategory::class, 'bot_recipient_categories');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Recipients who asked to hear about this category. */
    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->active()->when(
            $categoryId,
            fn (Builder $q) => $q->whereHas('categories', fn (Builder $c) => $c->whereKey($categoryId)),
        );
    }

    public function channelLabel(): string
    {
        return BotChannel::KEYS[$this->channel] ?? $this->channel;
    }
}
