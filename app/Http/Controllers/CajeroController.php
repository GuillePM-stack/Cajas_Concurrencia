<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bodega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CajeroController extends Controller
{
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

        $global = Cache::lock('operando_sistema', 5);
        if (!$global->get()) {
            return response()->json(['error' => true, 'mensaje' => 'SISTEMA OCUPADO, intenta más tarde.'], 423);
        }

        $lock = Cache::lock("operando_caja_{$caja}", 30);
        if (!$lock->get()) {
            $global->release();
            return response()->json(['error' => true, 'mensaje' => "Bodega ocupada, intenta de nuevo."], 423);
        }

        try {
            sleep(3);

            return DB::transaction(function () use ($cantidad, $caja) {
                $bodegas = Bodega::where('sucursal', $caja)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                if ($bodegas->isEmpty()) {
                    throw new \Exception("La caja no tiene billetes configurados. Ábrela primero.");
                }

                $restante = $cantidad;
                $billetesAAgregar = [];

                foreach ($bodegas as $b) {
                    $billetesAAgregar[$b->denominacion] = 0;
                }

                foreach ($bodegas as $b) {
                    if ($restante <= 0) break;
                    $num = intdiv($restante, $b->denominacion);
                    if ($num > 0) {
                        $billetesAAgregar[$b->denominacion] = $num;
                        $restante -= $num * $b->denominacion;
                    }
                }

                if ($restante > 0) {
                    throw new \Exception("No se puede desglosar la cantidad con las denominaciones disponibles.");
                }

                foreach ($bodegas as $b) {
                    $cantidadAgregar = $billetesAAgregar[$b->denominacion];
                    if ($cantidadAgregar > 0) {
                        $b->existencia += $cantidadAgregar;
                        $b->save();
                    }
                }

                $bodegasActualizadas = Bodega::where('sucursal', $caja)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                $detalle = [];
                foreach ($billetesAAgregar as $denom => $num) {
                    if ($num > 0) {
                        $detalle[] = "{$num} de \${$denom}";
                    }
                }
                $mensaje = "Se agregaron: " . implode(', ', $detalle);

                return response()->json(['error' => false, 'mensaje' => $mensaje, 'data' => $bodegasActualizadas]);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'mensaje' => $e->getMessage()], 400);
        } finally {
            $lock->release();
            $global->release();
        }
    }

    public function cambiarCheque(Request $request, $caja)
    {
        $importe = $request->input('importe');
        if (!$importe || $importe <= 0) {
            return response()->json(['error' => true, 'mensaje' => 'Importe inválido'], 400);
        }

        $global = Cache::lock('operando_sistema', 5);
        if (!$global->get()) {
            return response()->json(['error' => true, 'mensaje' => 'SISTEMA OCUPADO'], 423);
        }

        $lock = Cache::lock("operando_caja_{$caja}", 30);
        if (!$lock->get()) {
            $global->release();
            return response()->json(['error' => true, 'mensaje' => "Bodega ocupada"], 423);
        }

        try {
            sleep(3);

            return DB::transaction(function () use ($importe, $caja) {
                $bodegas = Bodega::where('sucursal', $caja)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                $restante = (float) $importe;
                $totalEnCaja = 0;

                foreach ($bodegas as $b) {
                    $totalEnCaja += ($b->denominacion * $b->existencia);
                }

                if ($totalEnCaja < $restante) {
                    throw new \Exception("La caja no tiene fondos suficientes en total.");
                }

                foreach ($bodegas as $b) {
                    if ($restante <= 0) break;

                    $cantidad = min(floor(round($restante, 2) / $b->denominacion), $b->existencia);
                    if ($cantidad > 0) {
                        $b->existencia -= $cantidad;
                        $b->entregados += $cantidad;
                        $b->save();
                        $restante -= $cantidad * $b->denominacion;
                        $restante = round($restante, 2);
                    }
                }

                if ($restante > 0) {
                    throw new \Exception("Hay dinero, pero faltan billetes chicos para dar la cantidad exacta.");
                }

                return response()->json(['error' => false, 'mensaje' => "Éxito", 'data' => $bodegas]);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'mensaje' => $e->getMessage()], 400);
        } finally {
            $lock->release();
            $global->release();
        }
    }
}
