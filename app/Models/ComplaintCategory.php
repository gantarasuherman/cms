<?php

namespace App\Models;

use App\Models\Bot\BotRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'description', 'requires_photo', 'requires_location', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_photo' => 'boolean',
            'requires_location' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(BotRecipient::class, 'bot_recipient_categories');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** What the bot must collect before it will file a complaint here. */
    public function evidenceRequired(): array
    {
        return array_keys(array_filter([
            'image' => $this->requires_photo,
            'location' => $this->requires_location,
        ]));
    }
}
