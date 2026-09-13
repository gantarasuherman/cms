<?php

namespace App\Policies;

class ServicePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'service';
    }
}

