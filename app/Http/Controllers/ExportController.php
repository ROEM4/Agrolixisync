<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Lote;
use App\Models\Reading;
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
            'sensor_ids.*' => 'integer|exists:sensors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permisos - el usuario puede exportar solo sus sensores
        $user = auth()->user();
        $userSensorIds = Sensor::whereIn('location_id',
            \DB::table('locations')
                ->whereIn('lote_id', $user->lotes()->pluck('id'))
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
            'lote_id' => 'required|integer|exists:lotes,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $lote = Lote::findOrFail($validated['lote_id']);
        if ($lote->user_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = Analysis::where('lote_id', $lote->id)
            ->whereBetween('analyzed_at', [$startDate, $endDate])
            ->with(['lote', 'location', 'sensorSuperficial', 'sensorProfundo'])
            ->get();

        if ($analyses->isEmpty()) {
            return response('No hay datos para exportar', 400);
        }

        $csv = $this->exportService->exportAnalysisToCSV($analyses);

        $filename = 'analisis_' . $lote->name . '_' . now()->format('Y-m-d_His') . '.csv';

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
            'lote_id' => 'required|integer|exists:lotes,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $lote = Lote::findOrFail($validated['lote_id']);
        if ($lote->user_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = Analysis::where('lote_id', $lote->id)
            ->whereBetween('analyzed_at', [$startDate, $endDate])
            ->with(['lote', 'location', 'sensorSuperficial', 'sensorProfundo', 'alerts'])
            ->get();

        if ($analyses->isEmpty()) {
            return response('No hay datos para generar reporte', 400);
        }

        $html = $this->exportService->generateAnalysisReport($analyses, [
            'title' => "Reporte de Lixiviación - {$lote->name}",
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $filename = 'reporte_' . $lote->name . '_' . now()->format('Y-m-d_His') . '.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Descargar reporte como PDF (si tienes DomPDF instalado)
     * Si no tienes instalada la librería de PDF, puede devolver HTML
     */
    public function generateAnalysisPDF(Request $request)
    {
        $validated = $request->validate([
            'lote_id' => 'required|integer|exists:lotes,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permiso
        $lote = Lote::findOrFail($validated['lote_id']);
        if ($lote->user_id !== auth()->id()) {
            return response('No autorizado', 403);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $analyses = Analysis::where('lote_id', $lote->id)
            ->whereBetween('analyzed_at', [$startDate, $endDate])
            ->with(['lote', 'location', 'sensorSuperficial', 'sensorProfundo', 'alerts'])
            ->get();

        if ($analyses->isEmpty()) {
            return response()->json(['error' => 'No hay datos para generar reporte'], 400);
        }

        $html = $this->exportService->generateAnalysisReport($analyses, [
            'title' => "Reporte de Lixiviación - {$lote->name}",
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Si tienes DomPDF instalado, descomenta esto:
        // $pdf = \PDF::loadHTML($html);
        // return $pdf->download('reporte_' . $lote->name . '_' . now()->format('Y-m-d_His') . '.pdf');

        // Por ahora, retornar HTML
        $filename = 'reporte_' . $lote->name . '_' . now()->format('Y-m-d_His') . '.html';

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
            'sensor1_id' => 'required|integer|exists:sensors,id',
            'sensor2_id' => 'required|integer|exists:sensors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Verificar permisos
        $user = auth()->user();
        $userSensorIds = Sensor::whereIn('location_id',
            \DB::table('locations')
                ->whereIn('lote_id', $user->lotes()->pluck('id'))
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

        $filename = 'comparativa_' . $sensor1->code . '_vs_' . $sensor2->code . '_' . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
