<?php

namespace App\Policies;

class CategoryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'category';
    }
}

