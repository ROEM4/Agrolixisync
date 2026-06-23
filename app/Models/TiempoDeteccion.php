<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiempoDeteccion extends Model
{
    use HasFactory;

    protected $table = 'tiempos_deteccion';

    protected $fillable = [
        'fecha',
        'ubicacion_id',
        'subparcela',
        'planta_id',
        'tiempo_promedio_segundos',
        'cantidad_eventos',
        'suma_tiempos_segundos',
        'tipo_entrada',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tiempo_promedio_segundos' => 'integer',
        'cantidad_eventos' => 'integer',
        'suma_tiempos_segundos' => 'integer',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function scopeAutomaticos($query)
    {
        return $query->where('tipo_entrada', 'automatico');
    }

    public function scopeManuales($query)
    {
        return $query->where('tipo_entrada', 'manual');
    }
}