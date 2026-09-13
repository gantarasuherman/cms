<?php

namespace App\Policies;

class BotRecipientPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}
