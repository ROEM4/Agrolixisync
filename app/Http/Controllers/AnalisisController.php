<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\AnalyticsEngine\AnalisisService;
use App\Models\Observacion;
use App\Models\Location;
use Carbon\Carbon;

class AnalisisController extends Controller
{
    private AnalisisService $analisisService;

    public function __construct(AnalisisService $analisisService)
    {
        $this->analisisService = $analisisService;
    }

    public function index(Request $request)
    {
        $locations = Location::with('lote')->get();
        $location_id = $request->query('location_id');
        // Normalize empty string to null so Blade can use is_null($location_id)
        if ($location_id === '') {
            $location_id = null;
        }

        $stats = $this->analisisService->getPdsStats($location_id);
        $comparison = $this->analisisService->getComparisonStats($location_id);

        // Control summary mínimo requerido (provisto por el Service)
        $control = $comparison['control'] ?? ['count' => 0, 'loss_percentage' => 0, 'avg_ce_sup' => 0, 'avg_ce_prof' => 0, 'avg_ilx' => 0];

        // Obtener análisis diario combinado (Control + Experimental)
        $dailyStats = $this->analisisService->getDailyComparisonStats($location_id);

        // Preparar series para los gráficos (arrays simples y JSON strings compatibles con la vista)
        $dates = array_map(function($r) { return $r['date_label']; }, $dailyStats);
        $aciertos = array_map(function($r) { return (($r['experimental']['vp'] ?? 0) + ($r['experimental']['vn'] ?? 0)); }, $dailyStats);
        $errores = array_map(function($r) { return (($r['experimental']['fp'] ?? 0) + ($r['experimental']['fn'] ?? 0)); }, $dailyStats);

        // PDS y tasa de error por día (experimental)
        $pds = array_map(function($r) { return isset($r['experimental']['pds']) ? $r['experimental']['pds'] : 0.0; }, $dailyStats);
        $errorRates = array_map(function($r) { return isset($r['experimental']['error_rate']) ? $r['experimental']['error_rate'] : 0.0; }, $dailyStats);

        // Variables JSON exactamente con los nombres que espera la vista
        $datesJson = json_encode($dates);
        $aciertosJson = json_encode($aciertos);
        $erroresJson = json_encode($errores);
        $pdsJson = json_encode($pds);
        $errorJson = json_encode($errorRates);

        // Parcela Control Records (Física y de Referencia)
        $controlQuery = \App\Models\PFRecord::where('experimental_group', 'control');
        if ($location_id) {
            $controlQuery->where('location_id', $location_id);
        }
        $controlRecords = $controlQuery->orderByDesc('recorded_at')
                            ->paginate(10, ['*'], 'control_page')
                            ->withQueryString();

        // Parcela Experimental Observations (IoT con Matriz de Confusión)
        $expQuery = \App\Models\Observacion::where('experimental_group', 'experimental');
        if ($location_id) {
            $expQuery->where('location_id', $location_id);
        }
        $experimentalRecords = $expQuery->orderByDesc('created_at')
                                ->paginate(10, ['*'], 'exp_page')
                                ->withQueryString();

        // Preparar campos de presentación en backend para evitar cálculos en la vista
        $controlRecords->getCollection()->transform(function ($record) {
            $record->date_label = $record->recorded_at ? $record->recorded_at->format('d/m/Y') : 'N/A';
            $record->ce_superficial_str = number_format($record->ce_superficial ?? 0, 3);
            $record->ce_profunda_str = number_format($record->ce_profunda ?? 0, 3);
            $record->ce_reference_str = number_format($record->ce_reference ?? 0, 4);

            $is_loss = (($record->ce_reference ?? 0) > 1.05) || (($record->ce_profunda ?? 0) > ($record->ce_superficial ?? 0));
            if ($is_loss) {
                $record->condition_label = 'Pérdida';
                $record->condition_badge_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-amber-100 text-amber-800 border border-amber-200 uppercase tracking-wide">Pérdida</span>';
            } else {
                $record->condition_label = 'Óptimo';
                $record->condition_badge_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-slate-100 text-slate-600 border border-slate-200 uppercase tracking-wide">Óptimo</span>';
            }

            return $record;
        });

        $experimentalRecords->getCollection()->transform(function ($obs) {
            $obs->date_label = $obs->created_at ? $obs->created_at->format('d/m/Y') : 'N/A';

            // Detección IoT
            if (in_array($obs->resultado, ['VP', 'FP'])) {
                $obs->detection_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-red-50 text-red-600 border border-red-200">🚨 Lixiviación</span>';
            } else {
                $obs->detection_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-slate-100 text-slate-500 border border-slate-200">Normal</span>';
            }

            // Referencia real
            if (in_array($obs->resultado, ['VP', 'FN'])) {
                $obs->reference_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-200">Lixiviación Real</span>';
            } else {
                $obs->reference_html = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-slate-100 text-slate-500 border border-slate-200">Sin Pérdida Real</span>';
            }

            // Clasificación (pequeña etiqueta)
            $map = [
                'VP' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300">VP</span>',
                'FP' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-red-100 text-red-800 border border-red-300">FP</span>',
                'FN' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300">FN</span>',
                'VN' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-slate-200 text-slate-800 border border-slate-400">VN</span>',
            ];
            $obs->classification_html = $map[$obs->resultado] ?? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-slate-100 text-slate-400">N/A</span>';

            return $obs;
        });

        $selectedLocation = $location_id ? Location::find($location_id) : null;

        return view('dashboard.analisis', compact(
            'locations', 
            'stats', 
            'comparison', 
            'control',
            'dailyStats',
            'dates',
            'aciertos',
            'errores',
            'datesJson',
            'aciertosJson',
            'erroresJson',
            'pdsJson',
            'errorJson',
            'controlRecords', 
            'experimentalRecords', 
            'location_id', 
            'selectedLocation'
        ));
    }

    public function export(Request $request)
    {
        $location_id = $request->query('location_id');
        $query = Observacion::with(['location.lote', 'alert'])->orderByDesc('created_at');
        if ($location_id) {
            $query->where('location_id', $location_id);
        }
        $data = $query->get();

        $filename = 'analisis_pds_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['N', 'Fecha - Hora', 'Ubicacion', 'VP', 'FP', 'PDS_Porcentaje']);

            $total = $data->count();
            foreach ($data as $index => $obs) {
                $is_vp = ($obs->resultado === 'VP') ? 1 : 0;
                $is_fp = ($obs->resultado === 'FP') ? 1 : 0;
                
                // PDS acumulado para el export
                $local_vp = $data->slice(0, $index + 1)->where('resultado', 'VP')->count();
                $running_pds = ($local_vp / ($index + 1)) * 100;

                fputcsv($file, [
                    $total - $index,
                    $obs->created_at->format('Y-m-d H:i:s'),
                    $obs->location->name,
                    $is_vp,
                    $is_fp,
                    number_format($running_pds, 2, '.', '')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
