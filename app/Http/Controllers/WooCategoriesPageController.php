<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\ApiConnector;

class WooCategoriesPageController extends Controller
{
    // Lee el token desde la BD por cliente (ajusta el "tipo" si en tu tabla es distinto)
    private function bearer(string $cliente): string
    {
        // Ejemplos de dónde podría venir el token:
        // $cred = ApiConnector::getCredentials($cliente, 'api');        // si guardas como 'api'
        // $cred = ApiConnector::getCredentials($cliente, 'auth');       // o 'auth'
        // $cred = ApiConnector::getCredentials($cliente, 'woocommerce'); // si ahí guardas token
        $cred = ApiConnector::getCredentials($cliente, 'api'); // <- ajusta a tu caso real

        abort_if(!$cred, 403, 'Token no configurado');

        // Usa el campo correcto donde guardes el token en tu BD
        $token = $cred->token ?? $cred->password ?? $cred->extra ?? null;
        abort_if(!$token, 403, 'Token vacío');

        return $token;
    }

    private function apiBase(): string
    {
        return url('/api');
    }

    public function index(string $cliente)
    {
        return view('admin.woo-categories.index', [
            'cliente' => $cliente,
        ]);
    }

    public function data(string $cliente)
    {
        $r = Http::withToken($this->bearer($cliente))
            ->acceptJson()
            ->get($this->apiBase()."/{$cliente}/woocommerce/categories");
        return response()->json($r->json(), $r->status());
    }

    public function deleteZeros(string $cliente)
    {
        $r = Http::withToken($this->bearer($cliente))
            ->acceptJson()
            ->delete($this->apiBase()."/{$cliente}/woocommerce/categories/zero");
        return response()->json($r->json(), $r->status());
    }

    public function deleteOne(string $cliente, int $id)
    {
        $r = Http::withToken($this->bearer($cliente))
            ->acceptJson()
            ->delete($this->apiBase()."/{$cliente}/woocommerce/categories/{$id}");
        return response()->json($r->json(), $r->status());
    }

    public function sync(string $cliente)
    {
        $r = Http::withToken($this->bearer($cliente))
            ->acceptJson()
            ->post($this->apiBase()."/{$cliente}/woocommerce/categories/sync-from-sirett", []);
        return response()->json($r->json(), $r->status());
    }
}
