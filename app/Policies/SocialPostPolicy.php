<?php

namespace App\Policies;

class SocialPostPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'social_post';
    }
}
