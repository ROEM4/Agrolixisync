<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorGroup extends Model
{
    use HasFactory;

    protected $table = 'sensor_groups';

    protected $fillable = [
        'sensor_id',
        'group_type',
        'group_name',
        'description',
        'start_date',
        'end_date',
        'treatment_applied',
        'treatment_type',
        'researcher_name',
        'researcher_institution',
        'thesis_title',
        'is_active',
        'academic_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relaciones
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * ¿Es grupo de control?
     */
    public function isControl(): bool
    {
        return $this->group_type === 'CONTROL';
    }

    /**
     * ¿Es grupo experimental?
     */
    public function isExperimental(): bool
    {
        return $this->group_type === 'EXPERIMENTAL';
    }

    /**
     * ¿Es grupo de referencia?
     */
    public function isReference(): bool
    {
        return $this->group_type === 'REFERENCE';
    }

    /**
     * Obtener período activo en años
     */
    public function getDurationDays(): int
    {
        $end = $this->end_date ?? now()->toDateString();
        return $this->start_date->diffInDays($end);
    }

    /**
     * Obtener nombre completo del grupo
     */
    public function getFullName(): string
    {
        return "{$this->group_name} ({$this->group_type})";
    }

    /**
     * Obtener información de investigador
     */
    public function getResearcherInfo(): string
    {
        $parts = [];
        if ($this->researcher_name) $parts[] = $this->researcher_name;
        if ($this->researcher_institution) $parts[] = $this->researcher_institution;
        return implode(' - ', $parts) ?: 'No especificado';
    }

    /**
     * Scopes
     */
    public function scopeControl($query)
    {
        return $query->where('group_type', 'CONTROL');
    }

    public function scopeExperimental($query)
    {
        return $query->where('group_type', 'EXPERIMENTAL');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeCurrent($query)
    {
        return $query->active()
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    public function scopeByResearcher($query, $researcher)
    {
        return $query->where('researcher_name', 'like', "%{$researcher}%");
    }

    public function scopeByThesis($query, $thesis)
    {
        return $query->where('thesis_title', 'like', "%{$thesis}%");
    }
}
