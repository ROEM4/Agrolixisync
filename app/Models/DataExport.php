<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExport extends Model
{
    use HasFactory;

    protected $table = 'data_exports';

    protected $fillable = [
        'location_id',
        'export_type',
        'period_start',
        'period_end',
        'filename',
        'filepath',
        'file_size_bytes',
        'record_count',
        'export_status',
        'triggered_by',
        'started_at',
        'completed_at',
        'error_message',
        'storage_location',
        'cloud_url',
        'email_recipient',
        'query_filters',
        'notes',
        'is_backup',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_backup' => 'boolean',
        'query_filters' => 'json',
    ];

    /**
     * Relación: pertenece a una Ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * ¿La exportación está completa?
     */
    public function isCompleted(): bool
    {
        return $this->export_status === 'COMPLETED';
    }

    /**
     * ¿La exportación falló?
     */
    public function hasFailed(): bool
    {
        return $this->export_status === 'FAILED';
    }

    /**
     * Obtener duración de la exportación en segundos
     */
    public function getDurationSeconds(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Obtener tamaño en formato legible
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        $size = $bytes;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Obtener descripción del tipo de exportación
     */
    public function getExportTypeDescription(): string
    {
        return match($this->export_type) {
            'FULL_EXPORT' => 'Exportación Completa',
            'ANALYSIS_EXPORT' => 'Análisis',
            'THESIS_METRICS' => 'Métricas de Tesis',
            'SYSTEM_TESTS' => 'Pruebas del Sistema',
            'PERIODIC_EXPORT' => 'Exportación Periódica',
            default => $this->export_type
        };
    }

    /**
     * Obtener URL para descarga
     */
    public function getDownloadUrl(): string
    {
        return route('exports.download', ['export' => $this->id]);
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('export_status', 'COMPLETED');
    }

    public function scopeFailed($query)
    {
        return $query->where('export_status', 'FAILED');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('completed_at', '>=', now()->subDays($days));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('export_type', $type);
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeBackups($query)
    {
        return $query->where('is_backup', true);
    }

    public function scopeNotBackups($query)
    {
        return $query->where('is_backup', false);
    }
}
