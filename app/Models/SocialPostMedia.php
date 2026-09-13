<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One slide of a republished post.
 *
 * Like the cover, the file sits on this site's own disk: the platform's URLs
 * are signed, expire within days, and calling them from a visitor's browser
 * would report that visitor to the platform.
 */
class SocialPostMedia extends Model
{
    protected $table = 'social_post_media';

    protected $fillable = ['sort_order', 'kind', 'path', 'remote_url', 'alt_text', 'width', 'height'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    /**
     * The slide's shape, as a CSS aspect-ratio.
     *
     * Instagram shows a whole post at one ratio — the first slide's — and pads
     * the rest, so a mixed-orientation carousel does not jump as it advances.
     * Unknown dimensions fall back to the square Instagram itself defaults to.
     */
    public function ratio(): string
    {
        return $this->width && $this->height ? $this->width.' / '.$this->height : '1 / 1';
    }
}
