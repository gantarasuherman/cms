<?php

namespace App\Policies;

class AnnouncementPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'announcement';
    }
}
