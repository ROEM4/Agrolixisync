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
     */
    public function getTiempoInicialAttribute()
    {
        $firstAlert = Alert::where('location_id', $this->location_id)
            ->whereDate('tiempo_alerta', $this->fecha)
            ->whereNotNull('tiempo_alerta')
            ->orderBy('tiempo_alerta', 'asc')
            ->first();
        return $firstAlert ? $firstAlert->tiempo_alerta : $this->fecha;
    }

    /**
     * Get the end time (Tf) for the date.
     */
    public function getTiempoFinalAttribute()
    {
        $lastAlert = Alert::where('location_id', $this->location_id)
            ->whereDate('tiempo_alerta', $this->fecha)
            ->whereNotNull('tiempo_riesgo')
            ->orderBy('tiempo_riesgo', 'desc')
            ->first();
        return $lastAlert ? $lastAlert->tiempo_riesgo : $this->fecha;
    }

    /**
     * Get subparcela name.
     */
    public function getSubparcelaAttribute()
    {
        return $this->location ? $this->location->name : 'N/A';
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
