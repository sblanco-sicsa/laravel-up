<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiCredential;
use App\Models\ApiLog;

class ApiTokenAuth
{

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token de autenticación requerido'], 401);
        }

        $credencial = ApiCredential::where('api_token', $token)->first();

        if (!$credencial) {
            return response()->json(['error' => 'Token inválido'], 403);
        }

        // ✅ Registrar log de acceso
        ApiLog::create([
            'cliente_nombre' => $credencial->cliente_nombre,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'api_token' => $token,
            'fecha' => now(),
        ]);

        // ✅ Hacer disponible el cliente al request (opcional)
        $request->merge(['cliente_autenticado' => $credencial->cliente_nombre]);

        return $next($request);
    }

    // public function handle(Request $request, Closure $next): Response
    // {
    //     $token = $request->bearerToken(); // lee Authorization: Bearer XXX

    //     if (!$token) {
    //         return response()->json(['error' => 'Token de autenticación requerido'], 401);
    //     }

    //     $credencial = ApiCredential::where('api_token', $token)->first();

    //     if (!$credencial) {
    //         return response()->json(['error' => 'Token inválido'], 403);
    //     }

    //     // ✅ Almacenar cliente para usar en otras partes si se desea
    //     $request->merge(['cliente_autenticado' => $credencial->cliente_nombre]);

    //     return $next($request);
    // }

}
