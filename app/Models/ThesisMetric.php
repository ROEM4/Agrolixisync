<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisMetric extends Model
{
    use HasFactory;

    protected $table = 'thesis_metrics';

    protected $fillable = [
        'location_id',
        
        // TAR
        'tar_minutes',
        'tar_sample_count',
        'tar_calculated_at',
        
        // PDS
        'pds_percentage',
        'pds_total_tests',
        'pds_correct_detections',
        'pds_false_positives',
        'pds_false_negatives',
        'pds_calculated_at',
        
        // NCES
        'nces_control_avg',
        'nces_experimental_avg',
        'nces_difference',
        'nces_control_samples',
        'nces_experimental_samples',
        'nces_calculated_at',
        'pf_percentage',
        'pf_reference_ce',
        'pf_measured_ce',
        'pf_calculated_at',
        
        // Período
        'period_start_date',
        'period_end_date',
        
        // Metadatos
        'notes',
        'calculated_by',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'tar_calculated_at' => 'datetime',
        'pds_calculated_at' => 'datetime',
        'nces_calculated_at' => 'datetime',
        'pf_calculated_at' => 'datetime',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Relación: pertenece a una Ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtener TAR formateado
     */
    public function getTarFormatted(): ?string
    {
        if (!$this->tar_minutes) return null;
        
        $minutes = $this->tar_minutes;
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }

    /**
     * Obtener descripción de PDS
     */
    public function getPdsDescription(): string
    {
        $pds = $this->pds_percentage ?? 0;
        
        if ($pds >= 95) return "Excelente (95%+)";
        if ($pds >= 80) return "Muy Bueno (80-95%)";
        if ($pds >= 70) return "Bueno (70-80%)";
        if ($pds >= 60) return "Aceptable (60-70%)";
        return "Necesita mejora (<60%)";
    }

    /**
     * Obtener estado NCES
     */
    public function getNcesStatus(): string
    {
        if (!$this->nces_difference) return "Sin datos";
        
        $diff = $this->nces_difference;
        
        if ($diff > 100) return "Lixiviación severa";
        if ($diff > 50) return "Lixiviación moderada";
        if ($diff > 0) return "Lixiviación leve";
        if ($diff == 0) return "Sin diferencia";
        return "Acumulación";
    }

    /**
     * Scope: Métricas recientes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('period_end_date', '>=', now()->subDays($days));
    }

    /**
     * Scope: Métricas verificadas
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope: Métricas de un mes específico
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('period_start_date', $year)
                    ->whereMonth('period_start_date', $month);
    }
}
