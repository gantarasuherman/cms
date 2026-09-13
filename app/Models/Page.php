<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, HasSlug, Publishable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image',
        'status', 'published_at', 'seo_title', 'seo_description', 'seo_keywords',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected function slugSourceColumn(): string
    {
        return 'title';
    }
}

