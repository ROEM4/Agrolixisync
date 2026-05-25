<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    use HasFactory;

    protected $table = 'analysis';

    protected $fillable = [
        'lote_id',
        'location_id',
        'experimental_group',
        'sensor_superficial_id',
        'sensor_profundo_id',
        'reading_superficial_id',
        'reading_profundo_id',
        'conductivity_superficial',
        'conductivity_profundo',
        'delta_conductivity',
        'ilx',           // ILx = CE_p / CE_s — indicador principal v3
        'ilx_estado',    // Estado agronomico: LIXIVIACIÓN ALTA|LIXIVIACIÓN|EQUILIBRIO|RETENCIÓN|ACUMULACIÓN
        'threshold_used',
        'lixiviation_detected',
        'risk_level',
        'risk_percentage',
        'notes',
        'analyzed_at',
        
        // Campos para métricas de tesis
        'event_detected_at',
        'alert_generated_at',
        'event_type',
        'is_validated',
        'validated_at',
        'validated_by',
        'confidence_level',
        'academic_notes',
    ];

    protected $casts = [
        'conductivity_superficial' => 'decimal:4',
        'conductivity_profundo'    => 'decimal:4',
        'delta_conductivity'       => 'decimal:4',
        'ilx'                      => 'decimal:4',
        'threshold_used'           => 'decimal:4',
        'lixiviation_detected'     => 'boolean',
        'risk_percentage'          => 'decimal:2',
        'analyzed_at'              => 'datetime',
        
        // Casts para tesis
        'event_detected_at' => 'datetime',
        'alert_generated_at' => 'datetime',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
        'confidence_level' => 'integer',
    ];

    /**
     * Obtener el lote
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Obtener la ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtener el sensor superficial
     */
    public function sensorSuperficial(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_superficial_id');
    }

    /**
     * Obtener el sensor profundo
     */
    public function sensorProfundo(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_profundo_id');
    }

    /**
     * Obtener la lectura superficial utilizada
     */
    public function readingSuperficial(): BelongsTo
    {
        return $this->belongsTo(Reading::class, 'reading_superficial_id');
    }

    /**
     * Obtener la lectura profunda utilizada
     */
    public function readingProfundo(): BelongsTo
    {
        return $this->belongsTo(Reading::class, 'reading_profundo_id');
    }

    /**
     * Obtener alertas generadas por este análisis
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Scope: obtener análisis con lixiviación detectada
     */
    public function scopeWithLixiviation($query)
    {
        return $query->where('lixiviation_detected', true);
    }

    /**
     * Scope: obtener análisis por período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('analyzed_at', [$startDate, $endDate]);
    }

    /**
     * Scope: obtener análisis por riesgo
     */
    public function scopeByRiskLevel($query, $level)
    {
        return $query->where('risk_level', $level);
    }

    // ═══════════════════════════════════════════════════════════════
    // MÉTODOS DE TESIS - TAR, VALIDACIÓN, ETC
    // ═══════════════════════════════════════════════════════════════

    /**
     * Registrar timestamps críticos para TAR
     * TAR = Tiempo de Alerta de Riesgo
     */
    public function recordCriticalEvent($eventType = 'LIXIVIATION'): void
    {
        $this->event_detected_at = now();
        $this->event_type = $eventType;
        $this->save();
    }

    /**
     * Registrar momento de generación de alerta
     * Permite calcular TAR = alert_generated_at - event_detected_at
     */
    public function recordAlertGenerated(): void
    {
        $this->alert_generated_at = now();
        $this->save();
    }

    /**
     * Calcular TAR para este análisis (en minutos)
     */
    public function calculateTAR(): ?float
    {
        if (!$this->event_detected_at || !$this->alert_generated_at) {
            return null;
        }

        return $this->event_detected_at->diffInMinutes($this->alert_generated_at);
    }

    /**
     * Validar este análisis contra observación manual
     */
    public function validate($isCorrect = true, $validatedBy = 'manual', $notes = null): void
    {
        $this->is_validated = true;
        $this->validated_at = now();
        $this->validated_by = $validatedBy;
        
        // Si se valida, ajustar confidence level
        if ($isCorrect) {
            $this->confidence_level = min(100, $this->confidence_level + 10);
        } else {
            $this->confidence_level = max(0, $this->confidence_level - 10);
        }

        if ($notes) {
            $this->academic_notes = ($this->academic_notes ? $this->academic_notes . "\n" : '') . $notes;
        }

        $this->save();
    }

    /**
     * Scope: análisis validados
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    /**
     * Scope: análisis no validados
     */
    public function scopeNotValidated($query)
    {
        return $query->where('is_validated', false);
    }

    /**
     * Scope: análisis con evento crítico registrado
     */
    public function scopeWithCriticalEvent($query)
    {
        return $query->whereNotNull('event_detected_at');
    }

    /**
     * Scope: análisis con alerta registrada
     */
    public function scopeWithAlertGenerated($query)
    {
        return $query->whereNotNull('alert_generated_at');
    }

    /**
     * Obtener descripción del tipo de evento
     */
    public function getEventTypeDescription(): string
    {
        return match($this->event_type) {
            'LIXIVIATION' => 'Lixiviación Detectada',
            'NUTRIENT_EXCESS' => 'Exceso de Nutrientes',
            'pH_ANOMALY' => 'Anomalía de pH',
            default => $this->event_type ?? 'Sin tipo'
        };
    }
}
