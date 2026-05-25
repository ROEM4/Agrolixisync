<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorType extends Model
{
    use HasFactory;

    protected $table = 'sensor_types';

    protected $fillable = [
        'name',
        'description',
        'unit',
        'model',
    ];

    /**
     * Obtener todos los sensores de este tipo
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }
}
