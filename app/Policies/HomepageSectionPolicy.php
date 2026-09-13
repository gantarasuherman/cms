<?php

namespace App\Policies;

class HomepageSectionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }
}

