<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NginxMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'mikrotik_id',
            'conn_type',
        'public_port',
            'is_enabled',
    ];

    protected $casts = [
        'mikrotik_id' => 'integer',
        'public_port' => 'integer',
            'is_enabled' => 'boolean',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function scopeEnabled($query)
    {
            return $query->where('is_enabled', true);
    }

    public function scopeType($query, string $type)
    {
            return $query->where('conn_type', $type);
    }

    public function getUpstreamNameAttribute(): string
    {
        $site = str_replace([' ', '-', '.'], '_', strtolower($this->mikrotik->site_name));
            return "mt_{$site}_{$this->conn_type}";
    }
}
