<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    /**
     * Get typed value accessor.
     */
    public function getParsedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Helper to get a setting value with fallback.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->parsed_value : $default;
    }

    /**
     * Helper to set a setting value.
     */
    public static function set(string $key, $value): bool
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return false;
        }

        if (is_bool($value)) {
            $setting->value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $setting->value = json_encode($value);
        } else {
            $setting->value = (string) $value;
        }

        return $setting->save();
    }
}

