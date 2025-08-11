<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncHistoryDetail extends Model
{
    protected $fillable = [
        'sync_history_id', 'sku', 'tipo', 'datos_nuevos', 'datos_anteriores'
    ];

    protected $casts = [
        'datos_nuevos' => 'array',
        'datos_anteriores' => 'array',
    ];

    public function sincronizacion()
    {
        return $this->belongsTo(SyncHistory::class, 'sync_history_id');
    }
}
