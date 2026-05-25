<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location_id',
        'depth',
        'is_active',
        'status',
        'notes',
        'last_reading_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'depth'     => 'decimal:2',
        'last_reading_at' => 'datetime',
    ];

    /**
     * Obtener la ubicación del sensor
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtener todas las lecturas de este sensor
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class)->orderByDesc('recorded_at');
    }

    /**
     * Obtener la última lectura
     */
    public function lastReading()
    {
        return $this->hasOne(Reading::class)->latest('recorded_at');
    }

    /**
     * Obtener análisis donde este sensor es el superficial
     */
    public function analysisAsSuperficial(): HasMany
    {
        return $this->hasMany(Analysis::class, 'sensor_superficial_id');
    }

    /**
     * Obtener análisis donde este sensor es el profundo
     */
    public function analysisAsDeep(): HasMany
    {
        return $this->hasMany(Analysis::class, 'sensor_profundo_id');
    }

    /**
     * Determinar si es un sensor superficial
     */
    public function isSuperficial(): bool
    {
        return $this->depth == 0;
    }

    /**
     * Determinar si es un sensor profundo
     */
    public function isDeep(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Obtener las últimas N lecturas
     */
    public function getLatestReadings($limit = 10)
    {
        return $this->readings()->limit($limit)->get();
    }

    /**
     * Obtener lecturas en un rango de fechas
     */
    public function getReadingsBetween($startDate, $endDate)
    {
        return $this->readings()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->get();
    }
}
