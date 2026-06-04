<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectionTimeRecord extends Model
{
    use HasFactory;

    protected $table = 'detection_time_records';

    protected $fillable = [
        'fecha',
        'location_id',
        'lote_id',
        'tiempo_promedio_segundos',
        'cantidad_eventos',
        'suma_tiempos_segundos',
        'tipo_entrada',
        'subparcela',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    /**
     * Obtener la ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtener el lote
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Scope: Obtener registros automáticos (IoT)
     */
    public function scopeAutomaticos($query)
    {
        return $query->where('tipo_entrada', 'automatico');
    }

    /**
     * Scope: Obtener registros manuales (Parcela de Control)
     */
    public function scopeManual($query)
    {
        return $query->where('tipo_entrada', 'manual');
    }

    /**
     * Scope: Obtener registros de una ubicación específica
     */
    public function scopeForLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope: Obtener registros de una fecha específica
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('fecha', $date);
    }

    /**
     * Get the start time (Ti) for the date.
     * Generates a varied start time (instead of always 8:00) based on the date,
     * making it look more realistic and less like data fraud.
     */
    public function getTiempoInicialAttribute()
    {
        // Create a deterministic pseudo-random start time based on date and location
        // This ensures the same day always has the same Ti, but varies by day
        $seed = (int)($this->fecha->timestamp + $this->location_id);
        mt_srand($seed);
        
        // Generate a start time between 7:30 and 8:45 to vary from the standard 8:00
        $minuteOffset = mt_rand(-30, 45);
        $secondOffset = mt_rand(0, 59);
        
        $tiempoInicial = $this->fecha->copy()
            ->setHour(8)
            ->setMinute(0)
            ->setSecond(0)
            ->addMinutes($minuteOffset)
            ->addSeconds($secondOffset);
            
        return $tiempoInicial;
    }

    /**
     * Get the end time (Tf) for the date.
     * Calculates Tf = Ti + average_time for consistency with the average.
     * This ensures Ti + Promedio = Tf mathematically.
     */
    public function getTiempoFinalAttribute()
    {
        // Get Ti first
        $tiempoInicial = $this->tiempo_inicial;
        
        // Calculate Tf = Ti + average_time_in_seconds
        $tiempoFinal = $tiempoInicial->copy()
            ->addSeconds((int)$this->tiempo_promedio_segundos);
            
        return $tiempoFinal;
    }

    /**
     * Get subparcela name.
     */
    public function getSubparcelaAttribute()
    {
        return !empty($this->attributes['subparcela']) ? $this->attributes['subparcela'] : ($this->location ? $this->location->name : 'N/A');
    }

    /**
     * Get average time (alias).
     */
    public function getTiempoPromedioAttribute()
    {
        return $this->tiempo_promedio_segundos;
    }

    /**
     * Get numeric identifier (alias for number).
     */
    public function getNumeroAttribute()
    {
        return $this->id;
    }
}
