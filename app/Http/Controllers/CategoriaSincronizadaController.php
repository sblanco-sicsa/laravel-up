<?php

namespace App\Http\Controllers;

use App\Models\CategoriaSincronizada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\ApiConnector;
use Illuminate\Support\Facades\Log;


class CategoriaSincronizadaController extends Controller
{
    // Reutiliza tu helper de credenciales
    protected function credWoo(string $cliente)
    {
        return ApiConnector::getCredentials($cliente, 'woocommerce');
    }

    public function index(string $cliente, Request $request)
    {
        $filtro = $request->get('filtro', 'todas');       // todas | con | sin | eliminables
        $search = trim($request->get('q', ''));
        $soloTop = (int) $request->get('per_page', 0);      // 0 = todos
        $orden = $request->get('orden', 'name');          // name | id | count
        $dir = $request->get('dir', 'asc');             // asc | desc

        $query = CategoriaSincronizada::where('cliente', $cliente);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('familia_sirett', 'like', "%{$search}%")
                    ->orWhere('woocommerce_id', 'like', "%{$search}%");
            });
        }

        // Trae columnas clave
        $rows = $query->get([
            'id',
            'cliente',
            'woocommerce_id',
            'nombre',
            'slug',
            'familia_sirett',
            'familia_sirett_key',
            'key_normalized',
            'woocommerce_parent_id',
            'es_principal',
            'respuesta'
        ])->map(function ($r) {
            // intenta leer count desde respuesta JSON (Woo)
            $resp = is_array($r->respuesta) ? $r->respuesta : (json_decode($r->respuesta ?? '[]', true) ?: []);
            $count = (int) ($resp['count'] ?? 0);

            // "eliminable" = huérfana con match exacto (misma key normalizada)
            $eliminable = ($count === 0)
                && !empty($r->familia_sirett_key)
                && (trim((string) $r->familia_sirett_key) === trim((string) $r->key_normalized));

            return [
                'row_id' => $r->id,
                'woo_id' => (int) $r->woocommerce_id,
                'name' => (string) ($r->nombre ?? ''),
                'slug' => (string) ($r->slug ?? ''),
                'count' => $count,
                'parent' => (int) ($r->woocommerce_parent_id ?? 0),
                'familia' => (string) ($r->familia_sirett ?? ''),
                'fam_key' => (string) ($r->familia_sirett_key ?? ''),
                'name_key' => (string) ($r->key_normalized ?? ''),
                'eliminable' => $eliminable,
                'principal' => (bool) $r->es_principal,
            ];
        });

        // Filtro
        if ($filtro === 'con')
            $rows = $rows->where('count', '>', 0);
        if ($filtro === 'sin')
            $rows = $rows->where('count', 0);
        if ($filtro === 'eliminables')
            $rows = $rows->where('eliminable', true);

        // Orden
        $rows = $rows->sortBy($orden, SORT_REGULAR, strtolower($dir) === 'desc')->values();

        $totales = [
            'todas' => $rows->count(),
            'con' => $rows->where('count', '>', 0)->count(),
            'sin' => $rows->where('count', 0)->count(),
            'eliminables' => $rows->where('eliminable', true)->count(),
        ];

        if ($soloTop > 0) {
            $rows = $rows->take($soloTop)->values();
        }

        return view('categorias_sincronizadas.index', [
            'cliente' => $cliente,
            'rows' => $rows,
            'totales' => $totales,
            'filtro' => $filtro,
            'q' => $search,
            'orden' => $orden,
            'dir' => $dir,
        ]);
    }

    // Elimina una (según reglas de Woo: count==0 y sin hijos)
    // public function deleteOne(string $cliente, int $wooId)
    // {
    //     $cred = $this->credWoo($cliente);
    //     if (!$cred)
    //         return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);

    //     // Ver categoría
    //     $cat = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->get("{$cred->base_url}/products/categories/{$wooId}");
    //     if ($cat->failed())
    //         return response()->json(['ok' => false, 'msg' => 'No se pudo leer categoría', 'det' => $cat->body()], 500);
    //     $catJ = $cat->json();
    //     if ((int) ($catJ['count'] ?? 0) !== 0)
    //         return response()->json(['ok' => false, 'msg' => 'La categoría tiene productos (count>0)'], 422);

    //     // Hijos
    //     $hijos = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->get("{$cred->base_url}/products/categories", ['per_page' => 1, 'parent' => $wooId]);
    //     if ($hijos->successful() && collect($hijos->json())->isNotEmpty()) {
    //         return response()->json(['ok' => false, 'msg' => 'Tiene subcategorías. Elimínalas primero.'], 422);
    //     }

    //     $del = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->delete("{$cred->base_url}/products/categories/{$wooId}", ['force' => true]);

    //     return $del->successful()
    //         ? response()->json(['ok' => true, 'id' => $wooId])
    //         : response()->json(['ok' => false, 'msg' => 'Woo error', 'det' => $del->body()], 500);
    // }


    public function deleteOne(string $cliente, int $wooId)
    {
        $cred = $this->credWoo($cliente);
        if (!$cred) {
            return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);
        }

        // 1) BORRAR EN BD LOCAL PRIMERO
        $local = $this->deleteLocalByWooId($cliente, $wooId, true); // exige productos_woo=0 o null

        // 2) Intentar leer en Woo (si ya no existe, lo consideramos ok)
        $get = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories/{$wooId}");

        if ($get->status() === 404) {
            // En Woo ya no existe. Devolvemos ok porque en BD ya borramos.
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'get'],
                'db_rows_deleted' => $local['deleted'],
                'db_method' => $local['method'],
                'db_ids_deleted' => $local['ids'],
            ]);
        }
        if ($get->failed()) {
            // Otro error real de Woo: informamos pero ya se borró localmente
            return response()->json([
                'ok' => false,
                'msg' => 'No se pudo leer categoría en Woo',
                'det' => $get->body(),
                'db_rows_deleted' => $local['deleted'],
                'db_ids_deleted' => $local['ids'],
            ], 500);
        }

        $cat = $get->json();
        if ((int) ($cat['count'] ?? 0) !== 0) {
            return response()->json([
                'ok' => false,
                'msg' => 'La categoría tiene productos (count>0)',
                'db_rows_deleted' => $local['deleted'],
                'db_ids_deleted' => $local['ids'],
            ], 422);
        }

        // Verifica que no tenga hijos (si los hay, no borramos en Woo)
        $hijos = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories", ['per_page' => 1, 'parent' => $wooId]);

        if ($hijos->successful() && collect($hijos->json())->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Tiene subcategorías en Woo; elimínalas primero.',
                'db_rows_deleted' => $local['deleted'],
                'db_ids_deleted' => $local['ids'],
            ], 422);
        }

        // 3) BORRAR EN WOO
        $del = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->delete("{$cred->base_url}/products/categories/{$wooId}", ['force' => true]);

        if ($del->status() === 404) {
            // Ya no estaba: igual devolvemos ok
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'delete'],
                'db_rows_deleted' => $local['deleted'],
                'db_method' => $local['method'],
                'db_ids_deleted' => $local['ids'],
            ]);
        }

        if ($del->failed()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Woo error al eliminar',
                'det' => $del->body(),
                'db_rows_deleted' => $local['deleted'],
                'db_ids_deleted' => $local['ids'],
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $wooId,
            'woo' => ['status' => 'deleted'],
            'db_rows_deleted' => $local['deleted'],
            'db_method' => $local['method'],
            'db_ids_deleted' => $local['ids'],
        ]);
    }



    // Elimina seleccionadas (IDs Woo recibidos en POST)
    public function deleteSelected(string $cliente, Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty())
            return response()->json(['ok' => false, 'msg' => 'Sin IDs'], 422);

        $ok = [];
        $err = [];
        foreach ($ids as $id) {
            $r = $this->deleteOne($cliente, $id);
            $payload = $r->getData(true);
            if (($payload['ok'] ?? false) === true)
                $ok[] = $id;
            else
                $err[] = ['id' => $id, 'msg' => $payload['msg'] ?? 'error', 'det' => $payload['det'] ?? null];
        }
        return response()->json(['ok' => true, 'eliminadas' => $ok, 'errores' => $err]);
    }

    // Elimina todas huérfanas eliminables (según definición)
    public function deleteAllOrphans(string $cliente)
    {
        $cred = $this->credWoo($cliente);
        if (!$cred)
            return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);

        // Cargar del tracking local
        $rows = CategoriaSincronizada::where('cliente', $cliente)->get();
        $ids = $rows->filter(function ($r) {
            $resp = is_array($r->respuesta) ? $r->respuesta : (json_decode($r->respuesta ?? '[]', true) ?: []);
            $count = (int) ($resp['count'] ?? 0);
            $eliminable = ($count === 0)
                && !empty($r->familia_sirett_key)
                && (trim((string) $r->familia_sirett_key) === trim((string) $r->key_normalized));
            return $eliminable && !empty($r->woocommerce_id);
        })->pluck('woocommerce_id')->values();

        if ($ids->isEmpty())
            return response()->json(['ok' => true, 'eliminadas' => [], 'errores' => [], 'msg' => 'No hay huérfanas eliminables']);

        // Reutiliza flujo de lote
        request()->merge(['ids' => $ids->all()]);
        return $this->deleteSelected($cliente, request());
    }


    private function deleteLocalByWooId(string $cliente, int $wooId, bool $onlyZeroProducts = true): array
    {
        $wooIdStr = trim((string) $wooId);

        $q = CategoriaSincronizada::query()
            ->where('cliente', $cliente)
            // match robusto por si la columna es VARCHAR
            ->where(function ($qq) use ($wooId, $wooIdStr) {
                $qq->where('woocommerce_id', $wooId)
                    ->orWhere('woocommerce_id', $wooIdStr)
                    ->orWhereRaw('CAST(woocommerce_id AS UNSIGNED) = ?', [$wooId]);
            });

        // si quieres exigir “sin productos”, considera null como 0
        if ($onlyZeroProducts) {
            $q->where(function ($qq) {
                $qq->whereNull('productos_woo')
                    ->orWhere('productos_woo', 0);
            });
        }

        // ids PK de tu tabla que se van a borrar
        $ids = $q->pluck('id')->all();
        $deleted = 0;
        if (!empty($ids)) {
            $deleted = CategoriaSincronizada::whereIn('id', $ids)->delete();
        }

        Log::info('catsync.local.deleteByWooId', [
            'cliente' => $cliente,
            'woo_id' => $wooId,
            'ids' => $ids,
            'deleted' => $deleted,
            'onlyZeroProducts' => $onlyZeroProducts,
        ]);

        return ['deleted' => $deleted, 'ids' => $ids, 'method' => 'woocommerce_id'];
    }


// --- helper: token por cliente, leyendo de BD ---
private function getSyncToken(string $cliente): ?string
{
    // 1) priorizamos Sirett (o el que uses para validar el middleware)
    $credSirett = ApiConnector::getCredentials($cliente, 'sirett');
    if ($credSirett && !empty($credSirett->api_token)) {
        return $credSirett->api_token;
    }

    // 2) fallback a Woo si también guarda token
    $credWoo = ApiConnector::getCredentials($cliente, 'woocommerce');
    if ($credWoo && !empty($credWoo->api_token)) {
        return $credWoo->api_token;
    }

    // 3) último recurso (por si lo mantienes para pruebas)
    return config('services.sync.token') ?? env('SYNC_API_TOKEN');
}

// --- acción que dispara la sync desde la UI ---
public function syncNow(string $cliente, Request $request)
{
    $token = $this->getSyncToken($cliente);
    if (!$token) {
        return response()->json(['ok' => false, 'msg' => 'No hay api_token para este cliente'], 403);
    }

    // mismo endpoint que usas en Postman:
    $apiUrl = url("/api/{$cliente}/woocommerce/categories/sync-from-sirett");

    Log::info('catsync.syncNow.call', ['cliente' => $cliente, 'apiUrl' => $apiUrl]);

    $res = Http::withToken($token)
        ->timeout(600)               // ajústalo según dure tu sync
        ->post($apiUrl, []);         // body vacío si tu API no requiere más

    if ($res->failed()) {
        return response()->json([
            'ok'     => false,
            'msg'    => 'Error al ejecutar la sincronización',
            'status' => $res->status(),
            'det'    => $res->body(),
        ], 500);
    }

    return response()->json([
        'ok'  => true,
        'api' => $res->json(),        // devolvemos el resumen de tu API
    ]);
}








}
