<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaSincronizada extends Model
{

    protected $table = 'categoria_sincronizadas';

    protected $fillable = [
        'cliente',
        'key_normalized',
        'familia_sirett',
        'nombre',
        'slug',
        'woocommerce_id',
        'woocommerce_parent_id',
        'parent_id',     // <— nuevo
        'orden',         // <— nuevo
        'es_principal',
        'respuesta',
    ];

    protected $casts = [
        'respuesta' => 'array',
        'es_principal' => 'boolean',
        'orden' => 'integer',
    ];

    // Relaciones jerárquicas
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('nombre');
    }

    // Scopes útiles
    public function scopeCliente($q, string $cliente)
    {
        return $q->where('cliente', $cliente);
    }

    public function getEsMasterAttribute(): bool
    {
        return is_null($this->parent_id);
    }
}




