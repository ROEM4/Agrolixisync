<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PFRecord extends Model
{
    use HasFactory;

    protected $table = 'pf_records';

    protected $fillable = [
        'location_id',
        'experimental_group',
        'recorded_at',
        'ce_superficial',
        'ce_profunda',
        'ce_reference',
        'ce_measured',
        'subparcela',
        'pf_percentage',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'ce_reference' => 'float',
        'ce_measured' => 'float',
        'pf_percentage' => 'float',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
