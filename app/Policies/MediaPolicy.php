<?php

namespace App\Policies;

class MediaPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'media';
    }
}

