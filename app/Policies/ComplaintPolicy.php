<?php

namespace App\Policies;

class ComplaintPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'complaint';
    }
}
