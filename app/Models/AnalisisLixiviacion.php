<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisisLixiviacion extends Model
{
    use HasFactory;

    protected $table = 'analisis_lixiviacion';

    protected $fillable = [
        'planta_id',
        'ubicacion_id',
        'grupo_experimental',
        'sensor_superficial_id',
        'sensor_profundo_id',
        'lectura_superficial_id',
        'lectura_profundo_id',
        'conductividad_superficial',
        'conductividad_profundo',
        'delta_conductividad',
        'ilx',
        'ilx_estado',
        'umbral_usado',
        'lixiviacion_detectada',
        'nivel_riesgo',
        'porcentaje_riesgo',
        'notas',
        'fecha_analisis',
        'fecha_deteccion',
        'fecha_generacion_alerta',
        'tipo_evento',
        'validado',
        'fecha_validacion',
        'validado_por',
        'nivel_confianza',
        'notas_academicas',
    ];

    protected $casts = [
        'conductividad_superficial' => 'decimal:4',
        'conductividad_profundo' => 'decimal:4',
        'delta_conductividad' => 'decimal:4',
        'ilx' => 'decimal:4',
        'umbral_usado' => 'decimal:4',
        'lixiviacion_detectada' => 'boolean',
        'porcentaje_riesgo' => 'decimal:2',
        'fecha_analisis' => 'datetime',
        'fecha_deteccion' => 'datetime',
        'fecha_generacion_alerta' => 'datetime',
        'validado' => 'boolean',
        'fecha_validacion' => 'datetime',
        'nivel_confianza' => 'integer',
    ];

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function sensorSuperficial(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_superficial_id');
    }

    public function sensorProfundo(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_profundo_id');
    }

    public function lecturaSuperficial(): BelongsTo
    {
        return $this->belongsTo(Lectura::class, 'lectura_superficial_id');
    }

    public function lecturaProfunda(): BelongsTo
    {
        return $this->belongsTo(Lectura::class, 'lectura_profundo_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'analisis_lixiviacion_id');
    }

    public function scopeConLixiviacion($query)
    {
        return $query->where('lixiviacion_detectada', true);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_analisis', [$fechaInicio, $fechaFin]);
    }

    public function scopePorNivelRiesgo($query, $nivel)
    {
        return $query->where('nivel_riesgo', $nivel);
    }

    public function calcularTAR(): ?float
    {
        if (!$this->fecha_deteccion || !$this->fecha_generacion_alerta) {
            return null;
        }
        return $this->fecha_deteccion->diffInMinutes($this->fecha_generacion_alerta);
    }

    public function validar($esCorrecto = true, $validadoPor = 'manual', $notas = null): void
    {
        $this->validado = true;
        $this->fecha_validacion = now();
        $this->validado_por = $validadoPor;

        if ($esCorrecto) {
            $this->nivel_confianza = min(100, $this->nivel_confianza + 10);
        } else {
            $this->nivel_confianza = max(0, $this->nivel_confianza - 10);
        }

        if ($notas) {
            $this->notas_academicas = ($this->notas_academicas ? $this->notas_academicas . "\n" : '') . $notas;
        }

        $this->save();
    }
}