<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    use HasFactory;

    protected $table = 'sensores';

    protected $fillable = [
        'codigo',
        'nombre',
        'ubicacion_id',
        'profundidad',
        'tipo_grupo',
        'activo',
        'ultima_lectura',
        'estado',
        'notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'profundidad' => 'decimal:2',
        'ultima_lectura' => 'datetime',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class, 'sensor_id')->orderByDesc('fecha_registro');
    }

    public function ultimaLectura()
    {
        return $this->hasOne(Lectura::class, 'sensor_id')->latest('fecha_registro');
    }

    public function analisisComoSuperficial(): HasMany
    {
        return $this->hasMany(AnalisisLixiviacion::class, 'sensor_superficial_id');
    }

    public function analisisComoProfundo(): HasMany
    {
        return $this->hasMany(AnalisisLixiviacion::class, 'sensor_profundo_id');
    }

    public function esSuperficial(): bool
    {
        return $this->profundidad == 20;
    }

    public function esProfundo(): bool
    {
        return $this->profundidad == 60;
    }
}