<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records audit trail for all significant operations.
 */
class AuditLogService
{
    public function log(
            string $event,
        ?Model $subject = null,
            array $meta = [],
        ?int $userId = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
                'event' => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
                'meta' => $meta ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function logForUser(
            string $event,
        Model $subject,
            int $userId,
            array $meta = []
    ): AuditLog {
            return $this->log($event, $subject, $meta, $userId);
    }
}
