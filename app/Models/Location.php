<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'lote_id',
        'name',
        'device_code',
        'experimental_group',
        'latitude',
        'longitude',
        'is_active',
        'alert_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'alert_settings' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    public function analysis(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SENSOR HELPERS
    |--------------------------------------------------------------------------
    */

    public function superficialSensors()
    {
        return $this->sensors()->where('depth', 20);
    }

    public function deepSensors()
    {
        return $this->sensors()->where('depth', 60);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | ALERT HELPERS
    |--------------------------------------------------------------------------
    */

    public function alertsEnabled(string $type): bool
    {
        return $this->alert_settings[$type] ?? false;
    }

    public function enableAllAlerts(): void
    {
        $this->alert_settings = [
            'lixiviacion_alta' => true,
            'lixiviacion_media' => true,
            'lixiviacion_baja' => true,
        ];

        $this->save();
    }
}