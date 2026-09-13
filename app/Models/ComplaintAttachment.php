<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    protected $fillable = ['complaint_id', 'kind', 'path', 'mime', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Served through a controlled route, never a public disk URL.
     *
     * These are photographs of somebody's house, street or property, sent in
     * private. The documents module makes the same choice for the same reason.
     */
    public function isImage(): bool
    {
        return str_starts_with(\App\Services\Media\MediaService::mimeFor($this->path, $this->mime), 'image/');
    }

    public function url(): string
    {
        return route('admin.complaints.attachment', ['attachment' => $this->getKey()]);
    }
}
