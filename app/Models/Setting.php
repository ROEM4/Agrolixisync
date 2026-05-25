<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'data_type',
        'description',
        'is_editable',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    /**
     * Scope: obtener solo configuraciones editables
     */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    /**
     * Obtener valor casteado según su tipo de dato
     */
    public function getCastedValue()
    {
        return match ($this->data_type) {
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            'boolean' => $this->value === '1' || $this->value === 'true',
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Obtener configuración por clave
     */
    public static function getByKey($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->getCastedValue() : $default;
    }

    /**
     * Actualizar configuración por clave
     */
    public static function updateByKey($key, $value)
    {
        $setting = self::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
            return $setting;
        }
        return self::create(['key' => $key, 'value' => $value]);
    }
}
