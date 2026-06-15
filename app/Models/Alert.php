<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'analysis_id',
        'lote_id',
        'location_id',
        'subparcela',
        'type',
        'severity',
        'status',
        'level',
        'description',
        'ce_actual',
        'ce_anterior',
        'delta_ce',
        'tiempo_alerta',
        'tiempo_riesgo',
        'tar',
        'is_resolved',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'ce_actual'     => 'decimal:3',
        'ce_anterior'   => 'decimal:3',
        'delta_ce'      => 'decimal:3',
        'resolved_at'   => 'datetime',
        'is_resolved'   => 'boolean',
        'tiempo_alerta' => 'datetime',
        'tiempo_riesgo' => 'datetime',
    ];

    // ✅ NUEVA RELACIÓN
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Analysis::class, 'analysis_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(AlertEvaluation::class, 'alert_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}