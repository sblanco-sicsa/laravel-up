<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaSincronizada extends Model
{
    protected $table = 'categoria_sincronizadas';

    protected $fillable = [
        'cliente',
        'familia_sirett',
        'familia_sirett_key',
        'nombre',
        'slug',
        'key_normalized',
        'woocommerce_id',
        'woocommerce_parent_id',
        'es_principal',
        'productos_woo',
        'respuesta',
    ];

    protected $casts = [
        'respuesta'     => 'array',
        'es_principal'  => 'boolean',
        'productos_woo' => 'integer',
    ];
}
