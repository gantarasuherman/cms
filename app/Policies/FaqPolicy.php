<?php

namespace App\Policies;

class FaqPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'faq';
    }
}

