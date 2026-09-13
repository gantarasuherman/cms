<?php

namespace App\Policies;

class DocumentPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'document';
    }
}

