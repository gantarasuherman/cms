<?php

namespace App\Models;

use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Complaint extends Model
{
    /** @var array<string, string> */
    public const STATUSES = [
        'baru' => 'Baru',
        'diproses' => 'Sedang Diproses',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    protected $fillable = [
        'ticket', 'complaint_category_id', 'bot_contact_id', 'bot_conversation_id', 'channel',
        'reporter_name', 'reporter_phone', 'description', 'latitude', 'longitude', 'address',
        'status', 'assigned_to', 'processed_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'processed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(BotContact::class, 'bot_contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BotConversation::class, 'bot_conversation_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->orderByDesc('id');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    /**
     * An unguessable ticket.
     *
     * Random rather than sequential: this code is quoted back over WhatsApp to
     * read a complaint, so a sequential one would let anybody read every other
     * report by counting. The alphabet drops the characters people misread when
     * copying a code by hand.
     */
    public static function newTicket(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $ticket = 'ADU-'.$code;
        } while (static::where('ticket', $ticket)->exists());

        return $ticket;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::title($this->status);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Pictures the reporter sent, as opposed to proof of completion. */
    public function evidence(): HasMany
    {
        return $this->attachments()->where('kind', 'report');
    }

    public function resolutionProof(): HasMany
    {
        return $this->attachments()->where('kind', 'resolution');
    }
}
