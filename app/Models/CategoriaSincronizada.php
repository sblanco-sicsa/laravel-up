<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaSincronizada extends Model
{
    protected $table = 'categorias_sincronizadas'; // ✅ Nombre correcto

    protected $fillable = [
        'cliente',
        'nombre',
        'woocommerce_id',
        'respuesta',
    ];

    protected $casts = [
        'respuesta' => 'array',
    ];
}
