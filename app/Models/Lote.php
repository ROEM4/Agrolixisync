<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lote extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'user_id', 'crop_type'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtener el usuario propietario de este lote
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtener todas las ubicaciones en este lote
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Obtener todas las lecturas de este lote (a través de sensores)
     */
    public function readings()
    {
        return Reading::whereIn('sensor_id', 
            Sensor::whereIn('location_id', $this->locations()->pluck('id'))->pluck('id')
        );
    }

    /**
     * Obtener todos los análisis de este lote
     */
    public function analysis(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * Obtener todas las alertas de este lote
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Obtener lecturas antiguas (mantener compatibilidad)
     */
    public function getRecentReadings($minutes = 60)
    {
        return $this->readings()->recent($minutes)->get();
    }

    /**
     * Obtener análisis con lixiviación detectada
     */
    public function getLixiviationAnalysis()
    {
        return $this->analysis()->withLixiviation()->get();
    }

    /**
     * Obtener alertas no resueltas
     */
    public function getUnresolvedAlerts()
    {
        return $this->alerts()->unresolved()->get();
    }
}