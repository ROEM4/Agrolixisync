<?php

namespace App\Modules\SensorRealtime;

use App\Models\Reading;
use App\Services\IoTAutoProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IngestionService
 *
 * Responsabilidad: persistir las lecturas del ESP32 en la tabla readings.
 * Maneja idempotencia, transacción y actualización de last_reading_at.
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
        if (Reading::where('sensor_id', $sensor_sup->id)->where('recorded_at', $dto->ts)->exists()) {
            Log::info('Duplicate reading, ACK sent', ['device' => $dto->device, 'ts' => $dto->ts]);
            return ['status' => 'duplicate', 'ack' => true, 'ack_token' => 'dup'];
        }

        $readings = DB::transaction(function () use ($sensor_sup, $sensor_prof, $dto) {
            $sup = Reading::create([
                'sensor_id'    => $sensor_sup->id,
                'conductivity' => $dto->ce_s,
                'humidity'     => $dto->hum_s,
                'temperature'  => $dto->temp_s,
                'recorded_at'  => $dto->ts,
            ]);
            $prof = Reading::create([
                'sensor_id'    => $sensor_prof->id,
                'conductivity' => $dto->ce_p,
                'humidity'     => $dto->hum_p,
                'temperature'  => $dto->temp_p,
                'recorded_at'  => $dto->ts,
            ]);
            $sensor_sup->update(['last_reading_at'  => now()]);
            $sensor_prof->update(['last_reading_at' => now()]);
            return ['sup' => $sup, 'prof' => $prof];
        });

        return [
            'status'      => 'success',
            'ack'         => true,
            'ack_token'   => $readings['sup']->id . ':' . $readings['prof']->id,
            'sup_id'      => $readings['sup']->id,
            'prof_id'     => $readings['prof']->id,
            'location_id' => $sensor_sup->location_id,
        ];
    }
}
