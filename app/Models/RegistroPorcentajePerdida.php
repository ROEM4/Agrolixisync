<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroPorcentajePerdida extends Model
{
    protected $table = 'registros_porcentaje_perdida';

    protected $fillable = [
        'ubicacion_id',
        'grupo_experimental',
        'fecha_registro',
        'ce_superficial',
        'ce_profunda',
        'ce_referencia',
        'ce_medida',
        'subparcela',
        'porcentaje_pf',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'ce_superficial' => 'float',
        'ce_profunda' => 'float',
        'ce_referencia' => 'float',
        'ce_medida' => 'float',
        'porcentaje_pf' => 'float',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }
}