<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class InitialSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Configuraciones iniciales del sistema
        $settings = [
            [
                'key' => 'lixiviation_threshold',
                'value' => '150.0',
                'data_type' => 'decimal',
                'description' => 'Umbral de delta conductividad (µS/cm) para detectar lixiviación',
                'is_editable' => true,
            ],
            [
                'key' => 'humidity_min_alert',
                'value' => '20.0',
                'data_type' => 'decimal',
                'description' => 'Humedad mínima (%) para alerta de riego insuficiente',
                'is_editable' => true,
            ],
            [
                'key' => 'humidity_max_alert',
                'value' => '85.0',
                'data_type' => 'decimal',
                'description' => 'Humedad máxima (%) para alerta de exceso de agua',
                'is_editable' => true,
            ],
            [
                'key' => 'temperature_min_alert',
                'value' => '10.0',
                'data_type' => 'decimal',
                'description' => 'Temperatura mínima (°C) para alerta',
                'is_editable' => true,
            ],
            [
                'key' => 'temperature_max_alert',
                'value' => '35.0',
                'data_type' => 'decimal',
                'description' => 'Temperatura máxima (°C) para alerta',
                'is_editable' => true,
            ],
            [
                'key' => 'risk_level_low_threshold',
                'value' => '50.0',
                'data_type' => 'decimal',
                'description' => 'Porcentaje mínimo para considerar riesgo bajo',
                'is_editable' => true,
            ],
            [
                'key' => 'risk_level_medium_threshold',
                'value' => '75.0',
                'data_type' => 'decimal',
                'description' => 'Porcentaje mínimo para considerar riesgo medio',
                'is_editable' => true,
            ],
            [
                'key' => 'analysis_interval_minutes',
                'value' => '5',
                'data_type' => 'integer',
                'description' => 'Intervalo en minutos entre análisis automáticos',
                'is_editable' => true,
            ],
            [
                'key' => 'enable_auto_analysis',
                'value' => '1',
                'data_type' => 'boolean',
                'description' => 'Habilitar análisis automático de lixiviación',
                'is_editable' => true,
            ],
            [
                'key' => 'enable_alerts',
                'value' => '1',
                'data_type' => 'boolean',
                'description' => 'Habilitar generación de alertas',
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
