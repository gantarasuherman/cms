<?php

namespace App\Policies;

class BotConversationPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}
