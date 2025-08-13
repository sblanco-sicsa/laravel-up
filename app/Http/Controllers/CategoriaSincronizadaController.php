<?php

namespace App\Http\Controllers;

use App\Models\CategoriaSincronizada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\ApiConnector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


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
    //     if (!$cred) {
    //         return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);
    //     }

    //     // 1) BORRAR EN BD LOCAL PRIMERO
    //     $local = $this->deleteLocalByWooId($cliente, $wooId, true); // exige productos_woo=0 o null

    //     // 2) Intentar leer en Woo (si ya no existe, lo consideramos ok)
    //     $get = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->get("{$cred->base_url}/products/categories/{$wooId}");

    //     if ($get->status() === 404) {
    //         // En Woo ya no existe. Devolvemos ok porque en BD ya borramos.
    //         return response()->json([
    //             'ok' => true,
    //             'id' => $wooId,
    //             'woo' => ['status' => 'not_found', 'stage' => 'get'],
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_method' => $local['method'],
    //             'db_ids_deleted' => $local['ids'],
    //         ]);
    //     }
    //     if ($get->failed()) {
    //         // Otro error real de Woo: informamos pero ya se borró localmente
    //         return response()->json([
    //             'ok' => false,
    //             'msg' => 'No se pudo leer categoría en Woo',
    //             'det' => $get->body(),
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_ids_deleted' => $local['ids'],
    //         ], 500);
    //     }

    //     $cat = $get->json();
    //     if ((int) ($cat['count'] ?? 0) !== 0) {
    //         return response()->json([
    //             'ok' => false,
    //             'msg' => 'La categoría tiene productos (count>0)',
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_ids_deleted' => $local['ids'],
    //         ], 422);
    //     }

    //     // Verifica que no tenga hijos (si los hay, no borramos en Woo)
    //     $hijos = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->get("{$cred->base_url}/products/categories", ['per_page' => 1, 'parent' => $wooId]);

    //     if ($hijos->successful() && collect($hijos->json())->isNotEmpty()) {
    //         return response()->json([
    //             'ok' => false,
    //             'msg' => 'Tiene subcategorías en Woo; elimínalas primero.',
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_ids_deleted' => $local['ids'],
    //         ], 422);
    //     }

    //     // 3) BORRAR EN WOO
    //     $del = Http::withBasicAuth($cred->user, $cred->password)
    //         ->timeout(30)
    //         ->delete("{$cred->base_url}/products/categories/{$wooId}", ['force' => true]);

    //     if ($del->status() === 404) {
    //         // Ya no estaba: igual devolvemos ok
    //         return response()->json([
    //             'ok' => true,
    //             'id' => $wooId,
    //             'woo' => ['status' => 'not_found', 'stage' => 'delete'],
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_method' => $local['method'],
    //             'db_ids_deleted' => $local['ids'],
    //         ]);
    //     }

    //     if ($del->failed()) {
    //         return response()->json([
    //             'ok' => false,
    //             'msg' => 'Woo error al eliminar',
    //             'det' => $del->body(),
    //             'db_rows_deleted' => $local['deleted'],
    //             'db_ids_deleted' => $local['ids'],
    //         ], 500);
    //     }

    //     return response()->json([
    //         'ok' => true,
    //         'id' => $wooId,
    //         'woo' => ['status' => 'deleted'],
    //         'db_rows_deleted' => $local['deleted'],
    //         'db_method' => $local['method'],
    //         'db_ids_deleted' => $local['ids'],
    //     ]);
    // }



    private function wooClient($credWoo)
    {
        $raw = rtrim($credWoo->base_url ?? '', '/');

        // Si ya trae /wp-json, úsalo tal cual:
        if (Str::contains($raw, '/wp-json/')) {
            $base = $raw;
        } else {
            // Si es solo dominio, agrega el path REST:
            $base = $raw . '/wp-json/wc/v3';
        }

        Log::info('wooClient.base', ['base_url_cred' => $credWoo->base_url, 'api_base_resuelto' => $base]);

        return [
            'base' => $base,
            'withAuth' => fn() => Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->acceptJson()->asJson()->retry(3, 2000)->timeout(30),
        ];
    }




    public function deleteOne(string $cliente, int $wooId)
    {
        // 0) Credenciales Woo
        $cred = $this->credWoo($cliente);
        if (!$cred) {
            return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);
        }

        // 1) Verificar que exista en tracking local y que sea eliminable
        $row = CategoriaSincronizada::where('cliente', $cliente)
            ->where('woocommerce_id', $wooId)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'msg' => 'Categoría no encontrada para este cliente'], 404);
        }

        // Regla: sin productos y (si existe) bandera eliminable
        $localCount = (int) ($row->count ?? $row->productos_woo ?? 0);
        $tieneProductosLocal = $localCount > 0;

        // Si tu modelo tiene columna 'eliminable' úsala; si no, solo confía en count==0
        $eliminableFlagExiste = isset($row->eliminable);
        $esEliminable = $eliminableFlagExiste ? (bool) $row->eliminable : ($localCount === 0);

        if ($tieneProductosLocal || !$esEliminable) {
            return response()->json([
                'ok' => false,
                'msg' => 'No permitido: la categoría no es eliminable (tiene productos o no cumple condiciones)',
                'det' => [
                    'local_count' => $localCount,
                    'eliminable' => $eliminableFlagExiste ? (bool) $row->eliminable : null,
                ]
            ], 403);
        }

        // 2) Intentar leer en Woo (si ya no existe, borra local y devuelve ok)
        $get = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories/{$wooId}");

        if ($get->status() === 404) {
            // Ya no existe en Woo: borra local ahora
            $local = $this->deleteLocalByWooId($cliente, $wooId, true); // true => exige productos_woo=0 o null
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'get'],
                'db_rows_deleted' => $local['deleted'] ?? null,
                'db_method' => $local['method'] ?? null,
                'db_ids_deleted' => $local['ids'] ?? [],
            ]);
        }

        if ($get->failed()) {
            // No tocar BD local si Woo falló al leer
            return response()->json([
                'ok' => false,
                'msg' => 'No se pudo leer categoría en Woo',
                'det' => $get->body(),
            ], 500);
        }

        $cat = $get->json();
        $wooCount = (int) ($cat['count'] ?? 0);
        if ($wooCount !== 0) {
            // Si Woo dice que tiene productos, no borrar (aunque local diga 0)
            return response()->json([
                'ok' => false,
                'msg' => 'La categoría tiene productos en Woo (count>0)',
                'det' => ['woo_count' => $wooCount],
            ], 422);
        }

        // 2.1) Verificar que no tenga hijos en Woo
        $hijos = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories", ['per_page' => 1, 'parent' => $wooId]);

        if ($hijos->successful() && collect($hijos->json())->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Tiene subcategorías en Woo; elimínalas primero.',
            ], 422);
        }

        // 3) Borrar en Woo
        $del = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->delete("{$cred->base_url}/products/categories/{$wooId}", ['force' => true]);

        if ($del->status() === 404) {
            // Ya no estaba: procede a borrar local y devolver ok
            $local = $this->deleteLocalByWooId($cliente, $wooId, true);
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'delete'],
                'db_rows_deleted' => $local['deleted'] ?? null,
                'db_method' => $local['method'] ?? null,
                'db_ids_deleted' => $local['ids'] ?? [],
            ]);
        }

        if ($del->failed()) {
            // No borrar local si Woo falló
            return response()->json([
                'ok' => false,
                'msg' => 'Woo error al eliminar',
                'det' => $del->body(),
            ], 500);
        }

        // 4) Woo OK -> ahora sí borra local
        $local = $this->deleteLocalByWooId($cliente, $wooId, true);

        return response()->json([
            'ok' => true,
            'id' => $wooId,
            'woo' => ['status' => 'deleted'],
            'db_rows_deleted' => $local['deleted'] ?? null,
            'db_method' => $local['method'] ?? null,
            'db_ids_deleted' => $local['ids'] ?? [],
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
                'ok' => false,
                'msg' => 'Error al ejecutar la sincronización',
                'status' => $res->status(),
                'det' => $res->body(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'api' => $res->json(),        // devolvemos el resumen de tu API
        ]);
    }


    // Vista árbol
    public function tree(string $cliente)
    {
        return view('categorias_sincronizadas.tree', compact('cliente'));
    }


    // public function apiTree(string $cliente)
    // {
    //     $cats = CategoriaSincronizada::cliente($cliente)
    //         ->orderBy('parent_id')
    //         //->orderBy('orden')
    //         ->orderBy('nombre', 'asc')
    //         ->get(['id', 'nombre', 'parent_id', 'woocommerce_id']);

    //     $data = $cats->map(function ($c) {
    //         $isMaster = is_null($c->parent_id);
    //         return [
    //             'id' => (string) $c->id,
    //             'parent' => $isMaster ? '#' : (string) $c->parent_id,
    //             'text' => $c->nombre,
    //             'type' => $isMaster ? 'master' : 'child',
    //             'li_attr' => [
    //                 'data-wid' => $c->woocommerce_id,
    //                 'class' => $isMaster ? 'is-master' : 'is-child',
    //                 'title' => $isMaster ? 'Categoría master' : 'Categoría hija',
    //             ],
    //         ];
    //     })->values();

    //     return response()->json($data);
    // }






    public function applyManualHierarchyToWoo(string $cliente)
    {
        // 1) Credenciales
        $credWoo = ApiConnector::getCredentials($cliente, 'woocommerce');
        if (!$credWoo) {
            return response()->json(['error' => 'Credenciales WooCommerce no encontradas'], 404);
        }
        $http = $this->wooClient($credWoo);
        $api = $http['base'];
        $auth = $http['withAuth'];

        // 2) Cargar categorías locales (jerarquía manual)
        $cats = CategoriaSincronizada::cliente($cliente)
            ->orderBy('parent_id')->orderBy('orden')->orderBy('nombre')
            ->get(['id', 'nombre', 'slug', 'parent_id', 'orden', 'woocommerce_id']);

        if ($cats->isEmpty()) {
            return response()->json(['error' => 'No hay categorías para procesar'], 422);
        }

        // 3) Mapas rápidos
        $byId = $cats->keyBy('id')->map(fn($c) => $c->toArray())->all();

        // 4) Orden topológico por profundidad (padres antes que hijos)
        $withDepth = $cats->map(function ($c) use ($byId) {
            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'slug' => $this->normalizeSlug($c->slug, $c->nombre),
                'parent_id' => $c->parent_id,
                'orden' => (int) $c->orden,
                'woocommerce_id' => $c->woocommerce_id,
                'depth' => $this->catDepth($byId, $c->id),
            ];
        })->sortBy('depth')->values();

        // 5) Crear en Woo las que no existen (padres primero)
        foreach ($withDepth as $c) {
            if ($c['woocommerce_id'])
                continue;

            // Resolver parent Woo ID (0 si master)
            $parentWoo = 0;
            if ($c['parent_id']) {
                $parentLocal = $byId[$c['parent_id']] ?? null;
                if ($parentLocal && !empty($parentLocal['woocommerce_id'])) {
                    $parentWoo = (int) $parentLocal['woocommerce_id'];
                } else {
                    // Si el padre aún no tiene woo_id (raro por el orden), salta y se intentará luego
                    continue;
                }
            }

            $payload = [
                'name' => $c['nombre'],
                'slug' => $c['slug'],
                'parent' => $parentWoo,
                'menu_order' => (int) $c['orden'],
            ];

            $res = $auth()->post("$api/products/categories", $payload);
            if ($res->failed()) {
                Log::error('Woo create category failed', [
                    'cliente' => $cliente,
                    'payload' => $payload,
                    'resp' => $res->body()
                ]);
                continue;
            }

            $data = $res->json();
            // Actualiza BD local con IDs de Woo
            CategoriaSincronizada::where('id', $c['id'])->update([
                'woocommerce_id' => $data['id'] ?? null,
                'woocommerce_parent_id' => $data['parent'] ?? null,
            ]);

            // Refrescar mapa en memoria
            $byId[$c['id']]['woocommerce_id'] = $data['id'] ?? null;
        }

        // 6) Alinear parent y orden en Woo para todas (incluye ya existentes)
        foreach ($withDepth as $c) {
            $wooId = $byId[$c['id']]['woocommerce_id'] ?? null;
            if (!$wooId)
                continue;

            $parentWoo = 0;
            if ($c['parent_id']) {
                $parentLocal = $byId[$c['parent_id']] ?? null;
                $parentWoo = (int) ($parentLocal['woocommerce_id'] ?? 0);
                if ($parentWoo === 0) {
                    // Si aún no se resolvió, se corregirá en la siguiente ejecución
                    continue;
                }
            }

            $payload = [
                'parent' => $parentWoo,
                'menu_order' => (int) $c['orden'],
            ];

            $res = $auth()->put("$api/products/categories/{$wooId}", $payload);

            if ($res->failed()) {
                Log::error('Woo update category failed', [
                    'cliente' => $cliente,
                    'id' => $wooId,
                    'payload' => $payload,
                    'resp' => $res->body()
                ]);
                continue;
            }

            // Mantener coherencia local del parent_id de Woo (opcional)
            CategoriaSincronizada::where('id', $c['id'])->update([
                'woocommerce_parent_id' => $parentWoo ?: null,
            ]);
        }

        return response()->json(['ok' => true, 'msg' => 'Jerarquía manual aplicada en Woo.']);
    }




    public function applyManualCategoriesToProducts(string $cliente)
    {
        $credWoo = ApiConnector::getCredentials($cliente, 'woocommerce');
        if (!$credWoo)
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);

        $http = $this->wooClient($credWoo);
        $api = $http['base'];
        $auth = $http['withAuth'];

        // 1) Recuperar todos los productos de Woo (paginado)
        $page = 1;
        $totalUpdated = 0;
        do {
            $resp = $auth()->get("$api/products", ['per_page' => 100, 'page' => $page, 'status' => 'publish']);
            if ($resp->failed())
                break;
            $items = $resp->json();
            if (!$items)
                break;

            foreach ($items as $p) {
                $sku = $p['sku'] ?? null;
                if (!$sku)
                    continue;

                // Aquí define tu lookup hacia SiReTT si quieres (por SKU),
                // o si ya guardaste "familia" localmente, úsala.
                $familia = $this->resolverFamiliaDesdeTuBD($cliente, $sku); // <-- implementa según tu caso
                if (!$familia)
                    continue;

                $wooCatId = $this->resolveWooCategoryId($cliente, $familia);
                if (!$wooCatId)
                    continue;

                // Solo actualizar si difiere
                $hasCat = collect($p['categories'] ?? [])->contains(fn($c) => (int) $c['id'] === $wooCatId);
                if ($hasCat)
                    continue;

                $payload = ['categories' => [['id' => $wooCatId]]];
                $u = $auth()->put("$api/products/{$p['id']}", $payload);
                if ($u->ok())
                    $totalUpdated++;
            }

            $page++;
        } while (count($items) === 100);

        return response()->json(['ok' => true, 'updated' => $totalUpdated]);
    }





    private function resolverFamiliaDesdeTuBD(string $cliente, string $sku): ?string
    {
        $sku = $this->skuKey($sku);
        if ($sku === '')
            return null;

        // 1) sirett_items (recomendado)
        if (Schema::hasTable('sirett_items')) {
            // intenta columnas comunes: familia, familia_sirett
            $row = DB::table('sirett_items')
                ->where('cliente', $cliente)
                ->where(function ($q) use ($sku) {
                    $q->where('sku_key', $sku)
                        ->orWhere('codigo', $sku)
                        ->orWhere('sku', $sku);
                })
                ->select('familia', 'familia_sirett')
                ->first();

            if ($row) {
                return $row->familia ?? $row->familia_sirett ?? null;
            }
        }

        // 2) Alternativas: si tuvieras otras tablas (ajusta nombres/columnas a tu caso)
        $alternativas = ['productos_sirett', 'items', 'productos']; // <- cámbialas si usas otras
        foreach ($alternativas as $tabla) {
            if (!Schema::hasTable($tabla))
                continue;

            $row = DB::table($tabla)
                ->where('cliente', $cliente)
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)
                        ->orWhere('codigo', $sku)
                        ->orWhere('sku_key', $sku);
                })
                ->select('familia', 'familia_sirett', 'categoria', 'family')
                ->first();

            if ($row) {
                return $row->familia
                    ?? $row->familia_sirett
                    ?? $row->categoria
                    ?? $row->family
                    ?? null;
            }
        }

        // 3) Fallback: consulta SiReTT y cachea
        $familia = $this->fetchFamiliaFromSirett($cliente, $sku);
        if ($familia) {
            // Cachear en sirett_items para la próxima
            if (Schema::hasTable('sirett_items')) {
                DB::table('sirett_items')->updateOrInsert(
                    ['cliente' => $cliente, 'sku_key' => $sku],
                    ['familia' => $familia, 'updated_at' => now()]
                );
            }
        }

        return $familia ?: null;
    }

    // Normaliza un SKU para comparaciones robustas (lower + sin espacios/guiones)
    private function skuKey(string $raw): string
    {
        $s = Str::of($raw)->lower()->trim()->replace([' ', '-', '_', '.'], '');
        return (string) $s;
    }

    // Buscar en SiReTT y devolver familia (y opcionalmente cachear)
    private function fetchFamiliaFromSirett(string $cliente, string $skuKey): ?string
    {
        try {
            $cred = ApiConnector::getCredentials($cliente, 'sirett');
            if (!$cred)
                return null;

            $client = new \SoapClient($cred->base_url . '?wsdl', ['trace' => 1, 'exceptions' => true]);

            // Nota: si tuvieras un método SiReTT "por código" úsalo; aquí hacemos uno genérico
            $params = [
                'ws_pid' => $cred->user,
                'ws_passwd' => $cred->password,
                'bid' => $cred->extra,  // según tu WSDL
            ];

            // Este método suele traer TODOS los ítems. Si hay uno "por código", cámbialo aquí.
            $response = $client->__soapCall('wsp_request_items', [$params]);

            $arr = json_decode(json_encode($response), true);
            $items = $arr['data'] ?? $arr['items'] ?? [];

            // Busca el SKU/código, pero normalizado
            foreach ($items as $it) {
                $codigo = $this->skuKey((string) ($it['codigo'] ?? $it['sku'] ?? $it['item_code'] ?? ''));
                if ($codigo !== '' && $codigo === $skuKey) {
                    // Campos posibles para familia
                    return (string) ($it['familia'] ?? $it['Familia'] ?? $it['family'] ?? $it['category'] ?? '');
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('fetchFamiliaFromSirett.fail', ['cliente' => $cliente, 'sku' => $skuKey, 'err' => $e->getMessage()]);
            return null;
        }
    }



    private function resolveWooCategoryId(string $cliente, ?string $familia): ?int
    {
        if (!$familia)
            return null;

        // Normaliza igual que tu catKey()
        $key = Str::of($familia)->lower()->slug('-')->toString();

        $cat = CategoriaSincronizada::cliente($cliente)
            ->where('key_normalized', $key)
            ->orWhere('slug', $key)
            ->orWhere('nombre', $familia)
            ->orderByRaw('CASE WHEN woocommerce_id IS NULL THEN 1 ELSE 0 END') // preferir con woo_id
            ->first();

        return $cat?->woocommerce_id ? (int) $cat->woocommerce_id : null;
    }



    private function catDepth(array $byId, $id): int
    {
        $d = 0;
        $lim = 0;
        while ($id && ++$lim < 1000) {
            $p = $byId[$id]['parent_id'] ?? null;
            if (!$p)
                break;
            $id = $p;
            $d++;
        }
        return $d;
    }

    private function normalizeSlug(?string $slug, string $name): string
    {
        $s = $slug ?: Str::slug($name);
        return substr($s, 0, 190);
    }




    // Mover nodo (drag & drop): actualiza parent_id y orden
    // public function apiMove(Request $request, string $cliente)
    // {
    //     $request->validate([
    //         'id' => 'required|integer',
    //         'parent' => 'nullable',
    //         'position' => 'nullable|integer',
    //     ]);

    //     $id = (int) $request->input('id');
    //     $parent = $request->input('parent'); // '#' | id
    //     $position = (int) ($request->input('position') ?? 0);

    //     $node = CategoriaSincronizada::cliente($cliente)->findOrFail($id);
    //     $newParentId = $parent === '#' ? null : (int) $parent;

    //     // Validaciones básicas
    //     if ($newParentId === $id) {
    //         return response()->json(['error' => 'Una categoría no puede ser su propio padre.'], 422);
    //     }

    //     if ($newParentId) {
    //         $parentNode = CategoriaSincronizada::cliente($cliente)->findOrFail($newParentId);

    //         // Evitar ciclos: verificar que newParent no sea descendiente del node
    //         if ($this->isDescendant($node->id, $newParentId, $cliente)) {
    //             return response()->json(['error' => 'No puedes mover una categoría dentro de un descendiente.'], 422);
    //         }
    //     }

    //     DB::transaction(function () use ($node, $newParentId, $position, $cliente) {
    //         // Actualizar parent y provisionalmente el orden deseado
    //         $node->parent_id = $newParentId;
    //         $node->orden = $position;
    //         // Mantener coherencia con es_principal si lo usas
    //         if ($node->isFillable('es_principal')) {
    //             $node->es_principal = $newParentId === null;
    //         }
    //         $node->save();

    //         // Reordenar hermanos de ese parent por 'orden'
    //         $siblings = CategoriaSincronizada::cliente($cliente)
    //             ->where('parent_id', $newParentId)
    //             ->where('id', '!=', $node->id)
    //             ->orderBy('orden')
    //             ->orderBy('nombre')
    //             ->get();

    //         // Reconstruir orden 0..n, insertando $node en $position
    //         $list = $siblings->toArray();
    //         array_splice($list, $position, 0, [$node->toArray()]);
    //         foreach ($list as $idx => $row) {
    //             CategoriaSincronizada::where('id', $row['id'])->update(['orden' => $idx]);
    //         }
    //     });

    //     return response()->json(['ok' => true]);
    // }




    //     public function apiMove(Request $request, string $cliente)
// {
//     $request->validate([
//         'id'       => 'required|integer',
//         'parent'   => 'nullable',
//         'position' => 'nullable|integer',
//     ]);

    //     $id       = (int) $request->input('id');
//     $parent   = $request->input('parent'); // '#' | id | null
//     $position = (int) ($request->input('position') ?? 0);

    //     $node = CategoriaSincronizada::cliente($cliente)->findOrFail($id);
//     $newParentId = ($parent === '#' || $parent === null) ? null : (int) $parent;

    //     if ($newParentId === $id) {
//         return response()->json(['error' => 'Una categoría no puede ser su propio padre.'], 422);
//     }

    //     if ($newParentId) {
//         $parentNode = CategoriaSincronizada::cliente($cliente)->findOrFail($newParentId);
//         if ($this->isDescendant($node->id, $newParentId, $cliente)) {
//             return response()->json(['error' => 'No puedes mover una categoría dentro de un descendiente.'], 422);
//         }
//     }

    //     DB::transaction(function () use ($node, $newParentId, $position, $cliente) {
//         // === Guardar jerarquía manual ===
//         $node->parent_id = $newParentId;
//         $node->orden     = $position;

    //         // (Opcional) coherencia con es_principal si existe
//         if ($node->isFillable('es_principal')) {
//             $node->es_principal = $newParentId === null;
//         }

    //         // (Opcional) si quieres que al ser MASTER guardes 1 en woocommerce_parent_id
//         if (Schema::hasColumn('categoria_sincronizadas','woocommerce_parent_id')) {
//             $node->woocommerce_parent_id = $newParentId ? (int)$newParentId : 1; // <- 1 cuando es master
//         }

    //         $node->save();

    //         // Reordenar hermanos por 'orden'
//         $siblings = CategoriaSincronizada::cliente($cliente)
//             ->where('parent_id', $newParentId)
//             ->where('id', '!=', $node->id)
//             ->orderBy('orden')
//             ->orderBy('nombre')
//             ->get();

    //         $list = $siblings->toArray();
//         array_splice($list, $position, 0, [$node->toArray()]);
//         foreach ($list as $idx => $row) {
//             CategoriaSincronizada::where('id', $row['id'])->update(['orden' => $idx]);
//         }
//     });

    //     return response()->json(['ok' => true]);
// }




    // Resetear jerarquía manual a la de Woo
    public function apiResetToWoo(Request $request, string $cliente)
    {
        DB::transaction(function () use ($cliente) {
            // parent_id = id del que coincide con woocommerce_parent_id
            DB::statement("
                UPDATE categoria_sincronizadas c
                LEFT JOIN categoria_sincronizadas p
                       ON p.woocommerce_id = c.woocommerce_parent_id
                      AND p.cliente = c.cliente
                   SET c.parent_id = p.id,
                       c.orden = 0
                 WHERE c.cliente = ?
            ", [$cliente]);

            // es_principal coherente
            try {
                DB::statement("
                    UPDATE categoria_sincronizadas
                       SET es_principal = IF(parent_id IS NULL, 1, 0)
                     WHERE cliente = ?
                ", [$cliente]);
            } catch (\Throwable $e) {
                // Ignorar si no existe la columna
            }
        });

        return response()->json(['ok' => true]);
    }

    // ---- helper: detectar ciclos ----
    private function isDescendant(int $ancestorId, int $possibleDescendantId, string $cliente): bool
    {
        $current = CategoriaSincronizada::cliente($cliente)->find($possibleDescendantId);
        $limit = 0;
        while ($current && $current->parent_id && $limit < 1000) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }
            $current = CategoriaSincronizada::cliente($cliente)->find($current->parent_id);
            $limit++;
        }
        return false;
    }














    public function apiTree(string $cliente)
    {
        $cats = CategoriaSincronizada::cliente($cliente)
            ->orderBy('parent_id')
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'slug', 'parent_id', 'woocommerce_id']);

        $data = $cats->map(function ($c) {
            $isMaster = is_null($c->parent_id);
            return [
                'id' => (string) $c->id,
                'parent' => $isMaster ? '#' : (string) $c->parent_id,
                'text' => $c->nombre,
                'type' => $isMaster ? 'master' : 'child',
                'li_attr' => [
                    'data-wid' => $c->woocommerce_id,
                    'data-slug' => $c->slug,
                    'class' => $isMaster ? 'is-master' : 'is-child',
                    'title' => $isMaster ? 'Categoría master' : 'Categoría hija',
                ],
            ];
        })->values();

        return response()->json($data);
    }



    private function reindexSiblingsAlphabetically(string $cliente, ?int $parentId): void
    {
        $siblings = CategoriaSincronizada::cliente($cliente)
            ->when(
                is_null($parentId),
                fn($q) => $q->whereNull('parent_id'),
                fn($q) => $q->where('parent_id', $parentId)
            )
            ->orderBy('nombre', 'asc')->orderBy('id', 'asc')
            ->get(['id']);

        foreach ($siblings as $i => $row) {
            CategoriaSincronizada::where('id', $row->id)->update(['orden' => $i]);
        }
    }


public function apiMove(Request $request, string $cliente)
{
    $request->validate([
        'id'       => 'required|integer',
        'parent'   => 'nullable',
        'position' => 'nullable|integer',
    ]);

    $id       = (int) $request->input('id');
    $parent   = $request->input('parent'); // '#', id, o null
    $node     = CategoriaSincronizada::cliente($cliente)->findOrFail($id);

    $oldParentId = $node->parent_id;
    $newParentId = ($parent === '#' || $parent === null) ? null : (int) $parent;

    if ($newParentId === $id) {
        return response()->json(['error' => 'Una categoría no puede ser su propio padre.'], 422);
    }
    if ($newParentId && $this->isDescendant($node->id, $newParentId, $cliente)) {
        return response()->json(['error' => 'No puedes mover una categoría dentro de un descendiente.'], 422);
    }

    DB::transaction(function () use ($node, $oldParentId, $newParentId, $cliente) {
        $node->parent_id = $newParentId;
        $node->orden     = 0;
        if ($node->isFillable('es_principal')) $node->es_principal = $newParentId === null;
        if (Schema::hasColumn('categoria_sincronizadas','woocommerce_parent_id')) {
            $node->woocommerce_parent_id = $newParentId ? (int)$newParentId : 1;
        }
        $node->save();

        $this->reindexSiblingsAlphabetically($cliente, $newParentId);
        if ($oldParentId !== $newParentId) $this->reindexSiblingsAlphabetically($cliente, $oldParentId);
    });

    return response()->json(['ok' => true]);
}


public function apiStore(Request $request, string $cliente)
{
    $data = $request->validate([
        'nombre'    => 'required|string|min:2|max:190',
        'slug'      => 'nullable|string|max:190',
        'parent_id' => 'nullable|integer|exists:categoria_sincronizadas,id',
    ]);

    $nombre    = trim($data['nombre']);
    $slug      = trim((string)($data['slug'] ?? ''));
    $parent_id = $data['parent_id'] ?? null;

    if ($slug === '') $slug = Str::slug($nombre);

    // Crear al final; luego reindexamos A-Z
    $cat = new CategoriaSincronizada();
    $cat->cliente                 = $cliente;
    $cat->nombre                  = $nombre;
    $cat->slug                    = $slug;
    $cat->parent_id               = $parent_id ?: null;
    $cat->orden                   = 0;
    $cat->woocommerce_id          = null;
    $cat->woocommerce_parent_id   = $parent_id ? (int)$parent_id : 1; // opcional: 1 para masters
    if ($cat->isFillable('es_principal')) $cat->es_principal = $parent_id ? 0 : 1;
    $cat->save();

    $this->reindexSiblingsAlphabetically($cliente, $cat->parent_id);

    return response()->json(['ok'=>true, 'id'=>$cat->id]);
}





}
