<?php

namespace App\Policies;

class BotDataSourcePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}
