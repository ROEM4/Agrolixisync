<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    use HasFactory;

    protected $table = 'observaciones';

    protected $fillable = [
        'location_id',
        'experimental_group',
        'alert_id',
        'ce_real',
        'diagnostico',
        'resultado',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
