<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyConsolidation extends Model
{
    protected $table = 'daily_consolidations';

    protected $fillable = [
        'lote_id',
        'consolidation_date',
        'vp',
        'fp',
        'fn',
        'total_evaluations',
        'pds_percentage',
        'error_rate',
        'is_closed',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'consolidation_date' => 'date',
        'vp' => 'integer',
        'fp' => 'integer',
        'fn' => 'integer',
        'total_evaluations' => 'integer',
        'pds_percentage' => 'float',
        'error_rate' => 'float',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}