<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
            'event',
        'subject_type',
        'subject_id',
            'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'subject_id' => 'integer',
            'meta' => 'array',
    ];

        public $timestamps = ['created_at'];
        public const UPDATED_AT = null;

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }

        public function subject()
        {
            if (! $this->subject_type || ! $this->subject_id) {
                return null;
            }
            return $this->subject_type::find($this->subject_id);
        }
}
