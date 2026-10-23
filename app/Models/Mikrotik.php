<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mikrotik extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_name',
        'description',
        'vpn_ip',
        'vpn_username',
        'winbox_internal_port',
        'webfig_internal_port',
        'winbox_public_port',
        'webfig_public_port',
        'routeros_version',
        'is_online',
        'last_seen_at',
        'ssh_public_key',
    ];

    protected $casts = [
        'winbox_internal_port' => 'integer',
        'webfig_internal_port' => 'integer',
        'winbox_public_port' => 'integer',
        'webfig_public_port' => 'integer',
            'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    // no hidden fields — password lives in vpn_users table

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_username', 'username');
    }

    public function nginxMappings(): HasMany
    {
        return $this->hasMany(NginxMapping::class);
    }

    public function routerBackups(): HasMany
    {
        return $this->hasMany(RouterBackup::class);
    }

    public function isOnline(): bool
    {
            return $this->is_online === true;
    }

    public function scopeOnline($query)
    {
            return $query->where('is_online', true);
    }

    public function scopeOffline($query)
    {
            return $query->where('is_online', false);
    }

    public function getWinboxUrlAttribute(): string
    {
        $domain = config('vpnpanel.domain');
        $port = $this->winbox_public_port ?? $this->winbox_internal_port;
        return "{$domain}:{$port}";
    }

    public function getWebfigUrlAttribute(): string
    {
        $domain = config('vpnpanel.domain');
        $port = $this->webfig_public_port ?? $this->webfig_internal_port;
        return "{$domain}:{$port}";
    }

        /**
         * Get the VPN password from the related VpnUser record.
         * This is the single source of truth for the password.
         */
        public function vpnPassword(): string
        {
            return $this->vpnUser?->password ?? '';
        }
}
