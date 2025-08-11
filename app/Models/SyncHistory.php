<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncHistory extends Model
{
    protected $fillable = [
        'cliente', 'started_at', 'finished_at',
        'total_creados', 'total_actualizados', 'total_omitidos', 'total_fallidos_categoria'
    ];

    public function detalles()
    {
        return $this->hasMany(SyncHistoryDetail::class);
    }
}
