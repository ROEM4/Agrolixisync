<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\AnalyticsEngine\AnalisisService;
use App\Models\Observacion;
use App\Models\Location;

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

        $stats = $this->analisisService->getPdsStats($location_id);
        $comparison = $this->analisisService->getComparisonStats();

        // Agrupar por día para la ficha académica
        $query = Observacion::query();
        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        $dailyStats = $query->selectRaw('DATE(created_at) as date, 
                                       SUM(CASE WHEN resultado = "VP" THEN 1 ELSE 0 END) as vp,
                                       SUM(CASE WHEN resultado = "FP" THEN 1 ELSE 0 END) as fp,
                                       SUM(CASE WHEN resultado = "FN" THEN 1 ELSE 0 END) as fn')
                            ->groupBy('date')
                            ->orderByDesc('date')
                            ->paginate(15)
                            ->withQueryString();

        $selectedLocation = $location_id ? Location::find($location_id) : null;

        return view('dashboard.analisis', compact('locations', 'stats', 'comparison', 'dailyStats', 'location_id', 'selectedLocation'));
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
