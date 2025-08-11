<?php 

namespace App\Services;

use App\Models\ApiCredential;

class ApiConnector
{
    public static function getCredentials(string $cliente, string $nombre): ?ApiCredential
    {
        return ApiCredential::where('cliente_nombre', $cliente)
            ->where('nombre', $nombre)
            ->first();
    }
}
