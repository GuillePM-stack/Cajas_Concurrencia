<?php

namespace App\Services;
use App\Models\Bodega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CajaService
{
    public function agregarBilletes($caja, $cantidad)
    {
        $global = Cache::lock('operando_sistema', 3);
        if (!$global->get()) {
            return ['error' => true, 'status' => 423, 'mensaje' => 'SISTEMA OCUPADO, intenta más tarde.'];
        }

        $lock = Cache::lock("operando_caja_{$caja}", 3);
        if (!$lock->get()) {
            $global->release();
            return ['error' => true, 'status' => 423, 'mensaje' => 'Bodega ocupada, intenta de nuevo.'];
        }

        try {
            sleep(3);

            return DB::transaction(function () use ($cantidad) {
                $bodegas = Bodega::where('sucursal', 1)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                if ($bodegas->isEmpty()) {
                    throw new \Exception("La caja no tiene billetes configurados. Ábrela primero.");
                }

                $restante = $cantidad;
                $detalleFront = [];

                foreach ($bodegas as $b) {
                    if ($restante <= 0) break;
                    
                    $num = intdiv($restante, $b->denominacion);
                    
                    if ($num > 0) {
                        $b->existencia += $num;
                        $b->save();

                        $restante -= $num * $b->denominacion;

                        $detalleFront[] = [
                            'denominacion' => $b->denominacion,
                            'cantidad' => $num
                        ];
                    }
                }

                if ($restante > 0) {
                    throw new \Exception("No se puede desglosar la cantidad con las denominaciones disponibles.");
                }

                $bodegasActualizadas = Bodega::where('sucursal', 1)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                return [
                    'error' => false,
                    'status' => 200,
                    'mensaje' => 'Éxito',
                    'data' => $bodegasActualizadas,
                    'detalle' => $detalleFront
                ];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'status' => 400, 'mensaje' => $e->getMessage()];
        } finally {
            $lock->release();
            $global->release();
        }
    }

    public function cambiarCheque($caja, $importe)
    {
        $global = Cache::lock('operando_sistema', 5);
        if (!$global->get()) {
            return ['error' => true, 'status' => 423, 'mensaje' => 'SISTEMA OCUPADO'];
        }

        $lock = Cache::lock("operando_caja_{$caja}", 3);
        if (!$lock->get()) {
            $global->release();
            return ['error' => true, 'status' => 423, 'mensaje' => 'Bodega ocupada'];
        }

        try {
            sleep(3);

            return DB::transaction(function () use ($importe) {
                $bodegas = Bodega::where('sucursal', 1)
                    ->where('denominacion', '>', 0)
                    ->orderBy('denominacion', 'desc')
                    ->get();

                $restante = (float) $importe;
                $totalEnCaja = 0;

                foreach ($bodegas as $b) {
                    $totalEnCaja += ($b->denominacion * $b->existencia);
                }

                if ($totalEnCaja < $restante) {
                    throw new \Exception("No hay fondos suficientes.");
                }

                $billetesEntregados = [];

                foreach ($bodegas as $b) {
                    if ($restante <= 0) break;

                    $cantidad = min(floor(round($restante, 2) / $b->denominacion), $b->existencia);
                    if ($cantidad > 0) {
                        $b->existencia -= $cantidad;
                        $b->entregados += $cantidad;
                        $b->save();
                        
                        $restante -= $cantidad * $b->denominacion;
                        $restante = round($restante, 2);

                        $billetesEntregados[] = [
                            'denominacion' => $b->denominacion,
                            'cantidad' => $cantidad
                        ];
                    }
                }

                if ($restante > 0) {
                    throw new \Exception("Hay dinero, pero faltan billetes chicos para dar la cantidad exacta.");
                }

                return [
                    'error' => false, 
                    'status' => 200,
                    'mensaje' => 'Éxito', 
                    'data' => $bodegas,
                    'detalle' => $billetesEntregados 
                ];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'status' => 400, 'mensaje' => $e->getMessage()];
        } finally {
            $lock->release();
            $global->release();
        }
    }
}