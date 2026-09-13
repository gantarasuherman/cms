<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An editor's decision about a question the bot keeps being asked: turned into
 * an FAQ, or set aside.
 */
class BotQuestionTopic extends Model
{
    public const NEW = 'new';

    public const PROMOTED = 'promoted';

    public const IGNORED = 'ignored';

    protected $fillable = ['fingerprint', 'sample', 'status', 'faq_id', 'decided_by'];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
