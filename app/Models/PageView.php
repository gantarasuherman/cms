<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'path', 'route_name', 'visitor_hash', 'referrer_host', 'viewed_on', 'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
            'viewed_at' => 'datetime',
        ];
    }
}

