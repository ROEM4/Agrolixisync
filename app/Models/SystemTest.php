<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTest extends Model
{
    use HasFactory;

    protected $table = 'system_tests';

    protected $fillable = [
        'location_id',
        'analysis_id',
        'test_type',
        
        'system_detected_anomaly',
        'system_detection_type',
        'system_detection_time',
        'system_confidence',
        
        'actual_anomaly_existed',
        'actual_anomaly_type',
        'actual_anomaly_time',
        'validated_by',
        'validated_at',
        
        'match_result',
        'system_notes',
        'validation_notes',
        'discrepancy_reason',
        'included_in_pds',
    ];

    protected $casts = [
        'system_detected_anomaly' => 'boolean',
        'actual_anomaly_existed' => 'boolean',
        'system_detection_time' => 'datetime',
        'actual_anomaly_time' => 'datetime',
        'validated_at' => 'datetime',
        'included_in_pds' => 'boolean',
    ];

    /**
     * Relaciones
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /**
     * ¿Es un verdadero positivo? (Sistema detectó, realidad confirmó)
     */
    public function isTruePositive(): bool
    {
        return $this->match_result === 'TRUE_POSITIVE';
    }

    /**
     * ¿Es un verdadero negativo? (Sistema no detectó, realidad confirmó que no existe)
     */
    public function isTrueNegative(): bool
    {
        return $this->match_result === 'TRUE_NEGATIVE';
    }

    /**
     * ¿Es un falso positivo? (Sistema detectó, pero no existe)
     */
    public function isFalsePositive(): bool
    {
        return $this->match_result === 'FALSE_POSITIVE';
    }

    /**
     * ¿Es un falso negativo? (Sistema no detectó, pero existe)
     */
    public function isFalseNegative(): bool
    {
        return $this->match_result === 'FALSE_NEGATIVE';
    }

    /**
     * ¿El diagnóstico fue correcto?
     */
    public function isCorrect(): bool
    {
        return $this->match_result === 'TRUE_POSITIVE' || 
               $this->match_result === 'TRUE_NEGATIVE';
    }

    /**
     * Obtener descripción del resultado
     */
    public function getResultDescription(): string
    {
        return match($this->match_result) {
            'TRUE_POSITIVE' => '✓ Verdadero Positivo',
            'TRUE_NEGATIVE' => '✓ Verdadero Negativo',
            'FALSE_POSITIVE' => '✗ Falso Positivo',
            'FALSE_NEGATIVE' => '✗ Falso Negativo',
            default => 'Desconocido'
        };
    }

    /**
     * Calcular tiempo de respuesta del sistema (en minutos)
     */
    public function getResponseTimeMinutes(): ?float
    {
        if (!$this->system_detection_time || !$this->actual_anomaly_time) {
            return null;
        }

        return $this->system_detection_time->diffInMinutes($this->actual_anomaly_time);
    }

    /**
     * Scopes para consultas comunes
     */
    public function scopeForLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeIncludedInPds($query)
    {
        return $query->where('included_in_pds', true);
    }

    public function scopeCorrect($query)
    {
        return $query->whereIn('match_result', ['TRUE_POSITIVE', 'TRUE_NEGATIVE']);
    }

    public function scopeIncorrect($query)
    {
        return $query->whereIn('match_result', ['FALSE_POSITIVE', 'FALSE_NEGATIVE']);
    }

    public function scopeValidated($query)
    {
        return $query->whereNotNull('validated_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('test_type', $type);
    }
}
