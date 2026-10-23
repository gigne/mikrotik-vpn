<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VpnUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'username',
        'password',
        'assigned_ip',
        'notes',
        'expired_at',
            'is_enabled',
        'last_used_at',
    ];

    protected $casts = [
            'expired_at' => 'datetime',
            'is_enabled' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public function mikrotiks(): HasMany
    {
        return $this->hasMany(Mikrotik::class, 'vpn_username', 'username');
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function isActive(): bool
    {
            return $this->is_enabled && ! $this->isExpired();
    }

    public function scopeActive($query)
    {
            return $query->where('is_enabled', true)
                     ->where(function ($q) {
                         $q->whereNull('expired_at')
                           ->orWhere('expired_at', '>', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expired_at')
                     ->where('expired_at', '<=', now());
    }
}
