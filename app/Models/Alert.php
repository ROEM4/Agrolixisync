<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'lote_id',
        'location_id',
        'type',
        'severity',
        'status',
        'level',
        'description',
        'is_resolved',
        'resolved_at',
        'resolution_notes',
        'ce_actual',
        'ce_anterior',
        'delta_ce',
        'tiempo_alerta',
        'tiempo_riesgo',
        'tar',
        'notified',
        'notified_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'notified' => 'boolean',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
        'tiempo_alerta' => 'datetime',
        'tiempo_riesgo' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($alert) {
            if ($alert->tiempo_alerta && $alert->tiempo_riesgo) {
                // TAR en segundos
                $alert->tar = $alert->tiempo_riesgo->diffInSeconds($alert->tiempo_alerta);
            }
        });

        static::created(function ($alert) {
            // Solo enviar si es lixiviación (insensible a mayúsculas) y no está resuelto ni es de control
            if (strtolower($alert->type) === 'lixiviacion' && !$alert->is_resolved && $alert->location?->experimental_group !== 'control') {
                try {
                    $telegram = resolve(\App\Services\TelegramService::class);
                    $success = $telegram->sendAlert($alert);
                    
                    if ($success) {
                        $alert->updateQuietly([
                            'notified' => true,
                            'notified_at' => now(),
                        ]);
                        \Illuminate\Support\Facades\Log::info("Notificación de Telegram enviada con éxito para la alerta ID: {$alert->id}");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error al enviar notificación de Telegram: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * Obtener el análisis que generó esta alerta
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /**
     * Obtener el lote
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Obtener la ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Marcar alerta como resuelta
     */
    public function resolve($notes = null)
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        $this->resolution_notes = $notes;
        $this->save();
    }

    /**
     * Scope: obtener alertas no resueltas
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope: obtener alertas de lixiviación
     */
    public function scopeLixiviation($query)
    {
        return $query->where('type', 'lixiviacion');
    }

    /**
     * Scope: obtener alertas por nivel
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: obtener alertas recientes
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
