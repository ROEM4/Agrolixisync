<?php

namespace App\Modules\Storage;

use App\Models\Reading;
use App\Services\IoTAutoProvisioningService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SdIngestionService
 *
 * Responsabilidad: procesar archivos CSV provenientes de la microSD del ESP32.
 * Formato esperado: timestamp,sensor_id,depth,humidity,temperature,conductivity
 *
 * Deduplicación: unique(sensor_id, recorded_at) — no inserta si ya existe.
 * Tolerante a errores: continúa procesando aunque una fila falle.
 */
class SdIngestionService
{
    public function __construct(
        private readonly IoTAutoProvisioningService $provisioning
    ) {}

    /**
     * Procesa un archivo CSV desde la ruta del filesystem.
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function processFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['No se pudo abrir el archivo']];
        }

        fgetcsv($handle); // saltar header

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) { $skipped++; continue; }

            [$timestamp, $device_code, $depth, $humidity, $temperature, $conductivity] = $row;

            try {
                $sensors     = $this->provisioning->resolveSensors(trim($device_code));
                $depth_int   = (int) trim($depth);
                $sensor      = $depth_int <= 30 ? $sensors['superficial'] : $sensors['profundo'];
                $recorded_at = Carbon::parse(trim($timestamp));

                if (Reading::where('sensor_id', $sensor->id)->where('recorded_at', $recorded_at)->exists()) {
                    $skipped++;
                    continue;
                }

                Reading::create([
                    'sensor_id'    => $sensor->id,
                    'conductivity' => trim($conductivity),
                    'humidity'     => trim($humidity) ?: null,
                    'temperature'  => trim($temperature) ?: null,
                    'recorded_at'  => $recorded_at,
                ]);
                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Fila {$imported}: " . $e->getMessage();
                Log::warning('SdIngestionService row error', ['error' => $e->getMessage()]);
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 10)];
    }
}
