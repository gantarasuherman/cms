<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Single write path for the audit trail. Controllers and services call this
 * rather than touching AuditLog directly, so redaction and request metadata
 * stay consistent across modules.
 */
class AuditLogger
{
    /** Attribute names never persisted into the audit trail. */
    private const REDACTED = ['password', 'password_confirmation', 'remember_token'];

    public function record(
        string $action,
        string $module,
        int|string|null $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId !== null ? (string) $recordId : null,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ]);
    }

    /** Records a model change, capturing only the attributes that actually moved. */
    public function recordModel(string $action, string $module, Model $model, ?array $original = null): AuditLog
    {
        $new = $model->getAttributes();
        $old = $original;

        if ($old !== null) {
            $changed = array_keys(array_diff_assoc(
                array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $new),
                array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $old),
            ));

            $old = array_intersect_key($old, array_flip($changed));
            $new = array_intersect_key($new, array_flip($changed));
        }

        return $this->record($action, $module, $model->getKey(), $old, $new);
    }

    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}

