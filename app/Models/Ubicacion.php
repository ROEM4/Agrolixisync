<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';

    protected $fillable = [
        'planta_id',
        'nombre',
        'codigo_dispositivo',
        'grupo_experimental',
        'latitud',
        'longitud',
        'activa',
        'configuracion_alertas',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'latitud' => 'float',
        'longitud' => 'float',
        'configuracion_alertas' => 'array',
    ];

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function sensores(): HasMany
    {
        return $this->hasMany(Sensor::class, 'ubicacion_id');
    }

    public function analisis(): HasMany
    {
        return $this->hasMany(AnalisisLixiviacion::class, 'ubicacion_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'ubicacion_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class, 'ubicacion_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function sensoresSuperficiales()
    {
        return $this->sensores()->where('profundidad', 20);
    }

    public function sensoresProfundos()
    {
        return $this->sensores()->where('profundidad', 60);
    }
}