<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SirettItem extends Model
{
    protected $table = 'sirett_items';
    protected $fillable = ['cliente','sku','codigo','sku_key','familia','familia_sirett','descripcion'];
}
