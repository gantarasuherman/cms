<?php

namespace App\Policies;

class SocialLinkPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }
}

