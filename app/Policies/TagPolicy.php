<?php

namespace App\Policies;

class TagPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'tag';
    }
}

