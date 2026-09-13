<?php

namespace App\Policies;

class BotFlowPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}
