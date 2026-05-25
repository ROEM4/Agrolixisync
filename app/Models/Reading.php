<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $table = 'readings';

    protected $fillable = [
        'sensor_id',
        'conductivity',
        'humidity',
        'temperature',
        'recorded_at',
    ];

    protected $casts = [
        'conductivity' => 'string',
        'humidity'     => 'float',
        'temperature'  => 'float',
        'recorded_at'  => 'datetime',
    ];

    /**
     * Obtener el sensor que tomó esta lectura
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Relación para cuando esta lectura se usa en un análisis (como superficial)
     */
    public function analysisAsSuperficial()
    {
        return $this->hasMany(Analysis::class, 'reading_superficial_id');
    }

    /**
     * Relación para cuando esta lectura se usa en un análisis (como profunda)
     */
    public function analysisAsDeep()
    {
        return $this->hasMany(Analysis::class, 'reading_profundo_id');
    }

    /**
     * Scope: obtener lecturas de un rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope: obtener lecturas recientes
     */
    public function scopeRecent($query, $minutes = 60)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope: obtener lecturas de un sensor específico
     */
    public function scopeFromSensor($query, $sensorId)
    {
        return $query->where('sensor_id', $sensorId);
    }
}
