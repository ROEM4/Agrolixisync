<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidacionDiaria extends Model
{
    protected $table = 'consolidaciones_diarias';

    protected $fillable = [
        'planta_id',
        'fecha_consolidacion',
        'vp',
        'fp',
        'fn',
        'total_evaluaciones',
        'porcentaje_pds',
        'tasa_error',
        'cerrada',
        'cerrada_por',
        'fecha_cierre',
    ];

    protected $casts = [
        'fecha_consolidacion' => 'date',
        'vp' => 'integer',
        'fp' => 'integer',
        'fn' => 'integer',
        'total_evaluaciones' => 'integer',
        'porcentaje_pds' => 'float',
        'tasa_error' => 'float',
        'cerrada' => 'boolean',
        'fecha_cierre' => 'datetime',
    ];

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }
}