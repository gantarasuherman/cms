<?php

namespace App\Models;

class AdminMenu extends Menu
{
    protected $table = 'admin_menus';

    protected $fillable = [
        'parent_id', 'title', 'slug', 'icon', 'route', 'url',
        'permission', 'sort_order', 'is_active', 'target',
    ];
}

