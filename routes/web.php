<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CajeroController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/abrir-caja/{caja}', [CajeroController::class, 'abrircaja']);
Route::post('/agregar-billetes/{caja}', [CajeroController::class, 'agregarBilletes']); 
Route::post('/cambiar-cheque/{caja}', [CajeroController::class, 'cambiarCheque']);
Route::get('/cajas', [CajeroController::class, 'listarcajas']);
Route::get('/caja/{caja}', [CajeroController::class, 'vercaja']);
Route::post('/registrar-caja/{caja}', [CajeroController::class, 'registrarCaja']);