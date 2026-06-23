<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'analisis_lixiviacion_id',
        'planta_id',
        'ubicacion_id',
        'subparcela',
        'tipo',
        'severidad',
        'estado',
        'nivel',
        'descripcion',
        'ce_actual',
        'ce_anterior',
        'delta_ce',
        'tiempo_alerta',
        'tiempo_riesgo',
        'tar',
        'resuelta',
        'fecha_resolucion',
        'notas_resolucion',
    ];

    protected $casts = [
        'ce_actual' => 'decimal:3',
        'ce_anterior' => 'decimal:3',
        'delta_ce' => 'decimal:3',
        'fecha_resolucion' => 'datetime',
        'resuelta' => 'boolean',
        'tiempo_alerta' => 'datetime',
        'tiempo_riesgo' => 'datetime',
    ];

    public function analisis(): BelongsTo
    {
        return $this->belongsTo(AnalisisLixiviacion::class, 'analisis_lixiviacion_id');
    }

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(EvaluacionAlerta::class, 'alerta_id');
    }
}