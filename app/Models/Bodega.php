<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $fillable = [
        'sucursal',
        'denominacion',
        'entregados',
        'existencia'
    ];
}
