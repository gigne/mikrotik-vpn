<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
            'is_read',
        'sent_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
            'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
            return $query->where('is_read', false);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function markAsRead(): void
    {
            $this->update(['is_read' => true]);
    }
}
