<?php

namespace App\Services\Public;

use App\Models\Page;

class PageReader
{
    public function findBySlug(string $slug): ?Page
    {
        return Page::query()->published()->where('slug', $slug)->first();
    }
}

