<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lectura extends Model
{
    use HasFactory;

    protected $table = 'lecturas';

    protected $fillable = [
        'sensor_id',
        'conductividad',
        'humedad',
        'temperatura',
        'fecha_registro',
    ];

    protected $casts = [
        'conductividad' => 'decimal:4',
        'humedad' => 'decimal:2',
        'temperatura' => 'decimal:2',
        'fecha_registro' => 'datetime',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_registro', [$fechaInicio, $fechaFin]);
    }

    public function scopeRecientes($query, $minutos = 60)
    {
        return $query->where('fecha_registro', '>=', now()->subMinutes($minutos));
    }
}