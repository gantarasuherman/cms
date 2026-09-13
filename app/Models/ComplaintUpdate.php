<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintUpdate extends Model
{
    protected $fillable = [
        'complaint_id', 'from_status', 'to_status', 'note', 'user_id', 'source', 'source_actor',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who made the change, in words.
     *
     * An officer replying "/selesai" in a Telegram group has no Laravel
     * session, so the actor is whatever the channel knew about them.
     */
    public function actor(): string
    {
        return $this->user?->name
            ?? ($this->source_actor ? $this->source_actor.' ('.$this->source.')' : 'Sistem');
    }
}
