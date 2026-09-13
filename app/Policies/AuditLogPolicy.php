<?php

namespace App\Policies;

class AuditLogPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'audit_log';
    }
}

