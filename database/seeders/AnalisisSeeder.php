<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Lote;
use App\Models\Location;
use App\Models\PFRecord;
use App\Models\Analysis;
use App\Models\Alert;
use App\Models\Observacion;
use App\Models\DetectionTimeRecord;

class AnalisisSeeder extends Seeder
{
    public function run()
    {
        // Crear/obtener lotes y ubicaciones
        $loteCtrl = Lote::firstOrCreate(
            ['name' => 'Lote 01 - Parcela Control (Tradicional)'],
            ['user_id' => 1]
        );
        $loteExp  = Lote::firstOrCreate(
            ['name' => 'Auto-Esp32G1 - Parcela Experimental (Agrolixisync)'],
            ['user_id' => 1]
        );

        $locCtrl = Location::firstOrCreate([
            'name' => 'Lote 01 - Parcela Control (Tradicional)'
        ], [
            'lote_id' => $loteCtrl->id,
            'experimental_group' => 'control'
        ]);

        $locExp = Location::firstOrCreate([
            'name' => 'Auto-Esp32G1 - Parcela Experimental (Agrolixisync)'
        ], [
            'lote_id' => $loteExp->id,
            'experimental_group' => 'experimental'
        ]);

        $dates = [
            '2026-04-19','2026-04-21','2026-04-23','2026-04-25','2026-04-27','2026-04-29','2026-05-01','2026-05-03','2026-05-07','2026-05-09','2026-05-11','2026-05-13','2026-05-15','2026-05-17','2026-05-19'
        ];

        // Ajust: user listed 14 dates but requested 15 records; include 2026-05-19 as final fallback
        $lossPct = [78,75,82,79,74,81,85,80,73,76,83,74,84,86,82];

        // Crear PFRecord (control) con pérdida % y CE coherente
        foreach ($dates as $i => $d) {
            $pct = $lossPct[$i] ?? 0;
            $ce_ref = 1.0; // referencia
            $ce_measured = round(max(0.01, $ce_ref - ($pct / 100.0)), 4);
            PFRecord::updateOrCreate(
                ['experimental_group' => 'control', 'recorded_at' => Carbon::parse($d)],
                [
                    'location_id' => $locCtrl->id,
                    'experimental_group' => 'control',
                    'recorded_at' => Carbon::parse($d),
                    'ce_superficial' => 1.000,
                    'ce_profunda' => $ce_measured,
                    'ce_reference' => $ce_ref,
                    'ce_measured' => $ce_measured,
                    'subparcela' => 'P' . ($i+1),
                    'pf_percentage' => $pct,
                ]
            );
        }

        // Valores IL para lixiviación (control)
        $ilx_control = [0.98,1,0.97,0.97,0.96,0.98,0.95,1,0.99,1,0.98,1.01,0.96,0.96,1];
        $ilx_experimental = [0.93,0.95,1,0.93,0.95,0.99,0.97,0.92,1,1,0.92,0.94,0.94,0.98,0.93];

        // Tiempos promedios para realtime (segundos)
        $tpd_control = [55,48,52,50,47,53,46,49,54,51,48,50,49,52,50];
        $tpd_experimental = [19,66,36,54,19,59,20,23,32,52,50,36,57,44,37];

        foreach ($dates as $i => $d) {
            // Control Analysis
            $ilx = $ilx_control[$i] ?? 1.0;
            $ce_s = 1.0;
            $ce_p = round($ce_s * $ilx, 4);
            $analysisCtrl = Analysis::updateOrCreate(
                ['location_id' => $locCtrl->id, 'analyzed_at' => Carbon::parse($d)],
                [
                    'lote_id' => $loteCtrl->id,
                    'location_id' => $locCtrl->id,
                    'experimental_group' => 'control',
                    'conductivity_superficial' => $ce_s,
                    'conductivity_profundo' => $ce_p,
                    'delta_conductivity' => round($ce_p - $ce_s, 4),
                    'ilx' => $ilx,
                    'ilx_estado' => ($ilx > 1.05) ? 'LIXIVIACIÓN' : 'NORMAL',
                    'lixiviation_detected' => $ilx > 1.05,
                    'risk_level' => ($ilx > 1.05) ? 'MEDIO' : 'BAJO',
                    'threshold_used' => 1.20,
                    'analyzed_at' => Carbon::parse($d)->hour(9),
                    'event_detected_at' => Carbon::parse($d)->hour(9),
                    'alert_generated_at' => Carbon::parse($d)->hour(9),
                ]
            );

            // Crear alerta para poder calcular DetectionTimeRecord
            $ti = Carbon::parse($d)->hour(8)->minute(0)->second(0);
            $tf = (clone $ti)->addSeconds($tpd_control[$i] ?? 50);
            $alertCtrl = Alert::updateOrCreate(
                ['location_id' => $locCtrl->id, 'tiempo_alerta' => $ti],
                [
                    'lote_id' => $loteCtrl->id,
                    'location_id' => $locCtrl->id,
                    'analysis_id' => $analysisCtrl->id,
                    'type' => 'lixiviacion',
                    'description' => 'Seeded control alert',
                    'severity' => 'MEDIO',
                    'level' => 'medio',
                    'status' => 'RESOLVED',
                    'is_resolved' => true,
                    'resolved_at' => $tf,
                    'tiempo_alerta' => $ti,
                    'tiempo_riesgo' => $tf,
                    'subparcela' => 'P' . ($i+1),
                ]
            );

            // Generar Observacion asociada
            Observacion::updateOrCreate(
                ['location_id' => $locCtrl->id, 'alert_id' => $alertCtrl->id],
                [
                    'location_id' => $locCtrl->id,
                    'experimental_group' => 'control',
                    'alert_id' => $alertCtrl->id,
                    'ce_real' => $ce_s,
                    'diagnostico' => 'LIXIVIACION',
                    'resultado' => 'VP',
                ]
            );

            // Actualizar/crear DetectionTimeRecord para este día y ubicación
            DetectionTimeRecord::updateOrCreate(
                ['fecha' => Carbon::parse($d)->format('Y-m-d'), 'location_id' => $locCtrl->id],
                [
                    'lote_id' => $loteCtrl->id,
                    'tiempo_promedio_segundos' => $tpd_control[$i] ?? 50,
                    'cantidad_eventos' => 1,
                    'suma_tiempos_segundos' => $tpd_control[$i] ?? 50,
                    'tipo_entrada' => 'manual',
                    'subparcela' => 'P' . ($i+1),
                ]
            );

            // Experimental Analysis
            $ilx_e = $ilx_experimental[$i] ?? 1.0;
            $ce_s_e = 1.0;
            $ce_p_e = round($ce_s_e * $ilx_e, 4);
            $analysisExp = Analysis::updateOrCreate(
                ['location_id' => $locExp->id, 'analyzed_at' => Carbon::parse($d)],
                [
                    'lote_id' => $loteExp->id,
                    'location_id' => $locExp->id,
                    'experimental_group' => 'experimental',
                    'conductivity_superficial' => $ce_s_e,
                    'conductivity_profundo' => $ce_p_e,
                    'delta_conductivity' => round($ce_p_e - $ce_s_e, 4),
                    'ilx' => $ilx_e,
                    'ilx_estado' => ($ilx_e > 1.05) ? 'LIXIVIACIÓN' : 'NORMAL',
                    'lixiviation_detected' => $ilx_e > 1.05,
                    'risk_level' => ($ilx_e > 1.05) ? 'MEDIO' : 'BAJO',
                    'threshold_used' => 1.20,
                    'analyzed_at' => Carbon::parse($d)->hour(9),
                    'event_detected_at' => Carbon::parse($d)->hour(9),
                    'alert_generated_at' => Carbon::parse($d)->hour(9),
                ]
            );

            $ti_e = Carbon::parse($d)->hour(8)->minute(30)->second(0);
            $tf_e = (clone $ti_e)->addSeconds($tpd_experimental[$i] ?? 40);

            $alertExp = Alert::updateOrCreate(
                ['location_id' => $locExp->id, 'tiempo_alerta' => $ti_e],
                [
                    'lote_id' => $loteExp->id,
                    'location_id' => $locExp->id,
                    'analysis_id' => $analysisExp->id,
                    'type' => 'lixiviacion',
                    'description' => 'Seeded experimental alert',
                    'severity' => 'MEDIO',
                    'level' => 'medio',
                    'status' => 'RESOLVED',
                    'is_resolved' => true,
                    'resolved_at' => $tf_e,
                    'tiempo_alerta' => $ti_e,
                    'tiempo_riesgo' => $tf_e,
                    'subparcela' => 'P' . ($i+1),
                ]
            );

            // Observaciones experimentales: crear múltiples filas por día para obtener PDS aproximada
            // Mapeo simple para reproducir las porcentajes pedidos
            $pds_map = [
                66.7 => [2,1],
                85.7 => [6,1],
                80 => [4,1],
                71.4 => [5,2],
                77.7 => [7,2],
                83.3 => [5,1],
                100 => [1,0],
                67.7 => [2,1]
            ];

            $pdsList = [66.7,85.7,80,71.4,77.7,83.3,83.3,100,100,67.7,100,66.7,100,85.7,100];
            $target = $pdsList[$i] ?? 100;
            $pair = $pds_map[$target] ?? [1,0];
            $vp = $pair[0];
            $others = $pair[1];

            // Crear VP observaciones
            for ($k=0;$k<$vp;$k++){
                Observacion::create([
                    'location_id' => $locExp->id,
                    'experimental_group' => 'experimental',
                    'alert_id' => $alertExp->id,
                    'ce_real' => $ce_s_e,
                    'diagnostico' => 'LIXIVIACION',
                    'resultado' => 'VP',
                    'created_at' => Carbon::parse($d)->hour(9)->addMinutes($k),
                    'updated_at' => Carbon::parse($d)->hour(9)->addMinutes($k),
                ]);
            }
            // Crear FP/FN observaciones (otros)
            for ($k=0;$k<$others;$k++){
                Observacion::create([
                    'location_id' => $locExp->id,
                    'experimental_group' => 'experimental',
                    'alert_id' => $alertExp->id,
                    'ce_real' => $ce_s_e,
                    'diagnostico' => 'LIXIVIACION',
                    'resultado' => ($target>50 ? 'FP' : 'FN'),
                    'created_at' => Carbon::parse($d)->hour(9)->addMinutes(10+$k),
                    'updated_at' => Carbon::parse($d)->hour(9)->addMinutes(10+$k),
                ]);
            }

            // Crear DetectionTimeRecord para experimental
            DetectionTimeRecord::updateOrCreate(
                ['fecha' => Carbon::parse($d)->format('Y-m-d'), 'location_id' => $locExp->id],
                [
                    'lote_id' => $loteExp->id,
                    'tiempo_promedio_segundos' => $tpd_experimental[$i] ?? 40,
                    'cantidad_eventos' => 1,
                    'suma_tiempos_segundos' => $tpd_experimental[$i] ?? 40,
                    'tipo_entrada' => 'automatico',
                    'subparcela' => 'P' . ($i+1),
                ]
            );
        }
    }
}
