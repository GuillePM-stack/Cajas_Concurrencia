<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bodega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\CajaService;

class CajeroController
{
    protected $cajaService;

    public function __construct(CajaService $cajaService)
    {
        $this->cajaService = $cajaService;
    }

    public function registrarCaja($caja)
    {
        $max = Cache::get('max_caja', 0);
        if ($caja > $max) {
            Cache::put('max_caja', $caja);
        }
        return response()->json(['error' => false]);
    }

    public function listarcajas()
    {
        $cajas = Bodega::distinct()->pluck('sucursal')->toArray();

        $max = Cache::get('max_caja', 0);
        if ($max > 0) {
            $cajas = array_merge($cajas, range(1, $max));
        }

        if (empty($cajas)) {
            $cajas = [1];
        }

        $cajas = array_unique($cajas);
        sort($cajas);

        return response()->json(['error' => false, 'data' => $cajas]);
    }

    public function vercaja($caja)
    {
        $bodegas = Bodega::where('sucursal', $caja)
            ->where('denominacion', '>', 0)
            ->orderBy('denominacion', 'desc')
            ->get();

        $abierta = !$bodegas->isEmpty();

        if (!$abierta) {
            $primeraSucursal = Bodega::where('denominacion', '>', 0)->min('sucursal');
            
            if ($primeraSucursal) {
                DB::transaction(function () use ($caja, $primeraSucursal) {
                    $plantilla = Bodega::where('sucursal', $primeraSucursal)->get();
                    foreach ($plantilla as $b) {
                        Bodega::create([
                            'sucursal' => $caja,
                            'denominacion' => $b->denominacion,
                            'entregados' => 0,
                            'existencia' => $b->existencia,
                        ]);
                    }
                });

                $bodegas = Bodega::where('sucursal', $caja)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();
                    
                $abierta = true;
            }
        }

        $sistemaOcupado = Cache::has('sistema_bloqueado');

        return response()->json([
            'error' => false,
            'abierta' => $abierta,
            'bloqueado' => $sistemaOcupado,
            'data' => $bodegas
        ]);
    }

    public function abrircaja($caja)
    {
        $lock = Cache::lock('abriendo_boveda_global', 10);

        if (!$lock->get()) {
            return response()->json(['error' => true, 'mensaje' => "SISTEMA OCUPADO: Se está abriendo otra caja en este momento."], 423);
        }

        Cache::put('sistema_bloqueado', true, 10);

        try {
            return DB::transaction(function () use ($caja) {
                $existeInventario = Bodega::where('sucursal', $caja)
                    ->where('denominacion', '>', 0)
                    ->exists();

                if (!$existeInventario) {
                    sleep(3); 
                    $denominaciones = [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
                    foreach ($denominaciones as $d) {
                        Bodega::create([
                            'sucursal' => $caja,
                            'denominacion' => $d,
                            'entregados' => 0,
                            'existencia' => rand(15, 100)
                        ]);
                    }
                }

                $this->registrarCaja($caja);

                return $this->vercaja($caja);
            });
        } finally {
            Cache::forget('sistema_bloqueado');
            $lock->release();
        }
    }

    public function agregarBilletes(Request $request, $caja)
    {
        $cantidad = $request->input('importe');

        if (!$cantidad || $cantidad <= 0 || !is_numeric($cantidad) || $cantidad != intval($cantidad)) {
            return response()->json(['error' => true, 'mensaje' => 'Cantidad inválida. Debe ser un número entero positivo.'], 400);
        }

        $resultado = $this->cajaService->agregarBilletes($caja, $cantidad);

        $status = $resultado['status'];
        unset($resultado['status']);

        return response()->json($resultado, $status);
    }

    public function cambiarCheque(Request $request, $caja)
    {
        $importe = $request->input('importe');
        
        if (!$importe || $importe <= 0) {
            return response()->json(['error' => true, 'mensaje' => 'Importe inválido'], 400);
        }

        $resultado = $this->cajaService->cambiarCheque($caja, $importe);

        $status = $resultado['status'];
        unset($resultado['status']);

        return response()->json($resultado, $status);
    }
}