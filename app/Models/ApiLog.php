<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cliente_nombre',
        'endpoint',
        'method',
        'ip',
        'api_token',
        'fecha',
    ];
}
