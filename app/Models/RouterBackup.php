<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterBackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'mikrotik_id',
        'backup_path',
        'checksum',
        'file_size',
    ];

    protected $casts = [
        'mikrotik_id' => 'integer',
        'file_size' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
