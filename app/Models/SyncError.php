<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncError extends Model
{
    protected $fillable = [
        'sync_history_id',
        'sku',
        'tipo_error',
        'detalle',
    ];

    public function syncHistory()
    {
        return $this->belongsTo(SyncHistory::class);
    }
}
