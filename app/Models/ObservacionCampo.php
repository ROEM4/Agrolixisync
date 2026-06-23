<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionCampo extends Model
{
    use HasFactory;

    protected $table = 'observaciones_campo';

    protected $fillable = [
        'ubicacion_id',
        'grupo_experimental',
        'alerta_id',
        'ce_real',
        'diagnostico',
        'resultado',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(Alerta::class, 'alerta_id');
    }
}