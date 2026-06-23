<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Planta extends Model
{
    use HasFactory;

    protected $table = 'plantas';

    protected $fillable = [
        'nombre',
        'numero_planta',
        'grupo_experimental',
        'tipo_cultivo',
        'descripcion',
        'ce_referencia',
        'usuario_id',
    ];

    protected $casts = [
        'ce_referencia' => 'decimal:4',
        'grupo_experimental' => 'string',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class, 'planta_id');
    }

    public function analisis(): HasMany
    {
        return $this->hasMany(AnalisisLixiviacion::class, 'planta_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'planta_id');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(EvaluacionAlerta::class, 'planta_id');
    }

    public function consolidaciones(): HasMany
    {
        return $this->hasMany(ConsolidacionDiaria::class, 'planta_id');
    }
}