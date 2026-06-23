<?php

namespace App\Http\Controllers;

use App\Models\AnalisisLixiviacion;
use App\Models\Planta;
use App\Models\Lectura;
use App\Models\Sensor;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ExportController extends Controller
{
    protected DataExportService $exportService;

    public function __construct(DataExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Exportar lecturas a CSV
     */
    public function exportReadingsCSV(Request $request): Response
    {
        $validated = $request->validate([
            'sensor_ids' => 'required|array',
            'sensor_ids.*' => 'integer|exists:sensores,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permisos - el usuario puede exportar solo sus sensores
        $user = auth()->user();
        $userSensorIds = Sensor::whereIn('ubicacion_id',
            \DB::table('ubicaciones')
                ->whereIn('planta_id', $user->plantas()->pluck('id'))
                ->pluck('id')
        )->pluck('id');

        foreach ($validated['sensor_ids'] as $sensorId) {
            if (!$userSensorIds->contains($sensorId)) {
                return response('No autorizado', 403);
            }
        }

        $sensors = Sensor::whereIn('id', $validated['sensor_ids'])->get();
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $csv = $this->exportService->exportReadingsToCSV($sensors, $startDate, $endDate);

        $filename = 'lecturas_' . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Exportar análisis a CSV
     */
    public function exportAnalysisCSV(Request $request): Response
    {
        $validated = $request->validate([
            'lote_id' => 'required|integer|exists:plantas,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $planta = Planta::findOrFail($validated['lote_id']);
        if ($planta->usuario_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->whereBetween('fecha_analisis', [$startDate, $endDate])
            ->with(['planta', 'ubicacion', 'sensorSuperficial', 'sensorProfundo'])
            ->get();

        if ($analyses->isEmpty()) {
            return response('No hay datos para exportar', 400);
        }

        $csv = $this->exportService->exportAnalysisToCSV($analyses);

        $filename = 'analisis_' . $planta->nombre . '_' . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generar reporte HTML de análisis
     */
    public function generateAnalysisReport(Request $request): Response
    {
        $validated = $request->validate([
            'lote_id' => 'required|integer|exists:plantas,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $planta = Planta::findOrFail($validated['lote_id']);
        if ($planta->usuario_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->whereBetween('fecha_analisis', [$startDate, $endDate])
            ->with(['planta', 'ubicacion', 'sensorSuperficial', 'sensorProfundo', 'alertas'])
            ->get();

        if ($analyses->isEmpty()) {
            return response('No hay datos para generar reporte', 400);
        }

        $html = $this->exportService->generateAnalysisReport($analyses, [
            'title' => "Reporte de Lixiviación - {$planta->nombre}",
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $filename = 'reporte_' . $planta->nombre . '_' . now()->format('Y-m-d_His') . '.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Descargar reporte como PDF
     */
    public function generateAnalysisPDF(Request $request)
    {
        $validated = $request->validate([
            'lote_id' => 'required|integer|exists:plantas,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $planta = Planta::findOrFail($validated['lote_id']);
        if ($planta->usuario_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->whereBetween('fecha_analisis', [$startDate, $endDate])
            ->with(['planta', 'ubicacion', 'sensorSuperficial', 'sensorProfundo', 'alertas'])
            ->get();

        if ($analyses->isEmpty()) {
            return response()->json(['error' => 'No hay datos para generar reporte'], 400);
        }

        $html = $this->exportService->generateAnalysisReport($analyses, [
            'title' => "Reporte de Lixiviación - {$planta->nombre}",
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $filename = 'reporte_' . $planta->nombre . '_' . now()->format('Y-m-d_His') . '.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Exportar comparativa de sensores en rango de fechas
     */
    public function exportSensorComparison(Request $request): Response
    {
        $validated = $request->validate([
            'sensor1_id' => 'required|integer|exists:sensores,id',
            'sensor2_id' => 'required|integer|exists:sensores,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permisos
        $user = auth()->user();
        $userSensorIds = Sensor::whereIn('ubicacion_id',
            \DB::table('ubicaciones')
                ->whereIn('planta_id', $user->plantas()->pluck('id'))
                ->pluck('id')
        )->pluck('id');

        if (!$userSensorIds->contains($validated['sensor1_id']) || 
            !$userSensorIds->contains($validated['sensor2_id'])) {
            return response('No autorizado', 403);
        }

        $sensor1 = Sensor::findOrFail($validated['sensor1_id']);
        $sensor2 = Sensor::findOrFail($validated['sensor2_id']);

        $sensors = [$sensor1, $sensor2];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $csv = $this->exportService->exportReadingsToCSV($sensors, $startDate, $endDate);

        $filename = 'comparativa_' . $sensor1->codigo . '_vs_' . $sensor2->codigo . '_' . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
