<?php

namespace App\Policies;

class BotChannelPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}
