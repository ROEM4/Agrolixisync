<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    use HasFactory;

    protected $table = 'lotes';

    protected $fillable = [
        'name',
        'user_id',
        'crop_type',
        'plant_number',
        'experimental_group',
        'description',
        'reference_ce'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AlertEvaluation::class, 'lote_id');
    }

    public function dailyConsolidations(): HasMany
    {
        return $this->hasMany(DailyConsolidation::class, 'lote_id');
    }
}