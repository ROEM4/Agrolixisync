<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $alerts = Alert::with(['location.lote', 'analysis'])
            ->orderByDesc('tiempo_alerta')
            ->take($limit)
            ->get();

        $list = $alerts->map(function($a) {
            $tpd = null;
            if ($a->tiempo_alerta && $a->tiempo_riesgo) {
                try { $tpd = abs($a->tiempo_riesgo->diffInSeconds($a->tiempo_alerta)); } catch(\Exception $e) { $tpd = null; }
            }

            return [
                'id' => $a->id,
                'created_at' => $a->created_at ? $a->created_at->toDateTimeString() : null,
                'location' => $a->location ? ['id'=>$a->location->id,'name'=>$a->location->name,'lote'=> $a->location->lote->name ?? null] : null,
                'analysis' => $a->analysis ? ['id'=>$a->analysis->id,'ilx'=>$a->analysis->ilx,'ilx_estado'=>$a->analysis->ilx_estado] : null,
                'type' => $a->type,
                'status' => $a->status,
                'tiempo_alerta' => $a->tiempo_alerta ? $a->tiempo_alerta->toDateTimeString() : null,
                'tiempo_riesgo' => $a->tiempo_riesgo ? $a->tiempo_riesgo->toDateTimeString() : null,
                'tpd_seconds' => $tpd,
                'subparcela' => $a->subparcela,
            ];
        });

        return response()->json(['status'=>'success','count'=>$list->count(),'data'=>$list]);
    }
}
