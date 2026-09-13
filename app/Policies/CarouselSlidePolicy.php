<?php

namespace App\Policies;

class CarouselSlidePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }
}

