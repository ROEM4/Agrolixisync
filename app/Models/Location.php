<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'lote_id',
        'name',
        'experimental_group',
        'latitude',
        'longitude',
        'is_active',
        'alert_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'alert_settings' => 'array',
    ];

    /**
     * Obtener el lote que contiene esta ubicación
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Obtener todos los sensores en esta ubicación
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * Obtener todos los análisis realizados en esta ubicación
     */
    public function analysis(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * Obtener todas las alertas de esta ubicación
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Obtener sensores superficiales (profundidad = 0)
     */
    public function superficialSensors()
    {
        return $this->sensors()->where('depth', 0);
    }

    /**
     * Obtener sensores profundos (profundidad > 0)
     */
    public function deepSensors()
    {
        return $this->sensors()->where('depth', '>', 0);
    }

    /**
     * Scope: obtener solo ubicaciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
