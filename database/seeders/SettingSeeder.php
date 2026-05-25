<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'lixiviation_threshold',
                'value' => '100',
                'data_type' => 'decimal',
                'description' => 'Umbral de conductividad para detectar lixiviación (µS/cm)',
                'is_editable' => true,
            ],
            [
                'key' => 'alert_notification_enabled',
                'value' => '1',
                'data_type' => 'boolean',
                'description' => 'Habilitar notificaciones de alertas',
                'is_editable' => true,
            ],
            [
                'key' => 'reading_interval_minutes',
                'value' => '15',
                'data_type' => 'integer',
                'description' => 'Intervalo de tiempo entre lecturas en minutos',
                'is_editable' => true,
            ],
            [
                'key' => 'auto_analysis_enabled',
                'value' => '1',
                'data_type' => 'boolean',
                'description' => 'Ejecutar análisis automático de lixiviación',
                'is_editable' => true,
            ],
            [
                'key' => 'system_version',
                'value' => '2.0.0',
                'data_type' => 'string',
                'description' => 'Versión del sistema',
                'is_editable' => false,
            ],
            [
                'key' => 'last_auto_analysis',
                'value' => '2026-04-16 00:00:00',
                'data_type' => 'string',
                'description' => 'Timestamp del último análisis automático',
                'is_editable' => false,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Configuraciones del sistema creadas/verificadas.');
    }
}
