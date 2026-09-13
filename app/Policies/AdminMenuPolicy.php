<?php

namespace App\Policies;

class AdminMenuPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'menu';
    }
}

