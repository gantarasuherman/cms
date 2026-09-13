<?php

namespace App\Policies;

class PublicMenuPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'menu';
    }
}

