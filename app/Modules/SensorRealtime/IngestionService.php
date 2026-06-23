<?php

namespace App\Modules\SensorRealtime;

use App\Models\Lectura;
use App\Services\IoTAutoProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IngestionService
 *
 * Responsabilidad: persistir las lecturas del ESP32 en la tabla lecturas.
 * Maneja idempotencia, transacción y actualización de ultima_lectura.
 * No contiene lógica de análisis (eso es AnalyticsEngine).
 */
class IngestionService
{
    public function __construct(
        private readonly IoTAutoProvisioningService $provisioning
    ) {}

    /**
     * Procesa un payload del ESP32.
     *
     * @return array{status: string, ack: bool, ack_token: string, sup_id?: int, prof_id?: int, location_id?: int}
     */
    public function ingest(SensorPayloadDTO $dto): array
    {
        $sensors     = $this->provisioning->resolveSensors($dto->device);
        $sensor_sup  = $sensors['superficial'];
        $sensor_prof = $sensors['profundo'];

        // Idempotencia: mismo sensor + mismo timestamp = duplicado
        if (Lectura::where('sensor_id', $sensor_sup->id)->where('fecha_registro', $dto->ts)->exists()) {
            Log::info('Duplicate reading, ACK sent', ['device' => $dto->device, 'ts' => $dto->ts]);
            return ['status' => 'duplicate', 'ack' => true, 'ack_token' => 'dup'];
        }

        $readings = DB::transaction(function () use ($sensor_sup, $sensor_prof, $dto) {
            $sup = Lectura::create([
                'sensor_id'     => $sensor_sup->id,
                'conductividad' => $dto->ce_s,
                'humedad'       => $dto->hum_s,
                'temperatura'   => $dto->temp_s,
                'fecha_registro' => $dto->ts,
            ]);
            $prof = Lectura::create([
                'sensor_id'     => $sensor_prof->id,
                'conductividad' => $dto->ce_p,
                'humedad'       => $dto->hum_p,
                'temperatura'   => $dto->temp_p,
                'fecha_registro' => $dto->ts,
            ]);
            $sensor_sup->update(['ultima_lectura'  => now()]);
            $sensor_prof->update(['ultima_lectura' => now()]);
            return ['sup' => $sup, 'prof' => $prof];
        });

        return [
            'status'      => 'success',
            'ack'         => true,
            'ack_token'   => $readings['sup']->id . ':' . $readings['prof']->id,
            'sup_id'      => $readings['sup']->id,
            'prof_id'     => $readings['prof']->id,
            'location_id' => $sensor_sup->ubicacion_id,
        ];
    }
}
