<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
            'cast',
    ];

    public function getValueAttribute($value)
    {
            return match ($this->cast) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute($value)
    {
            $this->attributes['value'] = match ($this->cast) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

        public static function set(string $key, $value, string $cast = 'string'): void
    {
        self::updateOrCreate(
            ['key' => $key],
                ['value' => $value, 'cast' => $cast]
        );
    }
}
