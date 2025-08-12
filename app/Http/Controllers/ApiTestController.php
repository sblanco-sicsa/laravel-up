<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\ApiConnector;
use App\Models\CategoriaSincronizada;
use App\Models\SyncHistory;
use App\Models\SyncHistoryDetail;
use App\Models\SyncError;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;





class ApiTestController extends Controller
{



    // public function syncSirettCategoriesToWoo(string $clienteNombre, Request $request)
    // {
    //     $RENAME_EXISTING = true;

    //     // === Zona horaria a nivel de sesión MySQL (timestamps por defecto) ===
    //     try {
    //         DB::statement("SET time_zone = '-06:00'");
    //         Log::info('MySQL time_zone establecido', ['tz' => '-06:00', 'cliente' => $clienteNombre]);
    //     } catch (\Throwable $e) {
    //         // Si el hosting no permite SET time_zone, igual seguimos usando now('America/Managua')
    //         Log::warning('No se pudo establecer time_zone de la sesión MySQL', ['error' => $e->getMessage(), 'cliente' => $clienteNombre]);
    //     }

    //     // Límite de muestras que enviaremos a los logs para no saturar
    //     $LOG_SAMPLE_LIMIT = 60;

    //     $inicio = now('America/Managua');
    //     $sync = SyncHistory::create([
    //         'cliente' => $clienteNombre,
    //         'started_at' => $inicio,
    //     ]);

    //     try {
    //         // === 0) Credenciales ===
    //         $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
    //         $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

    //         Log::info('Credenciales detectadas', [
    //             'cliente' => $clienteNombre,
    //             'woo_base_url' => $credWoo->base_url ?? null,
    //             'sirett_base_url' => $credSirett->base_url ?? null,
    //             // No loguear usuario/password por seguridad
    //         ]);

    //         if (!$credWoo || !$credSirett) {
    //             Log::error('Credenciales faltantes para WooCommerce o SiReTT', ['cliente' => $clienteNombre]);
    //             return response()->json(['error' => 'Credenciales no encontradas para WooCommerce o SiReTT'], 404);
    //         }

    //         // === 1) SiReTT -> ITEMS y familias únicas ===
    //         $items = [];
    //         try {
    //             $wsdl = $credSirett->base_url . '?wsdl';
    //             Log::info('Conectando a SiReTT SOAP', ['wsdl' => $wsdl, 'cliente' => $clienteNombre]);

    //             $t0 = microtime(true);
    //             $client = new \SoapClient($wsdl, [
    //                 'trace' => 1,
    //                 'exceptions' => true,
    //                 'connection_timeout' => 30
    //             ]);

    //             // Opcional: log funciones y tipos del WSDL (muestras)
    //             try {
    //                 $funcs = $client->__getFunctions();
    //                 $types = $client->__getTypes();
    //                 Log::debug('SiReTT WSDL funciones/tipos', [
    //                     'functions_total' => is_array($funcs) ? count($funcs) : 0,
    //                     'functions_sample' => is_array($funcs) ? array_slice($funcs, 0, min(10, count($funcs))) : [],
    //                     'types_total' => is_array($types) ? count($types) : 0,
    //                     'types_sample' => is_array($types) ? array_slice($types, 0, min(10, count($types))) : [],
    //                 ]);
    //             } catch (\Throwable $e) {
    //                 Log::debug('No fue posible listar funciones/tipos del WSDL', ['error' => $e->getMessage()]);
    //             }

    //             $params = [
    //                 'ws_pid' => $credSirett->user,
    //                 'ws_passwd' => $credSirett->password,
    //                 'bid' => $credSirett->extra
    //             ];
    //             // No log password; solo indicar presencia
    //             Log::info('Invocando wsp_request_items a SiReTT', [
    //                 'cliente' => $clienteNombre,
    //                 'bid' => $credSirett->extra ?? null,
    //                 'user_set' => !empty($credSirett->user),
    //                 'pass_set' => !empty($credSirett->password),
    //             ]);

    //             $respWs = $client->__soapCall('wsp_request_items', [$params]);
    //             $elapsed = (microtime(true) - $t0) * 1000.0;

    //             // Log request/response size (no contenido completo para evitar ruido)
    //             try {
    //                 $lastReq = $client->__getLastRequest();
    //                 $lastResp = $client->__getLastResponse();
    //                 Log::debug('SiReTT SOAP trafico', [
    //                     'request_bytes' => is_string($lastReq) ? strlen($lastReq) : null,
    //                     'response_bytes' => is_string($lastResp) ? strlen($lastResp) : null,
    //                     'elapsed_ms' => round($elapsed, 2),
    //                 ]);
    //             } catch (\Throwable $e) {
    //                 Log::debug('No se pudo obtener lastRequest/lastResponse', ['error' => $e->getMessage()]);
    //             }

    //             $arr = json_decode(json_encode($respWs), true);
    //             $items = $arr['data'] ?? [];
    //             Log::info('SiReTT respuesta procesada', [
    //                 'cliente' => $clienteNombre,
    //                 'items_total' => is_array($items) ? count($items) : 0,
    //                 'elapsed_ms' => round($elapsed, 2),
    //                 'has_data_key' => array_key_exists('data', $arr),
    //                 'root_keys' => array_keys($arr),
    //             ]);

    //             // Muestras de items sin saturar logs
    //             $picker = function (array $row) {
    //                 $keys = ['familia', 'family', 'sku', 'codigo', 'item_code', 'id', 'descripcion', 'nombre', 'name', 'precio', 'price', 'unidad', 'brand', 'marca'];
    //                 $out = [];
    //                 foreach ($keys as $k) {
    //                     if (array_key_exists($k, $row))
    //                         $out[$k] = $row[$k];
    //                 }
    //                 if (!isset($out['familia']) && array_key_exists('family', $row))
    //                     $out['familia'] = $row['family'];
    //                 return $out;
    //             };
    //             if (is_array($items) && count($items) > 0) {
    //                 Log::debug('SiReTT items sample', [
    //                     'sample' => array_map($picker, array_slice($items, 0, min($LOG_SAMPLE_LIMIT, count($items)))),
    //                 ]);
    //             }

    //             // Distribución de familias (top N)
    //             $familiaDist = collect($items)->groupBy(function ($row) {
    //                 $fam = $row['familia'] ?? ($row['family'] ?? null);
    //                 return is_string($fam) ? trim($fam) : '';
    //             })->map->count()->sortDesc();

    //             $missingFamilia = $familiaDist[''] ?? 0;
    //             $topFamilias = $familiaDist->except([''])->take(20)->all();

    //             Log::info('SiReTT distribución de familias', [
    //                 'con_familia_total' => array_sum($familiaDist->except([''])->all()),
    //                 'sin_familia_total' => $missingFamilia,
    //                 'top20' => $topFamilias,
    //             ]);

    //             if ($missingFamilia > 0) {
    //                 $sampleMissing = collect($items)->filter(function ($row) {
    //                     $fam = $row['familia'] ?? ($row['family'] ?? null);
    //                     return !is_string($fam) || trim($fam) === '';
    //                 })->take(15)->values()->all();

    //                 Log::debug('SiReTT items sin familia (sample)', [
    //                     'sample' => array_map($picker, $sampleMissing),
    //                 ]);
    //             }
    //         } catch (\Throwable $e) {
    //             Log::error('Error al conectar/leer SiReTT', [
    //                 'cliente' => $clienteNombre,
    //                 'error' => $e->getMessage(),
    //             ]);
    //             throw new \RuntimeException('Error al conectar con SiReTT: ' . $e->getMessage());
    //         }

    //         // Extraer familias únicas
    //         $familiasSiReTT = collect($items)
    //             ->map(function ($row) {
    //                 $fam = $row['familia'] ?? ($row['family'] ?? null);
    //                 return is_string($fam) ? trim($fam) : '';
    //             })
    //             ->filter(fn($v) => $v !== '')
    //             ->unique()
    //             ->values();

    //         // LOG: familias SiReTT (conteo + muestra)
    //         Log::info('SiReTT: familias únicas', [
    //             'cliente' => $clienteNombre,
    //             'total' => $familiasSiReTT->count(),
    //             'sample' => $familiasSiReTT->take($LOG_SAMPLE_LIMIT)->values()->all(),
    //         ]);

    //         // Lookup: key_normalizada -> descripción familia (array plano para velocidad)
    //         $familiasMap = $familiasSiReTT
    //             ->mapWithKeys(fn($f) => [$this->catKey($f) => $f])
    //             ->all();

    //         // LOG: mapa key->familia (muestra)
    //         Log::debug('SiReTT: mapa key_normalized -> familia (sample)', [
    //             'sample' => collect($familiasMap)->take($LOG_SAMPLE_LIMIT)->all(),
    //             'keys_total' => count($familiasMap),
    //         ]);

    //         // === matcher inteligente (exacto, containment, fuzzy) ===
    //         $matchFamiliaByKey = function (?string $normalizedKey) use ($familiasMap) {
    //             if (!$normalizedKey)
    //                 return null;

    //             // 1) Exacto
    //             if (isset($familiasMap[$normalizedKey])) {
    //                 return $familiasMap[$normalizedKey];
    //             }

    //             // 2) Containment (evita palabras muy cortas)
    //             foreach ($familiasMap as $famKey => $desc) {
    //                 if (strlen($famKey) >= 4 && str_contains($normalizedKey, $famKey)) {
    //                     return $desc;
    //                 }
    //                 if (strlen($normalizedKey) >= 4 && str_contains($famKey, $normalizedKey)) {
    //                     return $desc;
    //                 }
    //             }

    //             // 3) Fuzzy (Levenshtein normalizado)
    //             $bestDesc = null;
    //             $bestScore = 0.0;
    //             foreach ($familiasMap as $famKey => $desc) {
    //                 $maxLen = max(strlen($normalizedKey), strlen($famKey));
    //                 if ($maxLen === 0)
    //                     continue;
    //                 $lev = levenshtein($normalizedKey, $famKey);
    //                 $score = 1.0 - ($lev / $maxLen); // 0..1
    //                 if ($score > $bestScore) {
    //                     $bestScore = $score;
    //                     $bestDesc = $desc;
    //                 }
    //             }
    //             return ($bestScore >= 0.75) ? $bestDesc : null;
    //         };

    //         // Versión "verbose" para logging: devuelve familia y método
    //         $debugMatchFamilia = function (?string $text) use ($familiasMap) {
    //             $result = [
    //                 'familia' => null,
    //                 'method' => 'none',
    //                 'name_key' => null,
    //                 'fam_key' => null,
    //                 'score' => null,
    //             ];
    //             if (!$text)
    //                 return $result;

    //             $key = $this->catKey($text);
    //             $result['name_key'] = $key;

    //             // Exacto
    //             if (isset($familiasMap[$key])) {
    //                 $result['familia'] = $familiasMap[$key];
    //                 $result['method'] = 'exact';
    //                 $result['fam_key'] = $key;
    //                 return $result;
    //             }

    //             // Containment
    //             foreach ($familiasMap as $famKey => $desc) {
    //                 if (strlen($famKey) >= 4 && str_contains($key, $famKey)) {
    //                     $result['familia'] = $desc;
    //                     $result['method'] = 'contain_cat_has_fam';
    //                     $result['fam_key'] = $famKey;
    //                     return $result;
    //                 }
    //                 if (strlen($key) >= 4 && str_contains($famKey, $key)) {
    //                     $result['familia'] = $desc;
    //                     $result['method'] = 'contain_fam_has_cat';
    //                     $result['fam_key'] = $famKey;
    //                     return $result;
    //                 }
    //             }

    //             // Fuzzy
    //             $bestDesc = null;
    //             $bestKey = null;
    //             $bestScore = 0.0;
    //             foreach ($familiasMap as $famKey => $desc) {
    //                 $maxLen = max(strlen($key), strlen($famKey));
    //                 if ($maxLen === 0)
    //                     continue;
    //                 $lev = levenshtein($key, $famKey);
    //                 $score = 1.0 - ($lev / $maxLen);
    //                 if ($score > $bestScore) {
    //                     $bestScore = $score;
    //                     $bestDesc = $desc;
    //                     $bestKey = $famKey;
    //                 }
    //             }
    //             if ($bestScore >= 0.75) {
    //                 $result['familia'] = $bestDesc;
    //                 $result['method'] = 'fuzzy';
    //                 $result['fam_key'] = $bestKey;
    //                 $result['score'] = round($bestScore, 4);
    //             }
    //             return $result;
    //         };

    //         $matchFamiliaFromText = function (?string $text) use ($debugMatchFamilia) {
    //             $dbg = $debugMatchFamilia($text);
    //             return $dbg['familia'];
    //         };

    //         // === 2) Woo -> todas las categorías (paginadas) ===
    //         $categoriasWoo = collect();
    //         $page = 1;
    //         do {
    //             $res = Http::retry(3, 2000)
    //                 ->withBasicAuth($credWoo->user, $credWoo->password)
    //                 ->timeout(120)
    //                 ->get("{$credWoo->base_url}/products/categories", [
    //                     'per_page' => 100,
    //                     'page' => $page,
    //                     'orderby' => 'id',
    //                     'order' => 'asc'
    //                 ]);

    //             if ($res->failed()) {
    //                 Log::error('Error HTTP obteniendo categorías de Woo', ['status' => $res->status(), 'body' => $res->body()]);
    //                 throw new \RuntimeException('Error al obtener categorías desde WooCommerce: ' . $res->body());
    //             }

    //             $batch = collect($res->json());
    //             $categoriasWoo = $categoriasWoo->merge($batch);
    //             $page++;
    //         } while ($batch->count() > 0);

    //         // LOG: categorías Woo (conteo + muestra condensada)
    //         Log::info('Woo: categorías obtenidas', [
    //             'cliente' => $clienteNombre,
    //             'total' => $categoriasWoo->count(),
    //             'sample' => $categoriasWoo->take($LOG_SAMPLE_LIMIT)->map(function ($c) {
    //                 return [
    //                     'id' => $c['id'] ?? null,
    //                     'name' => $c['name'] ?? null,
    //                     'name_k' => $this->catKey($c['name'] ?? ''),
    //                     'slug' => $c['slug'] ?? null,
    //                     'parent' => (int) ($c['parent'] ?? 0),
    //                     'count' => (int) ($c['count'] ?? 0),
    //                 ];
    //             })->values()->all(),
    //         ]);

    //         // Índices rápidos
    //         $wooById = $categoriasWoo->keyBy('id');
    //         $wooByKey = $categoriasWoo->keyBy(fn($c) => $this->catKey($c['name'] ?? ''));

    //         // === 3) BASELINE LOCAL: upsert de TODAS las categorías con productos_woo y familia (desde SiReTT) ===
    //         $matchStats = ['exact' => 0, 'contain_cat_has_fam' => 0, 'contain_fam_has_cat' => 0, 'fuzzy' => 0, 'none' => 0];
    //         $unmatchedSample = [];

    //         foreach ($categoriasWoo as $i => $cat) {
    //             $name = $cat['name'] ?? '';
    //             $count = (int) ($cat['count'] ?? 0);
    //             $parentId = (int) ($cat['parent'] ?? 0);

    //             $dbg = $debugMatchFamilia($name);
    //             $familiaMatch = $dbg['familia'];
    //             $method = $dbg['method'];
    //             $matchStats[$method] = ($matchStats[$method] ?? 0) + 1;

    //             if ($method === 'none' && count($unmatchedSample) < $LOG_SAMPLE_LIMIT) {
    //                 $unmatchedSample[] = [
    //                     'woo_id' => $cat['id'] ?? null,
    //                     'name' => $name,
    //                     'name_key' => $dbg['name_key'],
    //                     'slug' => $cat['slug'] ?? null,
    //                     'parent' => $parentId,
    //                     'count' => $count,
    //                 ];
    //             }

    //             // Log detallado para primeras N filas
    //             if ($i < 30) {
    //                 Log::debug('Match categoría vs familia (baseline)', [
    //                     'woo_id' => $cat['id'] ?? null,
    //                     'name' => $name,
    //                     'name_key' => $dbg['name_key'],
    //                     'method' => $method,
    //                     'fam_key' => $dbg['fam_key'],
    //                     'familia' => $familiaMatch,
    //                     'score' => $dbg['score'],
    //                 ]);
    //             }

    //             $this->ensureCatSync($clienteNombre, $familiaMatch, [
    //                 'id' => $cat['id'] ?? null,
    //                 'name' => $name,
    //                 'slug' => $cat['slug'] ?? null,
    //                 'parent' => $parentId,
    //                 'count' => $count,
    //             ]);
    //         }

    //         // LOG: resumen de matching
    //         Log::info('Resumen matching Woo vs SiReTT', [
    //             'cliente' => $clienteNombre,
    //             'stats' => $matchStats,
    //             'unmatched_sample' => $unmatchedSample,
    //         ]);

    //         // === 4) PRE-LIMPIEZA: eliminar duplicadas con count==0 ===
    //         $byId = $categoriasWoo->keyBy('id');
    //         $childrenByParent = $categoriasWoo->groupBy(fn($c) => (int) ($c['parent'] ?? 0));
    //         $normalize = fn(string $n) => preg_replace(
    //             '/\s+/',
    //             ' ',
    //             iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', Str::of($n)->lower()->squish()->toString()) ?: $n
    //         );
    //         $groups = $categoriasWoo->groupBy(fn($c) => $normalize($c['name'] ?? ''));

    //         $dup_groups = 0;
    //         $dup_candidates = 0;
    //         $toDelete = collect();

    //         foreach ($groups as $key => $list) {
    //             if ($key === '' || $list->count() <= 1)
    //                 continue;

    //             $dup_groups++;

    //             // Mantener la más "fuerte": mayor count, luego menor id
    //             $keep = $list->sortBy([
    //                 fn($a, $b) => ($b['count'] <=> $a['count']),
    //                 fn($a, $b) => ($a['id'] <=> $b['id']),
    //             ])->first();

    //             $list->each(function ($c) use ($keep, &$toDelete, &$dup_candidates) {
    //                 if ($c['id'] === $keep['id'])
    //                     return;
    //                 if ((int) ($c['count'] ?? 0) === 0) {
    //                     $toDelete->push((int) $c['id']);
    //                     $dup_candidates++;
    //                 }
    //             });
    //         }

    //         $toDelete = $toDelete->unique()->values();
    //         $deleted = [];
    //         $deleteErrors = [];

    //         if ($toDelete->isNotEmpty()) {
    //             $passes = 0;
    //             $max = 10;
    //             while ($toDelete->isNotEmpty() && $passes < $max) {
    //                 $passes++;
    //                 $set = $toDelete->flip();

    //                 $leafIds = $toDelete->filter(function ($id) use ($childrenByParent, $set) {
    //                     $children = $childrenByParent->get($id, collect());
    //                     foreach ($children as $child) {
    //                         if (isset($set[$child['id']]))
    //                             return false; // aún tiene hijos en la cola
    //                     }
    //                     return true;
    //                 })->values();

    //                 if ($leafIds->isEmpty())
    //                     break;

    //                 foreach ($leafIds as $id) {
    //                     $orig = $byId[$id] ?? null;

    //                     $del = Http::retry(2, 1500)
    //                         ->withBasicAuth($credWoo->user, $credWoo->password)
    //                         ->timeout(60)
    //                         ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

    //                     if ($del->successful()) {
    //                         $deleted[] = $id;

    //                         SyncHistoryDetail::create([
    //                             'sync_history_id' => $sync->id,
    //                             'sku' => "CAT:{$id}",
    //                             'tipo' => 'categoria_eliminada',
    //                             'datos_anteriores' => [
    //                                 'id' => $id,
    //                                 'name' => $orig['name'] ?? null,
    //                                 'slug' => $orig['slug'] ?? null,
    //                                 'count' => (int) ($orig['count'] ?? 0),
    //                             ],
    //                             'datos_nuevos' => [],
    //                             'deltas' => [],
    //                         ]);

    //                         // Limpiar tracking local
    //                         CategoriaSincronizada::where('cliente', $clienteNombre)
    //                             ->where('woocommerce_id', $id)
    //                             ->delete();
    //                     } else {
    //                         $deleteErrors[] = ['id' => $id, 'http' => $del->status(), 'body' => $del->body()];
    //                     }

    //                     $toDelete = $toDelete->reject(fn($x) => $x === $id)->values();
    //                 }
    //             }

    //             // Quitar del listado local las eliminadas
    //             $categoriasWoo = $categoriasWoo->reject(fn($c) => in_array($c['id'], $deleted))->values();

    //             // LOG: resultado de limpieza
    //             Log::info('Limpieza de duplicados en Woo', [
    //                 'cliente' => $clienteNombre,
    //                 'grupos_dup' => $dup_groups,
    //                 'candidatas_dup' => $dup_candidates,
    //                 'eliminadas' => count($deleted),
    //                 'errores' => $deleteErrors,
    //             ]);
    //         }

    //         // Para resumen
    //         $productosPorCategoria = $categoriasWoo->map(fn($c) => [
    //             'id' => $c['id'],
    //             'name' => $c['name'] ?? '',
    //             'slug' => $c['slug'] ?? '',
    //             'count' => (int) ($c['count'] ?? 0),
    //         ])->values();

    //         // Recalcular mapas
    //         $mapIdPorKey = [];
    //         $slugExistente = [];
    //         foreach ($categoriasWoo as $cat) {
    //             $id = $cat['id'];
    //             $name = $cat['name'] ?? '';
    //             $slug = $cat['slug'] ?? '';

    //             $mapIdPorKey[$this->categoryKey($name)] = $id;
    //             if ($slug !== '')
    //                 $slugExistente[$slug] = $id;
    //         }

    //         // === 5) Renombrado opcional ===
    //         $renombradas = [];
    //         if ($RENAME_EXISTING && $categoriasWoo->isNotEmpty()) {
    //             foreach ($categoriasWoo as $cat) {
    //                 $id = $cat['id'];
    //                 $name = $cat['name'] ?? '';
    //                 $slug = $cat['slug'] ?? '';
    //                 $count = (int) ($cat['count'] ?? 0);

    //                 // Intentar familia por nombre actual (exacta/containment/fuzzy) — origen SiReTT
    //                 $dbg = $debugMatchFamilia($name);
    //                 $familiaMatch = $dbg['familia'];

    //                 $nameDeseado = $this->categoryDisplay($name);
    //                 $slugDeseado = $this->categorySlug($name);

    //                 $needsRename = ($name !== $nameDeseado) || ($slug !== $slugDeseado);
    //                 if (!$needsRename) {
    //                     $this->ensureCatSync($clienteNombre, $familiaMatch, [
    //                         'id' => $id,
    //                         'name' => $name,
    //                         'slug' => $slug,
    //                         'parent' => (int) ($cat['parent'] ?? 0),
    //                         'count' => $count,
    //                     ]);
    //                     continue;
    //                 }

    //                 $slugFinal = $slugDeseado;
    //                 if (isset($slugExistente[$slugDeseado]) && $slugExistente[$slugDeseado] !== $id) {
    //                     $slugFinal = $slugDeseado . '-' . $id;
    //                 }

    //                 $up = Http::retry(3, 2000)
    //                     ->withBasicAuth($credWoo->user, $credWoo->password)
    //                     ->timeout(120)
    //                     ->put("{$credWoo->base_url}/products/categories/{$id}", [
    //                         'name' => $nameDeseado,
    //                         'slug' => $slugFinal
    //                     ]);

    //                 if ($up->successful()) {
    //                     $this->ensureCatSync($clienteNombre, $familiaMatch, [
    //                         'id' => $id,
    //                         'name' => $nameDeseado,
    //                         'slug' => $slugFinal,
    //                         'parent' => (int) ($cat['parent'] ?? 0),
    //                         'count' => $count,
    //                     ]);

    //                     unset($slugExistente[$slug]);
    //                     $slugExistente[$slugFinal] = $id;

    //                     $oldKey = $this->categoryKey($name);
    //                     if (($mapIdPorKey[$oldKey] ?? null) === $id)
    //                         unset($mapIdPorKey[$oldKey]);
    //                     $mapIdPorKey[$this->categoryKey($nameDeseado)] = $id;

    //                     $renombradas[] = [
    //                         'id' => $id,
    //                         'old' => ['name' => $name, 'slug' => $slug],
    //                         'new' => ['name' => $nameDeseado, 'slug' => $slugFinal],
    //                     ];

    //                     SyncHistoryDetail::create([
    //                         'sync_history_id' => $sync->id,
    //                         'sku' => "CAT:{$id}",
    //                         'tipo' => 'categoria_renombrada',
    //                         'datos_anteriores' => ['id' => $id, 'name' => $name, 'slug' => $slug],
    //                         'datos_nuevos' => ['id' => $id, 'name' => $nameDeseado, 'slug' => $slugFinal],
    //                         'deltas' => [],
    //                     ]);
    //                 } else {
    //                     $this->ensureCatSync($clienteNombre, $familiaMatch, [
    //                         'id' => $id,
    //                         'name' => $name,
    //                         'slug' => $slug,
    //                         'parent' => (int) ($cat['parent'] ?? 0),
    //                         'count' => $count,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // === 6) Asegurar registro local para familias SiReTT ya existentes en Woo ===
    //         $familiasYaEnWoo = $familiasSiReTT->filter(fn($f) => $wooByKey->has($this->catKey($f)));
    //         Log::info('Familias SiReTT que ya existen en Woo (match exacto por key)', [
    //             'cliente' => $clienteNombre,
    //             'total' => $familiasYaEnWoo->count(),
    //             'sample' => $familiasYaEnWoo->take(30)->values()->all(),
    //         ]);

    //         foreach ($familiasYaEnWoo as $familia) {
    //             $k = $this->catKey($familia);
    //             $wooCat = $wooByKey->get($k);
    //             $wooArr = is_array($wooCat) ? $wooCat : (array) $wooCat;

    //             $this->ensureCatSync($clienteNombre, $familia, [
    //                 'id' => $wooArr['id'] ?? null,
    //                 'name' => $wooArr['name'] ?? '',
    //                 'slug' => $wooArr['slug'] ?? null,
    //                 'parent' => (int) ($wooArr['parent'] ?? 0),
    //                 'count' => (int) ($wooArr['count'] ?? 0),
    //             ]);
    //         }

    //         // === 7) Crear faltantes desde SiReTT ===
    //         $familiasParaCrear = $familiasSiReTT
    //             ->map(fn($f) => [
    //                 'original' => $f,
    //                 'display' => $this->categoryDisplay($f),
    //                 'slug' => $this->categorySlug($f),
    //                 'key' => $this->categoryKey($f),
    //             ])
    //             ->filter(fn($row) => !isset($mapIdPorKey[$row['key']]))
    //             ->values();

    //         Log::info('Familias SiReTT que requieren creación en Woo', [
    //             'cliente' => $clienteNombre,
    //             'total' => $familiasParaCrear->count(),
    //             'sample' => $familiasParaCrear->take(40)->values()->all(),
    //         ]);

    //         $creadas = [];
    //         $erroresCreacion = [];

    //         if ($familiasParaCrear->isNotEmpty()) {
    //             foreach ($familiasParaCrear->chunk(100) as $chunkIndex => $chunk) {
    //                 $expectedBySlug = [];

    //                 $createPayload = $chunk->map(function ($row) use (&$slugExistente, &$expectedBySlug) {
    //                     $slugFinal = $row['slug'];
    //                     if (isset($slugExistente[$slugFinal])) {
    //                         $slugFinal = $row['slug'] . '-' . uniqid();
    //                     }
    //                     $slugExistente[$slugFinal] = -1; // reservar
    //                     $expectedBySlug[$slugFinal] = [
    //                         'familia' => $row['original'],
    //                         'key' => $row['key']
    //                     ];
    //                     return ['name' => $row['display'], 'slug' => $slugFinal];
    //                 })->values()->all();

    //                 Log::debug('Payload creación Woo desde familias SiReTT (chunk)', [
    //                     'cliente' => $clienteNombre,
    //                     'chunk' => $chunkIndex,
    //                     'create_count' => count($createPayload),
    //                     'expectedBySlug_sample' => array_slice($expectedBySlug, 0, min(25, count($expectedBySlug))),
    //                 ]);

    //                 $res = Http::retry(3, 2000)
    //                     ->withBasicAuth($credWoo->user, $credWoo->password)
    //                     ->timeout(120)
    //                     ->post("{$credWoo->base_url}/products/categories/batch", [
    //                         'create' => $createPayload
    //                     ]);

    //                 if ($res->successful()) {
    //                     $created = collect(($res->json())['create'] ?? []);
    //                     foreach ($created as $cat) {
    //                         $creadas[] = [
    //                             'id' => $cat['id'] ?? null,
    //                             'name' => $cat['name'] ?? null,
    //                             'slug' => $cat['slug'] ?? null
    //                         ];

    //                         // Vinculación de la nueva categoría con su familia SiReTT de origen
    //                         $slugCreated = $cat['slug'] ?? null;
    //                         $familia = ($slugCreated && isset($expectedBySlug[$slugCreated]))
    //                             ? $expectedBySlug[$slugCreated]['familia']
    //                             : ($cat['name'] ?? null);

    //                         Log::debug('Categoría Woo creada desde familia SiReTT', [
    //                             'woo_id' => $cat['id'] ?? null,
    //                             'woo_name' => $cat['name'] ?? null,
    //                             'slug' => $slugCreated,
    //                             'familia_origen' => $familia,
    //                         ]);

    //                         SyncHistoryDetail::create([
    //                             'sync_history_id' => $sync->id,
    //                             'sku' => "CAT:" . ($cat['id'] ?? 'new'),
    //                             'tipo' => 'categoria_creada',
    //                             'datos_anteriores' => [],
    //                             'datos_nuevos' => $cat ?? [],
    //                             'deltas' => [],
    //                         ]);

    //                         $this->ensureCatSync($clienteNombre, $familia, [
    //                             'id' => $cat['id'] ?? null,
    //                             'name' => $cat['name'] ?? null,
    //                             'slug' => $cat['slug'] ?? null,
    //                             'parent' => (int) ($cat['parent'] ?? 0),
    //                             'count' => (int) ($cat['count'] ?? 0),
    //                         ]);
    //                     }
    //                 } else {
    //                     $body = $res->body();
    //                     $erroresCreacion[] = $body;
    //                     Log::error('Error creando categorías en Woo desde familias SiReTT', [
    //                         'cliente' => $clienteNombre,
    //                         'status' => $res->status(),
    //                         'body' => $body,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // === 7.1) BACKFILL FINAL: completar familias NULL (exacto, containment, fuzzy) ===
    //         $ahora = now('America/Managua');
    //         $backfillUpdated = 0;

    //         CategoriaSincronizada::where('cliente', $clienteNombre)
    //             ->where(function ($q) {
    //                 $q->whereNull('familia_sirett')->orWhereNull('familia_sirett_key');
    //             })
    //             ->orderBy('id')
    //             ->chunk(500, function ($rows) use ($debugMatchFamilia, $ahora, &$backfillUpdated) {
    //                 foreach ($rows as $row) {
    //                     // Intentos: key_normalized -> nombre -> slug(espacios)
    //                     $familia = null;
    //                     $dbgUsed = null;

    //                     if ($row->key_normalized) {
    //                         $dbg = $debugMatchFamilia($row->key_normalized);
    //                         $familia = $dbg['familia'];
    //                         $dbgUsed = ['src' => 'key_normalized', 'dbg' => $dbg];
    //                     }
    //                     if (!$familia && !empty($row->nombre)) {
    //                         $dbg = $debugMatchFamilia($row->nombre);
    //                         $familia = $dbg['familia'];
    //                         $dbgUsed = ['src' => 'nombre', 'dbg' => $dbg];
    //                     }
    //                     if (!$familia && !empty($row->slug)) {
    //                         $dbg = $debugMatchFamilia(str_replace('-', ' ', $row->slug));
    //                         $familia = $dbg['familia'];
    //                         $dbgUsed = ['src' => 'slug', 'dbg' => $dbg];
    //                     }

    //                     if ($familia) {
    //                         $famKey = $this->catKey($familia);

    //                         $needsUpdate = false;
    //                         if (empty($row->familia_sirett)) {
    //                             $row->familia_sirett = $familia;
    //                             $needsUpdate = true;
    //                         }
    //                         if (empty($row->familia_sirett_key)) {
    //                             $row->familia_sirett_key = $famKey;
    //                             $needsUpdate = true;
    //                         }

    //                         if ($needsUpdate) {
    //                             $row->updated_at = $ahora; // Managua
    //                             $row->save();
    //                             $backfillUpdated++;

    //                             // Log de la primera docena de fills
    //                             if ($backfillUpdated <= 12) {
    //                                 Log::debug('Backfill familia aplicado', [
    //                                     'row_id' => $row->id,
    //                                     'src' => $dbgUsed['src'] ?? null,
    //                                     'method' => $dbgUsed['dbg']['method'] ?? null,
    //                                     'name_key' => $dbgUsed['dbg']['name_key'] ?? null,
    //                                     'fam_key' => $dbgUsed['dbg']['fam_key'] ?? null,
    //                                     'familia' => $familia,
    //                                 ]);
    //                             }
    //                         }
    //                     }
    //                 }
    //             });

    //         Log::info('Backfill familias completado', [
    //             'cliente' => $clienteNombre,
    //             'actualizadas' => $backfillUpdated,
    //         ]);

    //         // === 8) Cerrar historial ===
    //         $fin = now('America/Managua');
    //         $duracion = $inicio->floatDiffInSeconds($fin);

    //         $sync->update([
    //             'finished_at' => $fin,
    //             'total_creados' => count($creadas),
    //             'total_actualizados' => count($renombradas),
    //             'total_omitidos' => 0,
    //             'total_fallidos_categoria' => count($deleteErrors) + count($erroresCreacion),
    //         ]);

    //         if (method_exists($this, 'notificarTelegram')) {
    //             $msg = "🗂 <b>Sync Categorías</b> <b>{$clienteNombre}</b>\n"
    //                 . "🧹 Duplicados: grupos={$dup_groups}, candidatas={$dup_candidates}, eliminadas=" . count($deleted) . "\n"
    //                 . "✏️ Renombradas: <b>" . count($renombradas) . "</b>\n"
    //                 . "🆕 Creadas: <b>" . count($creadas) . "</b>\n"
    //                 . "⏱️ Duración: <b>" . number_format($duracion, 2) . "s</b>";
    //             $this->notificarTelegram($clienteNombre, $msg);
    //         }

    //         return response()->json([
    //             'sync_history_id' => $sync->id,
    //             'mensaje' => 'Limpieza de duplicados, baseline local, backfill de familias (con logs SiReTT) y sincronización completada',
    //             'cliente' => $clienteNombre,
    //             'duplicados' => [
    //                 'grupos_encontrados' => $dup_groups,
    //                 'candidatas_a_borrar' => $dup_candidates,
    //                 'eliminadas_total' => count($deleted),
    //                 'eliminadas_ids' => $deleted,
    //                 'errores_eliminacion' => $deleteErrors,
    //             ],
    //             'resumen_categorias_woo' => [
    //                 'total' => $categoriasWoo->count(),
    //                 'por_categoria' => $productosPorCategoria,
    //             ],
    //             'renombradas_total' => count($renombradas),
    //             'renombradas' => $renombradas,
    //             'creadas_total' => count($creadas),
    //             'creadas' => $creadas,
    //             'total_familias_sirett' => $familiasSiReTT->count(),
    //             'inicio' => $inicio->format('Y-m-d H:i:s'),
    //             'fin' => $fin->format('Y-m-d H:i:s'),
    //             'duracion' => number_format($duracion, 2) . 's',
    //         ]);

    //     } catch (\Throwable $e) {
    //         $fin = now('America/Managua');
    //         $sync->update([
    //             'finished_at' => $fin,
    //             'total_creados' => 0,
    //             'total_actualizados' => 0,
    //             'total_omitidos' => 0,
    //             'total_fallidos_categoria' => 1,
    //         ]);

    //         if (method_exists($this, 'notificarErrorTelegram')) {
    //             $this->notificarErrorTelegram($clienteNombre, 'Excepción categorías: ' . $e->getMessage());
    //         }

    //         Log::error('Sync categorías: excepción no controlada', [
    //             'cliente' => $clienteNombre,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return response()->json([
    //             'error' => 'Excepción no controlada',
    //             'detalle' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function syncSirettCategoriesToWoo(string $clienteNombre, Request $request)
{
    $RENAME_EXISTING = true;

    // === Zona horaria a nivel de sesión MySQL (timestamps por defecto) ===
    try {
        DB::statement("SET time_zone = '-06:00'");
        Log::info('MySQL time_zone establecido', ['tz' => '-06:00', 'cliente' => $clienteNombre]);
    } catch (\Throwable $e) {
        Log::warning('No se pudo establecer time_zone de la sesión MySQL', ['error' => $e->getMessage(), 'cliente' => $clienteNombre]);
    }

    // Límite de muestras en logs para no saturar
    $LOG_SAMPLE_LIMIT = 60;

    $inicio = now('America/Managua');
    $sync = SyncHistory::create([
        'cliente'     => $clienteNombre,
        'started_at'  => $inicio,
    ]);

    try {
        // === 0) Credenciales ===
        $credWoo    = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

        Log::info('Credenciales detectadas', [
            'cliente'         => $clienteNombre,
            'woo_base_url'    => $credWoo->base_url ?? null,
            'sirett_base_url' => $credSirett->base_url ?? null,
            'sirett_user_set' => !empty($credSirett->user),
            'sirett_pass_set' => !empty($credSirett->password),
            'sirett_bid'      => $credSirett->extra ?? null,
        ]);

        if (!$credWoo || !$credSirett) {
            Log::error('Credenciales faltantes para WooCommerce o SiReTT', ['cliente' => $clienteNombre]);
            return response()->json(['error' => 'Credenciales no encontradas para WooCommerce o SiReTT'], 404);
        }

        // ------------------------------------------------------------------
        // === 1) SiReTT: obtener ITEMS (productos) y familias únicas (+IDs)
        //   - Reintenta con varios formatos de parámetros
        //   - Loguea request/response size, tiempo y muestras
        // ------------------------------------------------------------------
        $items = [];
        $sirettAttempts = [];
        try {
            $wsdl = rtrim($credSirett->base_url ?? '', '/') . '?wsdl';
            Log::info('Conectando a SiReTT SOAP', ['wsdl' => $wsdl, 'cliente' => $clienteNombre]);

            // Cliente SOAP con trazas y sin cache de WSDL (para ver cambios inmediatos)
            $soapOpts = [
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => 60,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8',
                'stream_context' => stream_context_create([
                    'http' => ['user_agent' => 'PHP-SOAP/CategorySync'],
                ]),
            ];
            $client = new \SoapClient($wsdl, $soapOpts);

            // Intentos con diferentes firmas de parámetros
            $paramStruct = [
                'ws_pid'    => $credSirett->user,
                'ws_passwd' => $credSirett->password,
                'bid'       => $credSirett->extra,
            ];
            $paramList = [$credSirett->user, $credSirett->password, $credSirett->extra];

            $attempts = [
                ['label' => 'struct_array',     'call' => fn() => $client->__soapCall('wsp_request_items', [$paramStruct])],
                ['label' => 'struct_direct',    'call' => fn() => $client->__soapCall('wsp_request_items', $paramStruct)],
                ['label' => 'positional_array', 'call' => fn() => $client->__soapCall('wsp_request_items', [$paramList])],
                ['label' => 'method_direct',    'call' => fn() => $client->wsp_request_items($paramStruct)],
                ['label' => 'positional_args',  'call' => fn() => $client->__soapCall('wsp_request_items', $paramList)],
            ];

            $lastReq = null; $lastResp = null; $rawFiles = [];
            foreach ($attempts as $idx => $a) {
                $t0 = microtime(true);
                $label = $a['label'];
                try {
                    Log::info('SiReTT intento de llamada', [
                        'attempt' => $label,
                        'idx'     => $idx,
                        'has_user'=> !empty($credSirett->user),
                        'has_pass'=> !empty($credSirett->password),
                        'bid'     => $credSirett->extra,
                    ]);

                    $resp = $a['call']();
                    $elapsed = (microtime(true) - $t0) * 1000.0;

                    // Métricas de tráfico SOAP
                    try {
                        $lastReq  = $client->__getLastRequest();
                        $lastResp = $client->__getLastResponse();

                        // Guardar RAW de la respuesta por intento (para auditoría puntual)
                        $fileBase = 'sirett_' . $sync->id . '_' . $label;
                        $respFile = storage_path("logs/{$fileBase}_resp.xml");
                        @file_put_contents($respFile, $lastResp);
                        $rawFiles[] = basename($respFile);

                        Log::debug('SiReTT SOAP tráfico', [
                            'attempt'        => $label,
                            'request_bytes'  => is_string($lastReq)  ? strlen($lastReq)  : null,
                            'response_bytes' => is_string($lastResp) ? strlen($lastResp) : null,
                            'elapsed_ms'     => round($elapsed, 2),
                            'raw_file'       => basename($respFile),
                        ]);
                    } catch (\Throwable $e) {
                        Log::debug('No se pudo registrar lastRequest/lastResponse', ['attempt' => $label, 'error' => $e->getMessage()]);
                    }

                    $arr = json_decode(json_encode($resp), true);
                    $dataTmp = $arr['data'] ?? [];
                    $totalTmp = is_array($dataTmp) ? count($dataTmp) : 0;

                    $sirettAttempts[] = [
                        'attempt' => $label,
                        'ok'      => true,
                        'items'   => $totalTmp,
                        'elapsed_ms' => round($elapsed, 2),
                    ];

                    Log::info('SiReTT respuesta procesada por intento', [
                        'attempt'    => $label,
                        'cliente'    => $clienteNombre,
                        'items_total'=> $totalTmp,
                        'has_data'   => array_key_exists('data', $arr),
                        'root_keys'  => array_keys($arr),
                    ]);

                    // Tomar el primer intento que devuelva datos
                    if ($totalTmp > 0) {
                        $items = $dataTmp;
                        break;
                    }
                } catch (\Throwable $ex) {
                    $elapsed = (microtime(true) - $t0) * 1000.0;
                    $sirettAttempts[] = [
                        'attempt' => $label,
                        'ok'      => false,
                        'error'   => $ex->getMessage(),
                        'elapsed_ms' => round($elapsed, 2),
                    ];
                    Log::warning('SiReTT intento fallido', [
                        'attempt' => $label,
                        'error'   => $ex->getMessage(),
                        'elapsed_ms' => round($elapsed, 2),
                    ]);
                }
            }

            // Log resumen de intentos
            Log::info('SiReTT intentos realizados', [
                'cliente'  => $clienteNombre,
                'attempts' => $sirettAttempts,
            ]);

            // Si no hay items después de todos los intentos => devolver error con pista
            if (!is_array($items) || count($items) === 0) {
                // Guardar último response (si existe) como ayuda
                if (!empty($lastResp)) {
                    $failFile = storage_path("logs/sirett_{$sync->id}_lastresp_empty.xml");
                    @file_put_contents($failFile, $lastResp);
                }
                Log::critical('SiReTT no devolvió productos (items vacío) tras múltiples intentos', [
                    'cliente' => $clienteNombre,
                    'wsdl' => $wsdl,
                    'attempts' => $sirettAttempts,
                    'raw_files' => $rawFiles,
                ]);

                return response()->json([
                    'error' => 'SiReTT no devolvió productos.',
                    'detalle' => 'Ver logs para intentos, archivos RAW y tamaños de respuesta.',
                    'attempts' => $sirettAttempts,
                ], 502);
            }

            // Muestras de items (solo campos relevantes)
            $pick = function(array $row) {
                $keys = ['familia','family','familia_id','family_id','id_familia','codigo','item_code','sku','descripcion','name','precio','stock','marca','brand'];
                $out = [];
                foreach ($keys as $k) if (array_key_exists($k, $row)) $out[$k] = $row[$k];
                if (!isset($out['familia']) && array_key_exists('family', $row)) $out['familia'] = $row['family'];
                return $out;
            };
            Log::debug('SiReTT items (sample)', [
                'cliente' => $clienteNombre,
                'sample'  => array_map($pick, array_slice($items, 0, min($LOG_SAMPLE_LIMIT, count($items)))),
            ]);

            // Guardar archivo JSON con una muestra amplia (hasta 1000) para auditoría
            @file_put_contents(
                storage_path("logs/sirett_items_{$sync->id}_{$clienteNombre}.json"),
                json_encode(array_slice($items, 0, 1000), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // Distribución de familias (nombre + id si disponible)
            $getFamName = function($row) {
                $fam = $row['familia'] ?? ($row['family'] ?? null);
                return is_string($fam) ? trim($fam) : '';
            };
            $getFamId = function($row) {
                foreach (['familia_id','family_id','id_familia','idfamilia','cod_familia','familycode'] as $k) {
                    if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
                }
                return null;
            };

            $familiaPairs = collect($items)->map(function($r) use ($getFamName, $getFamId) {
                return ['id' => $getFamId($r), 'name' => $getFamName($r)];
            });

            $famWithId = $familiaPairs->filter(fn($p) => ($p['name'] ?? '') !== '' && $p['id'] !== null);
            $famNoId   = $familiaPairs->filter(fn($p) => ($p['name'] ?? '') !== '' && $p['id'] === null);

            Log::info('SiReTT familias (conteos)', [
                'cliente'           => $clienteNombre,
                'total_items'       => count($items),
                'familias_con_id'   => $famWithId->unique(fn($p) => $p['id'] . '|' . $p['name'])->count(),
                'familias_sin_id'   => $famNoId->unique(fn($p) => $p['name'])->count(),
            ]);

            Log::debug('SiReTT familias con ID (sample)', [
                'sample' => $famWithId->unique(fn($p) => $p['id'] . '|' . $p['name'])
                                       ->take($LOG_SAMPLE_LIMIT)->values()->all(),
            ]);
            Log::debug('SiReTT familias sin ID (sample)', [
                'sample' => $famNoId->unique(fn($p) => $p['name'])
                                    ->take($LOG_SAMPLE_LIMIT)->values()->all(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error general al conectar/leer SiReTT', [
                'cliente' => $clienteNombre,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error al conectar con SiReTT', 'detalle' => $e->getMessage()], 500);
        }

        // === 1.b) Extraer familias únicas por NOMBRE (para matching con Woo)
        $familiasSiReTT = collect($items)
            ->map(function($row){
                $fam = $row['familia'] ?? ($row['family'] ?? null);
                return is_string($fam) ? trim($fam) : '';
            })
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values();

        Log::info('SiReTT: familias únicas (por nombre)', [
            'cliente' => $clienteNombre,
            'total'   => $familiasSiReTT->count(),
            'sample'  => $familiasSiReTT->take($LOG_SAMPLE_LIMIT)->values()->all(),
        ]);

        // Mapa key_normalizada -> nombre familia
        $familiasMap = $familiasSiReTT->mapWithKeys(fn($f) => [$this->catKey($f) => $f])->all();
        Log::debug('SiReTT: mapa key_normalized -> familia (sample)', [
            'sample' => collect($familiasMap)->take($LOG_SAMPLE_LIMIT)->all(),
            'keys_total' => count($familiasMap),
        ]);

        // === matcher (exacto, containment, fuzzy) para nombre de familia ===
        $matchFamiliaByKey = function (?string $normalizedKey) use ($familiasMap) {
            if (!$normalizedKey) return null;
            if (isset($familiasMap[$normalizedKey])) {
                return $familiasMap[$normalizedKey]; // exact
            }
            foreach ($familiasMap as $famKey => $desc) {
                if (strlen($famKey) >= 4 && str_contains($normalizedKey, $famKey)) return $desc;
                if (strlen($normalizedKey) >= 4 && str_contains($famKey, $normalizedKey)) return $desc;
            }
            $bestDesc = null; $bestScore = 0.0;
            foreach ($familiasMap as $famKey => $desc) {
                $maxLen = max(strlen($normalizedKey), strlen($famKey));
                if ($maxLen === 0) continue;
                $lev = levenshtein($normalizedKey, $famKey);
                $score = 1.0 - ($lev / $maxLen);
                if ($score > $bestScore) { $bestScore = $score; $bestDesc = $desc; }
            }
            return ($bestScore >= 0.75) ? $bestDesc : null;
        };
        $debugMatchFamilia = function (?string $text) use ($familiasMap) {
            $result = ['familia'=>null,'method'=>'none','name_key'=>null,'fam_key'=>null,'score'=>null];
            if (!$text) return $result;
            $key = $this->catKey($text); $result['name_key'] = $key;
            if (isset($familiasMap[$key])) { $result['familia']=$familiasMap[$key]; $result['method']='exact'; $result['fam_key']=$key; return $result; }
            foreach ($familiasMap as $famKey => $desc) {
                if (strlen($famKey)>=4 && str_contains($key,$famKey)) { $result['familia']=$desc; $result['method']='contain_cat_has_fam'; $result['fam_key']=$famKey; return $result; }
                if (strlen($key)>=4 && str_contains($famKey,$key)) { $result['familia']=$desc; $result['method']='contain_fam_has_cat'; $result['fam_key']=$famKey; return $result; }
            }
            $bestDesc=null;$bestKey=null;$bestScore=0.0;
            foreach ($familiasMap as $famKey => $desc) {
                $maxLen = max(strlen($key), strlen($famKey)); if ($maxLen===0) continue;
                $lev = levenshtein($key,$famKey);
                $score = 1.0 - ($lev/$maxLen);
                if ($score>$bestScore){$bestScore=$score;$bestDesc=$desc;$bestKey=$famKey;}
            }
            if ($bestScore>=0.75){$result['familia']=$bestDesc;$result['method']='fuzzy';$result['fam_key']=$bestKey;$result['score']=round($bestScore,4);}
            return $result;
        };

        // ------------------------------------------------------------------
        // === 2) Woo -> obtener TODAS las categorías (paginadas)
        // ------------------------------------------------------------------
        $categoriasWoo = collect();
        $page = 1;
        do {
            $res = Http::retry(3, 2000)
                ->withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(120)
                ->get("{$credWoo->base_url}/products/categories", [
                    'per_page' => 100,
                    'page'     => $page,
                    'orderby'  => 'id',
                    'order'    => 'asc'
                ]);
            if ($res->failed()) {
                Log::error('Error HTTP obteniendo categorías de Woo', ['status' => $res->status(), 'body' => $res->body()]);
                throw new \RuntimeException('Error al obtener categorías desde WooCommerce: ' . $res->body());
            }
            $batch = collect($res->json());
            $categoriasWoo = $categoriasWoo->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        Log::info('Woo: categorías obtenidas', [
            'cliente' => $clienteNombre,
            'total'   => $categoriasWoo->count(),
            'sample'  => $categoriasWoo->take($LOG_SAMPLE_LIMIT)->map(function ($c) {
                return [
                    'id'     => $c['id'] ?? null,
                    'name'   => $c['name'] ?? null,
                    'name_k' => $this->catKey($c['name'] ?? ''),
                    'slug'   => $c['slug'] ?? null,
                    'parent' => (int)($c['parent'] ?? 0),
                    'count'  => (int)($c['count'] ?? 0),
                ];
            })->values()->all(),
        ]);

        // Índices rápidos
        $wooById  = $categoriasWoo->keyBy('id');
        $wooByKey = $categoriasWoo->keyBy(fn($c) => $this->catKey($c['name'] ?? ''));

        // ------------------------------------------------------------------
        // === 3) BASELINE LOCAL: upsert de TODAS las categorías con familia SiReTT
        // ------------------------------------------------------------------
        $matchStats = ['exact' => 0, 'contain_cat_has_fam' => 0, 'contain_fam_has_cat' => 0, 'fuzzy' => 0, 'none' => 0];
        $unmatchedSample = [];

        foreach ($categoriasWoo as $i => $cat) {
            $name     = $cat['name'] ?? '';
            $count    = (int) ($cat['count'] ?? 0);
            $parentId = (int) ($cat['parent'] ?? 0);

            $dbg = $debugMatchFamilia($name);
            $familiaMatch = $dbg['familia'];
            $method = $dbg['method'];
            $matchStats[$method] = ($matchStats[$method] ?? 0) + 1;

            if ($method === 'none' && count($unmatchedSample) < $LOG_SAMPLE_LIMIT) {
                $unmatchedSample[] = [
                    'woo_id'   => $cat['id'] ?? null,
                    'name'     => $name,
                    'name_key' => $dbg['name_key'],
                    'slug'     => $cat['slug'] ?? null,
                    'parent'   => $parentId,
                    'count'    => $count,
                ];
            }

            if ($i < 30) {
                Log::debug('Match categoría Woo vs familia SiReTT (baseline)', [
                    'woo_id'   => $cat['id'] ?? null,
                    'name'     => $name,
                    'name_key' => $dbg['name_key'],
                    'method'   => $method,
                    'fam_key'  => $dbg['fam_key'],
                    'familia'  => $familiaMatch,
                    'score'    => $dbg['score'],
                ]);
            }

            $this->ensureCatSync($clienteNombre, $familiaMatch, [
                'id'     => $cat['id'] ?? null,
                'name'   => $name,
                'slug'   => $cat['slug'] ?? null,
                'parent' => $parentId,
                'count'  => $count,
            ]);
        }

        Log::info('Resumen matching Woo vs SiReTT (familias por nombre)', [
            'cliente'  => $clienteNombre,
            'stats'    => $matchStats,
            'unmatched_sample' => $unmatchedSample,
        ]);

        // ------------------------------------------------------------------
        // === 4) PRE-LIMPIEZA: eliminar duplicadas con count==0
        // ------------------------------------------------------------------
        $byId             = $categoriasWoo->keyBy('id');
        $childrenByParent = $categoriasWoo->groupBy(fn($c) => (int) ($c['parent'] ?? 0));
        $normalize = fn(string $n) => preg_replace(
            '/\s+/', ' ',
            iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', Str::of($n)->lower()->squish()->toString()) ?: $n
        );
        $groups = $categoriasWoo->groupBy(fn($c) => $normalize($c['name'] ?? ''));

        $dup_groups = 0;
        $dup_candidates = 0;
        $toDelete = collect();

        foreach ($groups as $key => $list) {
            if ($key === '' || $list->count() <= 1) continue;

            $dup_groups++;
            $keep = $list->sortBy([
                fn($a, $b) => ($b['count'] <=> $a['count']),
                fn($a, $b) => ($a['id']   <=> $b['id']),
            ])->first();

            $list->each(function ($c) use ($keep, &$toDelete, &$dup_candidates) {
                if ($c['id'] === $keep['id']) return;
                if ((int)($c['count'] ?? 0) === 0) {
                    $toDelete->push((int) $c['id']);
                    $dup_candidates++;
                }
            });
        }

        $toDelete = $toDelete->unique()->values();
        $deleted = [];
        $deleteErrors = [];

        if ($toDelete->isNotEmpty()) {
            $passes = 0;
            $max    = 10;
            while ($toDelete->isNotEmpty() && $passes < $max) {
                $passes++;
                $set = $toDelete->flip();

                $leafIds = $toDelete->filter(function ($id) use ($childrenByParent, $set) {
                    $children = $childrenByParent->get($id, collect());
                    foreach ($children as $child) {
                        if (isset($set[$child['id']])) return false;
                    }
                    return true;
                })->values();

                if ($leafIds->isEmpty()) break;

                foreach ($leafIds as $id) {
                    $orig = $byId[$id] ?? null;

                    $del = Http::retry(2, 1500)
                        ->withBasicAuth($credWoo->user, $credWoo->password)
                        ->timeout(60)
                        ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

                    if ($del->successful()) {
                        $deleted[] = $id;

                        SyncHistoryDetail::create([
                            'sync_history_id'  => $sync->id,
                            'sku'              => "CAT:{$id}",
                            'tipo'             => 'categoria_eliminada',
                            'datos_anteriores' => [
                                'id'    => $id,
                                'name'  => $orig['name'] ?? null,
                                'slug'  => $orig['slug'] ?? null,
                                'count' => (int)($orig['count'] ?? 0),
                            ],
                            'datos_nuevos'     => [],
                            'deltas'           => [],
                        ]);

                        CategoriaSincronizada::where('cliente', $clienteNombre)
                            ->where('woocommerce_id', $id)
                            ->delete();
                    } else {
                        $deleteErrors[] = ['id' => $id, 'http' => $del->status(), 'body' => $del->body()];
                    }

                    $toDelete = $toDelete->reject(fn($x) => $x === $id)->values();
                }
            }

            $categoriasWoo = $categoriasWoo->reject(fn($c) => in_array($c['id'], $deleted))->values();

            Log::info('Limpieza de duplicados en Woo', [
                'cliente'        => $clienteNombre,
                'grupos_dup'     => $dup_groups,
                'candidatas_dup' => $dup_candidates,
                'eliminadas'     => count($deleted),
                'errores'        => $deleteErrors,
            ]);
        }

        // Para resumen
        $productosPorCategoria = $categoriasWoo->map(fn($c) => [
            'id'    => $c['id'],
            'name'  => $c['name'] ?? '',
            'slug'  => $c['slug'] ?? '',
            'count' => (int) ($c['count'] ?? 0),
        ])->values();

        // Recalcular mapas
        $mapIdPorKey   = [];
        $slugExistente = [];
        foreach ($categoriasWoo as $cat) {
            $id   = $cat['id'];
            $name = $cat['name'] ?? '';
            $slug = $cat['slug'] ?? '';

            $mapIdPorKey[$this->categoryKey($name)] = $id;
            if ($slug !== '') $slugExistente[$slug] = $id;
        }

        // ------------------------------------------------------------------
        // === 5) Renombrado opcional ===
        // ------------------------------------------------------------------
        $renombradas = [];
        if ($RENAME_EXISTING && $categoriasWoo->isNotEmpty()) {
            foreach ($categoriasWoo as $cat) {
                $id    = $cat['id'];
                $name  = $cat['name'] ?? '';
                $slug  = $cat['slug'] ?? '';
                $count = (int)($cat['count'] ?? 0);

                $dbg = $debugMatchFamilia($name);
                $familiaMatch = $dbg['familia'];

                $nameDeseado = $this->categoryDisplay($name);
                $slugDeseado = $this->categorySlug($name);

                $needsRename = ($name !== $nameDeseado) || ($slug !== $slugDeseado);
                if (!$needsRename) {
                    $this->ensureCatSync($clienteNombre, $familiaMatch, [
                        'id'     => $id,
                        'name'   => $name,
                        'slug'   => $slug,
                        'parent' => (int)($cat['parent'] ?? 0),
                        'count'  => $count,
                    ]);
                    continue;
                }

                $slugFinal = $slugDeseado;
                if (isset($slugExistente[$slugDeseado]) && $slugExistente[$slugDeseado] !== $id) {
                    $slugFinal = $slugDeseado . '-' . $id;
                }

                $up = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->put("{$credWoo->base_url}/products/categories/{$id}", [
                        'name' => $nameDeseado,
                        'slug' => $slugFinal
                    ]);

                if ($up->successful()) {
                    $this->ensureCatSync($clienteNombre, $familiaMatch, [
                        'id'     => $id,
                        'name'   => $nameDeseado,
                        'slug'   => $slugFinal,
                        'parent' => (int)($cat['parent'] ?? 0),
                        'count'  => $count,
                    ]);

                    unset($slugExistente[$slug]);
                    $slugExistente[$slugFinal] = $id;

                    $oldKey = $this->categoryKey($name);
                    if (($mapIdPorKey[$oldKey] ?? null) === $id) unset($mapIdPorKey[$oldKey]);
                    $mapIdPorKey[$this->categoryKey($nameDeseado)] = $id;

                    $renombradas[] = [
                        'id'  => $id,
                        'old' => ['name' => $name, 'slug' => $slug],
                        'new' => ['name' => $nameDeseado, 'slug' => $slugFinal],
                    ];

                    SyncHistoryDetail::create([
                        'sync_history_id'  => $sync->id,
                        'sku'              => "CAT:{$id}",
                        'tipo'             => 'categoria_renombrada',
                        'datos_anteriores' => ['id' => $id, 'name' => $name, 'slug' => $slug],
                        'datos_nuevos'     => ['id' => $id, 'name' => $nameDeseado, 'slug' => $slugFinal],
                        'deltas'           => [],
                    ]);
                } else {
                    $this->ensureCatSync($clienteNombre, $familiaMatch, [
                        'id'     => $id,
                        'name'   => $name,
                        'slug'   => $slug,
                        'parent' => (int)($cat['parent'] ?? 0),
                        'count'  => $count,
                    ]);
                }
            }
        }

        // ------------------------------------------------------------------
        // === 6) Asegurar tracking local para familias SiReTT que ya existen en Woo (match exacto por key)
        // ------------------------------------------------------------------
        $familiasYaEnWoo = $familiasSiReTT->filter(fn($f) => $wooByKey->has($this->catKey($f)));
        Log::info('Familias SiReTT que ya existen en Woo (match exacto por key)', [
            'cliente' => $clienteNombre,
            'total'   => $familiasYaEnWoo->count(),
            'sample'  => $familiasYaEnWoo->take(30)->values()->all(),
        ]);

        foreach ($familiasYaEnWoo as $familia) {
            $k      = $this->catKey($familia);
            $wooCat = $wooByKey->get($k);
            $wooArr = is_array($wooCat) ? $wooCat : (array) $wooCat;

            $this->ensureCatSync($clienteNombre, $familia, [
                'id'     => $wooArr['id']   ?? null,
                'name'   => $wooArr['name'] ?? '',
                'slug'   => $wooArr['slug'] ?? null,
                'parent' => (int)($wooArr['parent'] ?? 0),
                'count'  => (int)($wooArr['count'] ?? 0),
            ]);
        }

        // ------------------------------------------------------------------
        // === 7) Crear categorías faltantes desde familias SiReTT
        // ------------------------------------------------------------------
        $familiasParaCrear = $familiasSiReTT
            ->map(fn($f) => [
                'original' => $f,
                'display'  => $this->categoryDisplay($f),
                'slug'     => $this->categorySlug($f),
                'key'      => $this->categoryKey($f),
            ])
            ->filter(fn($row) => !isset($mapIdPorKey[$row['key']]))
            ->values();

        Log::info('Familias SiReTT que requieren creación en Woo', [
            'cliente' => $clienteNombre,
            'total'   => $familiasParaCrear->count(),
            'sample'  => $familiasParaCrear->take(40)->values()->all(),
        ]);

        $creadas = [];
        $erroresCreacion = [];

        if ($familiasParaCrear->isNotEmpty()) {
            foreach ($familiasParaCrear->chunk(100) as $chunkIndex => $chunk) {
                $expectedBySlug = [];

                $createPayload = $chunk->map(function ($row) use (&$slugExistente, &$expectedBySlug) {
                    $slugFinal = $row['slug'];
                    if (isset($slugExistente[$slugFinal])) {
                        $slugFinal = $row['slug'] . '-' . uniqid();
                    }
                    $slugExistente[$slugFinal] = -1; // reservar
                    $expectedBySlug[$slugFinal] = [
                        'familia' => $row['original'],
                        'key'     => $row['key']
                    ];
                    return ['name' => $row['display'], 'slug' => $slugFinal];
                })->values()->all();

                Log::debug('Payload creación Woo desde familias SiReTT (chunk)', [
                    'cliente' => $clienteNombre,
                    'chunk'   => $chunkIndex,
                    'create_count' => count($createPayload),
                    'expectedBySlug_sample' => array_slice($expectedBySlug, 0, min(25, count($expectedBySlug))),
                ]);

                $res = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->post("{$credWoo->base_url}/products/categories/batch", [
                        'create' => $createPayload
                    ]);

                if ($res->successful()) {
                    $created = collect(($res->json())['create'] ?? []);
                    foreach ($created as $cat) {
                        $creadas[] = [
                            'id'   => $cat['id']   ?? null,
                            'name' => $cat['name'] ?? null,
                            'slug' => $cat['slug'] ?? null
                        ];

                        $slugCreated = $cat['slug'] ?? null;
                        $familia     = ($slugCreated && isset($expectedBySlug[$slugCreated]))
                            ? $expectedBySlug[$slugCreated]['familia']
                            : ($cat['name'] ?? null);

                        Log::debug('Categoría Woo creada desde familia SiReTT', [
                            'woo_id'  => $cat['id'] ?? null,
                            'woo_name'=> $cat['name'] ?? null,
                            'slug'    => $slugCreated,
                            'familia_origen' => $familia,
                        ]);

                        SyncHistoryDetail::create([
                            'sync_history_id'  => $sync->id,
                            'sku'              => "CAT:" . ($cat['id'] ?? 'new'),
                            'tipo'             => 'categoria_creada',
                            'datos_anteriores' => [],
                            'datos_nuevos'     => $cat ?? [],
                            'deltas'           => [],
                        ]);

                        $this->ensureCatSync($clienteNombre, $familia, [
                            'id'     => $cat['id'] ?? null,
                            'name'   => $cat['name'] ?? null,
                            'slug'   => $cat['slug'] ?? null,
                            'parent' => (int)($cat['parent'] ?? 0),
                            'count'  => (int)($cat['count'] ?? 0),
                        ]);
                    }
                } else {
                    $body = $res->body();
                    $erroresCreacion[] = $body;
                    Log::error('Error creando categorías en Woo desde familias SiReTT', [
                        'cliente' => $clienteNombre,
                        'status'  => $res->status(),
                        'body'    => $body,
                    ]);
                }
            }
        }

        // ------------------------------------------------------------------
        // === 7.1) BACKFILL FINAL: completar familias NULL en tracking local
        // ------------------------------------------------------------------
        $ahora = now('America/Managua');
        $backfillUpdated = 0;

        CategoriaSincronizada::where('cliente', $clienteNombre)
            ->where(function ($q) {
                $q->whereNull('familia_sirett')->orWhereNull('familia_sirett_key');
            })
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($debugMatchFamilia, $ahora, &$backfillUpdated) {
                foreach ($rows as $row) {
                    $familia = null; $dbgUsed = null;

                    if ($row->key_normalized) {
                        $dbg = $debugMatchFamilia($row->key_normalized);
                        $familia = $dbg['familia']; $dbgUsed = ['src' => 'key_normalized', 'dbg' => $dbg];
                    }
                    if (!$familia && !empty($row->nombre)) {
                        $dbg = $debugMatchFamilia($row->nombre);
                        $familia = $dbg['familia']; $dbgUsed = ['src' => 'nombre', 'dbg' => $dbg];
                    }
                    if (!$familia && !empty($row->slug)) {
                        $dbg = $debugMatchFamilia(str_replace('-', ' ', $row->slug));
                        $familia = $dbg['familia']; $dbgUsed = ['src' => 'slug', 'dbg' => $dbg];
                    }

                    if ($familia) {
                        $famKey = $this->catKey($familia);
                        $needsUpdate = false;
                        if (empty($row->familia_sirett))     { $row->familia_sirett = $familia; $needsUpdate = true; }
                        if (empty($row->familia_sirett_key)) { $row->familia_sirett_key = $famKey; $needsUpdate = true; }

                        if ($needsUpdate) {
                            $row->updated_at = $ahora; // Managua
                            $row->save();
                            $backfillUpdated++;
                            if ($backfillUpdated <= 12) {
                                Log::debug('Backfill familia aplicado', [
                                    'row_id'  => $row->id,
                                    'src'     => $dbgUsed['src'] ?? null,
                                    'method'  => $dbgUsed['dbg']['method'] ?? null,
                                    'name_key'=> $dbgUsed['dbg']['name_key'] ?? null,
                                    'fam_key' => $dbgUsed['dbg']['fam_key'] ?? null,
                                    'familia' => $familia,
                                ]);
                            }
                        }
                    }
                }
            });

        Log::info('Backfill familias completado', [
            'cliente' => $clienteNombre,
            'actualizadas' => $backfillUpdated,
        ]);

        // === 8) Cerrar historial ===
        $fin      = now('America/Managua');
        $duracion = $inicio->floatDiffInSeconds($fin);

        $sync->update([
            'finished_at'              => $fin,
            'total_creados'            => count($creadas),
            'total_actualizados'       => count($renombradas),
            'total_omitidos'           => 0,
            'total_fallidos_categoria' => count($deleteErrors) + count($erroresCreacion),
        ]);

        if (method_exists($this, 'notificarTelegram')) {
            $msg = "🗂 <b>Sync Categorías</b> <b>{$clienteNombre}</b>\n"
                . "🧹 Duplicados: grupos={$dup_groups}, candidatas={$dup_candidates}, eliminadas=" . count($deleted) . "\n"
                . "✏️ Renombradas: <b>" . count($renombradas) . "</b>\n"
                . "🆕 Creadas: <b>" . count($creadas) . "</b>\n"
                . "⏱️ Duración: <b>" . number_format($duracion, 2) . "s</b>";
            $this->notificarTelegram($clienteNombre, $msg);
        }

        return response()->json([
            'sync_history_id' => $sync->id,
            'mensaje'         => 'Limpieza de duplicados, baseline local, backfill de familias (con logs SiReTT) y sincronización completada',
            'cliente'         => $clienteNombre,
            'duplicados' => [
                'grupos_encontrados'   => $dup_groups,
                'candidatas_a_borrar'  => $dup_candidates,
                'eliminadas_total'     => count($deleted),
                'eliminadas_ids'       => $deleted,
                'errores_eliminacion'  => $deleteErrors,
            ],
            'resumen_categorias_woo' => [
                'total'         => $categoriasWoo->count(),
                'por_categoria' => $productosPorCategoria,
            ],
            'renombradas_total' => count($renombradas),
            'renombradas'       => $renombradas,
            'creadas_total'     => count($creadas),
            'creadas'           => $creadas,
            'total_familias_sirett' => $familiasSiReTT->count(),
            'inicio'   => $inicio->format('Y-m-d H:i:s'),
            'fin'      => $fin->format('Y-m-d H:i:s'),
            'duracion' => number_format($duracion, 2) . 's',
        ]);

    } catch (\Throwable $e) {
        $fin = now('America/Managua');
        $sync->update([
            'finished_at'              => $fin,
            'total_creados'            => 0,
            'total_actualizados'       => 0,
            'total_omitidos'           => 0,
            'total_fallidos_categoria' => 1,
        ]);

        if (method_exists($this, 'notificarErrorTelegram')) {
            $this->notificarErrorTelegram($clienteNombre, 'Excepción categorías: ' . $e->getMessage());
        }

        Log::error('Sync categorías: excepción no controlada', [
            'cliente' => $clienteNombre,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error'   => 'Excepción no controlada',
            'detalle' => $e->getMessage()
        ], 500);
    }
}


    private function catKey(string $name): string
    {
        $n = Str::of($name)->lower()->squish()->toString();
        $n = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n) ?: $n;
        return preg_replace('/\s+/', ' ', $n);
    }

    private function categoryKey(string $name): string
    {
        return $this->catKey($name);
    }

    private function categoryDisplay(string $name): string
    {
        return Str::of($name)->squish()->title()->toString();
    }

    private function categorySlug(string $name): string
    {
        return Str::slug(Str::of($name)->squish()->toString(), '-');
    }

    private function ensureCatSync(string $cliente, ?string $familia, array $wooCat): void
    {
        if (!Schema::hasTable('categoria_sincronizadas')) {
            return;
        }

        static $cols = null;
        if ($cols === null) {
            $cols = Schema::getColumnListing('categoria_sincronizadas');
        }
        $has = fn(string $c) => in_array($c, $cols, true);

        $name = $wooCat['name'] ?? '';
        $slug = $wooCat['slug'] ?? null;
        $id = $wooCat['id'] ?? null;
        $parent = (int) ($wooCat['parent'] ?? 0);
        $count = (int) ($wooCat['count'] ?? 0);

        $keyNorm = $this->catKey($name);
        $famKey = $familia ? $this->catKey($familia) : null;

        // WHERE único
        if ($has('key_normalized')) {
            $where = ['cliente' => $cliente, 'key_normalized' => $keyNorm];
        } elseif (!empty($id) && $has('woocommerce_id')) {
            $where = ['cliente' => $cliente, 'woocommerce_id' => $id];
        } else {
            $where = ['cliente' => $cliente, 'nombre' => $name];
        }

        // DATA
        $data = ['cliente' => $cliente];

        if ($has('nombre'))
            $data['nombre'] = $name;
        if ($has('woocommerce_id'))
            $data['woocommerce_id'] = $id;
        if ($has('respuesta'))
            $data['respuesta'] = $wooCat;
        if ($has('slug'))
            $data['slug'] = $slug;
        if ($has('key_normalized'))
            $data['key_normalized'] = $keyNorm;
        if ($has('familia_sirett'))
            $data['familia_sirett'] = $familia;
        if ($has('familia_sirett_key'))
            $data['familia_sirett_key'] = $famKey;
        if ($has('woocommerce_parent_id'))
            $data['woocommerce_parent_id'] = $parent ?: null;
        if ($has('es_principal'))
            $data['es_principal'] = ($parent === 0);
        if ($has('productos_woo'))
            $data['productos_woo'] = $count;

        CategoriaSincronizada::updateOrCreate($where, $data);
    }

    // ===================== HELPERS =====================

    public function listWooCategories(string $clienteNombre)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo)
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);

        $cats = collect();
        $page = 1;
        do {
            $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(60)
                ->get("{$credWoo->base_url}/products/categories", ['per_page' => 100, 'page' => $page, 'orderby' => 'id', 'order' => 'asc']);
            if ($res->failed())
                return response()->json(['error' => 'Woo error', 'detalle' => $res->body()], 500);
            $batch = collect($res->json());
            $cats = $cats->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        // devolvemos lo esencial para la tabla
        $data = $cats->map(fn($c) => [
            'id' => $c['id'],
            'name' => $c['name'] ?? '',
            'slug' => $c['slug'] ?? '',
            'count' => (int) ($c['count'] ?? 0),
            'parent' => (int) ($c['parent'] ?? 0),
        ])->values();

        return response()->json(['total' => $data->count(), 'categories' => $data]);
    }

    public function deleteAllZeroCountCategories(string $clienteNombre)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo)
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);

        // traer todas
        $cats = collect();
        $page = 1;
        do {
            $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(60)
                ->get("{$credWoo->base_url}/products/categories", ['per_page' => 100, 'page' => $page, 'orderby' => 'id', 'order' => 'asc']);
            if ($res->failed())
                return response()->json(['error' => 'Woo error', 'detalle' => $res->body()], 500);
            $batch = collect($res->json());
            $cats = $cats->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        $byId = $cats->keyBy('id');
        $childrenByParent = $cats->groupBy(fn($c) => (int) ($c['parent'] ?? 0));

        $toDelete = $cats->filter(fn($c) => (int) ($c['count'] ?? 0) === 0)->pluck('id')->values();
        $deleted = [];
        $errors = [];
        $passes = 0;
        $max = 10;

        // eliminar hojas primero y evitar padres con hijos existentes
        while ($toDelete->isNotEmpty() && $passes < $max) {
            $passes++;
            $set = $toDelete->flip();

            $leafIds = $toDelete->filter(function ($id) use ($childrenByParent, $set) {
                $children = $childrenByParent->get($id, collect());
                // solo es leaf si no tiene hijos o TODOS sus hijos también están en la lista a borrar
                foreach ($children as $ch) {
                    if (!isset($set[$ch['id']]))
                        return false;
                }
                return true;
            })->values();

            if ($leafIds->isEmpty())
                break;

            foreach ($leafIds as $id) {
                $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(60)
                    ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

                if ($res->successful())
                    $deleted[] = $id;
                else
                    $errors[] = ['id' => $id, 'http' => $res->status(), 'body' => $res->body()];

                $toDelete = $toDelete->reject(fn($x) => $x === $id)->values();
            }
        }

        return response()->json([
            'mensaje' => 'Eliminación masiva de categorías con count==0 finalizada',
            'intentadas' => count($deleted) + count($errors),
            'eliminadas' => $deleted,
            'errores' => $errors,
        ]);
    }

    public function deleteOneZeroCountCategory(string $clienteNombre, int $id)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo)
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);

        // traer la categoría
        $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(30)
            ->get("{$credWoo->base_url}/products/categories/{$id}");
        if ($res->failed())
            return response()->json(['error' => 'No se pudo leer categoría', 'detalle' => $res->body()], 500);

        $cat = $res->json();
        if ((int) ($cat['count'] ?? 0) !== 0) {
            return response()->json(['error' => 'La categoría tiene productos asociados (count > 0).'], 422);
        }

        // comprobar hijos
        $hasChildren = false;
        $page = 1;
        do {
            $lr = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(30)
                ->get("{$credWoo->base_url}/products/categories", ['per_page' => 100, 'page' => $page, 'parent' => $id]);
            if ($lr->failed())
                break;
            $batch = collect($lr->json());
            if ($batch->isNotEmpty()) {
                $hasChildren = true;
                break;
            }
            $page++;
        } while ($batch->count() > 0);

        if ($hasChildren) {
            return response()->json(['error' => 'La categoría tiene subcategorías; elimínalas primero.'], 422);
        }

        $del = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(30)
            ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

        if ($del->failed()) {
            return response()->json(['error' => 'No se pudo eliminar', 'detalle' => $del->body()], 500);
        }

        return response()->json(['mensaje' => 'Categoría eliminada', 'id' => $id]);
    }




    public function sirett(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'sirett');

        if (!$cred)
            return response()->json(['error' => 'Credenciales no encontradas'], 404);

        $client = new \SoapClient($cred->base_url . '?wsdl', [
            'trace' => 1,
            'exceptions' => true,
        ]);

        $params = [
            'ws_pid' => $cred->user,
            'ws_passwd' => $cred->password,
            'bid' => $cred->extra,
        ];

        $reques_query_0 = "wsp_request_bodega_all_items";
        $reques_query_1 = "wsp_request_items";

        $response = $client->__soapCall($reques_query_1, $params);


        return response()->json($response);
    }

    public function sirettFiltrado(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'sirett');

        if (!$cred)
            return response()->json(['error' => 'Credenciales no encontradas'], 404);

        try {
            $client = new \SoapClient($cred->base_url . '?wsdl', [
                'trace' => 1,
                'exceptions' => true,
            ]);

            $params = [
                'ws_pid' => $cred->user,
                'ws_passwd' => $cred->password,
                'bid' => $cred->extra,
            ];

            $response = $client->__soapCall("wsp_request_items", $params);
            $productos = json_decode(json_encode($response), true)['data'] ?? [];

            // ✅ Filtrar solo los campos deseados (por ejemplo: "codigo")
            $resultado = array_map(function ($item) {

                // return [
                //     'codigo' => $item['codigo'] ?? null,
                //     'descripcion' => $item['descripcion'] ?? null,
                //     'precio' => $item['precio'] ?? null,
                // ];

                return [
                    'codigo' => $item['codigo'] ?? null,
                ];
            }, $productos);

            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error("❌ Error al consultar SiReTT filtrado: " . $e->getMessage());
            return response()->json(['error' => 'Error al conectar con SiReTT', 'detalle' => $e->getMessage()], 500);
        }
    }

    public function getUniqueFamiliesFromSirett(string $clienteNombre)
    {
        $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

        if (!$credSirett) {
            return response()->json(['error' => 'Credenciales de SiReTT no encontradas'], 404);
        }

        try {
            $client = new \SoapClient($credSirett->base_url . '?wsdl', ['trace' => 1, 'exceptions' => true]);

            $params = [
                'ws_pid' => $credSirett->user,
                'ws_passwd' => $credSirett->password,
                'bid' => $credSirett->extra,
            ];

            $response = $client->__soapCall("wsp_request_items", $params);
            $items = json_decode(json_encode($response), true)['data'] ?? [];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al conectar con SiReTT', 'detalle' => $e->getMessage()], 500);
        }

        $familiasUnicas = collect($items)
            ->pluck('familia')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'cliente' => $clienteNombre,
            'total_familias' => $familiasUnicas->count(),
            'familias' => $familiasUnicas,
        ]);
    }


    private function mapearImagenes(array $producto): array
    {
        $imagenes = [];
        for ($i = 0; $i <= 5; $i++) {
            $key = $i === 0 ? 'image_url' : "image_url_{$i}";
            if (!empty($producto[$key])) {
                $imagenes[] = [
                    'src' => 'https://familyoutletsancarlos.com/' . ltrim($producto[$key], '/')
                ];
            }
        }
        return $imagenes;
    }

    public function sincronizarProductosConCategorias(string $clienteNombre)
    {
        // === Comportamiento para productos Woo sin SKU ===
        // 'move'  => mover a categoría "Pendiente de revisión" + status 'draft'
        // 'delete'=> eliminar con force=true
        // 'none'  => no hacer nada (solo reportar)
        $WOO_NO_SKU_ACTION = 'none';                 // 'move' | 'delete' | 'none'
        $WOO_NO_SKU_CATEGORY = 'Pendiente de revisión';// usada si action = move

        try {
            $inicio = now('America/Managua');

            $sync = SyncHistory::create([
                'cliente' => $clienteNombre,
                'started_at' => $inicio,
            ]);

            $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
            $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

            if (!$credWoo || !$credSirett) {
                $this->notificarErrorTelegram($clienteNombre, 'Credenciales no encontradas para WooCommerce o SiReTT.');
                return response()->json(['error' => 'Credenciales no encontradas'], 404);
            }

            // 1) SiReTT
            try {
                $client = new \SoapClient($credSirett->base_url . '?wsdl', ['trace' => 1, 'exceptions' => true]);
                $params = ['ws_pid' => $credSirett->user, 'ws_passwd' => $credSirett->password, 'bid' => $credSirett->extra];
                $response = $client->__soapCall('wsp_request_items', $params);
                $productosSirett = json_decode(json_encode($response), true)['data'] ?? [];
            } catch (\Exception $e) {
                $this->notificarErrorTelegram($clienteNombre, 'Error al conectar con SiReTT: ' . $e->getMessage());
                return response()->json(['error' => 'Error al conectar con SiReTT', 'detalle' => $e->getMessage()], 500);
            }

            if (empty($productosSirett)) {
                $this->notificarErrorTelegram($clienteNombre, 'No se obtuvieron productos desde SiReTT.');
                return response()->json(['error' => 'No se obtuvieron productos desde SiReTT'], 500);
            }

            file_put_contents(
                storage_path("logs/productos_sirett_{$clienteNombre}.json"),
                json_encode($productosSirett, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            Log::info("✅ Total productos recibidos desde SiReTT: " . count($productosSirett));

            // 2) Woo productos
            $productosWoo = collect();
            $page = 1;
            do {
                $res = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->get("{$credWoo->base_url}/products", ['per_page' => 100, 'page' => $page]);

                if ($res->failed())
                    break;

                $batch = collect($res->json());
                $productosWoo = $productosWoo->merge($batch);
                $page++;
            } while ($batch->count() > 0);

            // Evitar colisiones por SKU vacío
            $wooPorSku = $productosWoo
                ->filter(fn($p) => isset($p['sku']) && is_string($p['sku']) && trim($p['sku']) !== '')
                ->keyBy(fn($p) => trim($p['sku']));

            // 3) familias únicas SiReTT (referencia)
            $familiasSiReTT = collect($productosSirett)
                ->pluck('familia')->filter()->unique()
                ->map(fn($f) => $this->categoryKey($f))
                ->values();

            // 4) Woo categorías (traer todas)
            $categoriasWoo = collect();
            $page = 1;
            do {
                $res = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->get("{$credWoo->base_url}/products/categories", ['per_page' => 100, 'page' => $page]);

                if ($res->failed())
                    break;

                $batch = collect($res->json());
                $categoriasWoo = $categoriasWoo->merge($batch);
                $page++;
            } while ($batch->count() > 0);

            // Índices para comparación y colisiones
            $categoriasMap = []; // key (minúsculas) => id
            $slugExistentes = []; // slug => id
            foreach ($categoriasWoo as $cat) {
                $id = $cat['id'];
                $name = $cat['name'] ?? '';
                $slug = $cat['slug'] ?? '';
                $key = $this->categoryKey($name);
                $categoriasMap[$key] = $id;
                $slugExistentes[$slug] = $id;
            }

            // Renombrar categorías en Woo a “Oración” (visible) y slug minúsculas (opcional)
            foreach ($categoriasWoo as $cat) {
                $id = $cat['id'];
                $name = $cat['name'] ?? '';
                $slug = $cat['slug'] ?? '';

                $nameDeseado = $this->categoryDisplay($name); // “Oración”
                $slugDeseado = $this->categorySlug($name);    // minúsculas

                $needsRename = ($name !== $nameDeseado) || ($slug !== $slugDeseado);
                if (!$needsRename)
                    continue;

                // Evitar colisiones de slug
                $slugFinal = $slugDeseado;
                if (isset($slugExistentes[$slugDeseado]) && $slugExistentes[$slugDeseado] !== $id) {
                    $slugFinal = $slugDeseado . '-' . $id;
                }

                // ⚠️ Si no deseas tocar slug por SEO, elimina 'slug' del payload.
                $payload = ['name' => $nameDeseado, 'slug' => $slugFinal];

                $up = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->put("{$credWoo->base_url}/products/categories/{$id}", $payload);

                if ($up->successful()) {
                    unset($slugExistentes[$slug]);
                    $slugExistentes[$slugFinal] = $id;

                    $oldKey = $this->categoryKey($name);
                    if (isset($categoriasMap[$oldKey]) && $categoriasMap[$oldKey] === $id) {
                        unset($categoriasMap[$oldKey]);
                    }
                    $categoriasMap[$this->categoryKey($nameDeseado)] = $id;

                    Log::info("✏️ Categoría #{$id} => name='{$nameDeseado}', slug='{$slugFinal}'");
                } else {
                    Log::warning("❌ No se pudo renombrar categoría #{$id}: " . $up->body());
                }
            }

            // --------------- LOOP PRINCIPAL ---------------
            $creados = [];
            $omitidos = [];
            $actualizados = [];
            $categoriasFallidas = [];
            $fallidosPorCategoria = [];
            $productosParaCrear = [];

            foreach ($productosSirett as $producto) {
                $sku = trim($producto['codigo'] ?? '');
                if ($sku === '')
                    continue;

                $wooProducto = $wooPorSku[$sku] ?? null;

                $nombreCategoriaOriginal = trim($producto['familia'] ?? '');
                if ($nombreCategoriaOriginal === '') {
                    Log::warning("❌ Producto con SKU $sku no tiene familia. Se omite.");
                    SyncError::create([
                        'sync_history_id' => $sync->id,
                        'sku' => $sku,
                        'tipo_error' => 'familia_vacia',
                        'detalle' => json_encode($producto, JSON_UNESCAPED_UNICODE),
                    ]);
                    $categoriasFallidas[] = '(sin familia)';
                    $fallidosPorCategoria[] = $sku;
                    continue;
                }

                // Normalización de categoría para mapa y display
                $keyDeseado = $this->categoryKey($nombreCategoriaOriginal);
                $nameVisible = $this->categoryDisplay($nombreCategoriaOriginal);

                $categoriaId = $categoriasMap[$keyDeseado] ?? null;
                if (!$categoriaId) {
                    // Crear categoría con nombre visible “Oración” y slug minúsculas
                    $slugDeseado = $this->categorySlug($nombreCategoriaOriginal);
                    $slugFinal = $slugDeseado;
                    if (isset($slugExistentes[$slugDeseado])) {
                        $slugFinal = $slugDeseado . '-' . uniqid();
                    }

                    $resCategoria = Http::retry(3, 2000)
                        ->withBasicAuth($credWoo->user, $credWoo->password)
                        ->timeout(120)
                        ->post("{$credWoo->base_url}/products/categories", [
                            'name' => $nameVisible,
                            'slug' => $slugFinal, // remover si no quieres tocar slug
                        ]);

                    if ($resCategoria->successful()) {
                        $categoriaId = $resCategoria->json('id');
                        $categoriasMap[$keyDeseado] = $categoriaId;
                        $slugExistentes[$slugFinal] = $categoriaId;
                    } else {
                        Log::warning("❌ No se pudo crear categoría: $nombreCategoriaOriginal");
                        $categoriasFallidas[] = $nombreCategoriaOriginal;
                        $fallidosPorCategoria[] = $sku;
                        continue;
                    }
                }

                // ====== EXISTE EN WOO: comparar usando NORMALIZACIÓN ======
                if ($wooProducto) {
                    $nombre = trim($producto['descripcion'] ?? '');
                    $precio = number_format((float) ($producto['precio'] ?? 0), 2, '.', '');
                    $stock = (int) ($producto['stock'] ?? 0);

                    $nameOld = $wooProducto['name'] ?? '';
                    $nameNew = $nombre;

                    $catOldName = $wooProducto['categories'][0]['name'] ?? '';
                    $catNewName = $nombreCategoriaOriginal;

                    $needsUpdate =
                        ($this->normalizeText($nameOld) !== $this->normalizeText($nameNew)) ||
                        (($wooProducto['regular_price'] ?? '') !== $precio) ||
                        ((int) ($wooProducto['stock_quantity'] ?? 0) !== $stock) ||
                        ($this->categoryKey($catOldName) !== $this->categoryKey($catNewName));

                    if ($needsUpdate) {
                        $resUpdate = Http::retry(3, 2000)
                            ->withBasicAuth($credWoo->user, $credWoo->password)
                            ->timeout(120)
                            ->put("{$credWoo->base_url}/products/{$wooProducto['id']}", [
                                'name' => $nombre,
                                'regular_price' => $precio,
                                'stock_quantity' => $stock,
                                'categories' => [['id' => $categoriaId]],
                                'manage_stock' => true,
                                'description' => $producto['caracteristicas'] ?? '',
                            ]);

                        if ($resUpdate->successful()) {
                            $actualizados[] = $sku;

                            $rName = $this->fieldDiffReport('name', $nameOld, $nameNew);
                            $rCat = $this->fieldDiffReport('categoria', $catOldName, $catNewName);
                            $rPrecio = [
                                'campo' => 'precio',
                                'igual' => (($wooProducto['regular_price'] ?? '') === $precio),
                                'old_raw' => $wooProducto['regular_price'] ?? '',
                                'new_raw' => $precio,
                            ];
                            $rStock = [
                                'campo' => 'stock',
                                'igual' => ((int) ($wooProducto['stock_quantity'] ?? 0) === $stock),
                                'old_raw' => (int) ($wooProducto['stock_quantity'] ?? 0),
                                'new_raw' => $stock,
                            ];

                            SyncHistoryDetail::create([
                                'sync_history_id' => $sync->id,
                                'sku' => $sku,
                                'tipo' => 'actualizado',
                                'datos_anteriores' => [
                                    'name' => $nameOld,
                                    'precio' => $wooProducto['regular_price'] ?? '',
                                    'stock' => $wooProducto['stock_quantity'] ?? 0,
                                    'categoria' => $catOldName,
                                ],
                                'datos_nuevos' => [
                                    'name' => $nameNew,
                                    'precio' => $precio,
                                    'stock' => $stock,
                                    'categoria' => $catNewName,
                                ],
                                'deltas' => [
                                    'name' => $rName,
                                    'categoria' => $rCat,
                                    'precio' => $rPrecio,
                                    'stock' => $rStock,
                                ],
                            ]);

                            Log::info("🔍 Diff SKU {$sku}", [
                                'name_equal' => $rName['igual'],
                                'name_first_diff' => $rName['first_diff'],
                                'cat_equal' => $rCat['igual'],
                                'cat_first_diff' => $rCat['first_diff'],
                                'precio_equal' => $rPrecio['igual'],
                                'stock_equal' => $rStock['igual'],
                            ]);
                        } else {
                            $this->notificarErrorTelegram($clienteNombre, "Error actualizando SKU $sku: " . $resUpdate->body());
                            Log::warning("❌ Error actualizando SKU $sku: " . $resUpdate->body());
                        }
                    } else {
                        $omitidos[] = $sku;
                    }

                    continue; // ya procesado este SKU
                }

                // ====== NO EXISTE EN WOO: preparar creación ======
                $productosParaCrear[] = [
                    'name' => $producto['descripcion'] ?? '',
                    'sku' => $sku,
                    'regular_price' => number_format((float) ($producto['precio'] ?? 0), 2, '.', ''),
                    'stock_quantity' => (int) ($producto['stock'] ?? 0),
                    'manage_stock' => true,
                    'description' => $producto['caracteristicas'] ?? '',
                    'categories' => [['id' => $categoriaId]],
                    'images' => $this->mapearImagenes($producto),
                ];

                $creados[] = $sku;
                SyncHistoryDetail::create([
                    'sync_history_id' => $sync->id,
                    'sku' => $sku,
                    'tipo' => 'creado',
                    'datos_nuevos' => [
                        'name' => $producto['descripcion'] ?? '',
                        'sku' => $sku,
                        'precio' => $producto['precio'] ?? 0,
                        'stock' => $producto['stock'] ?? 0,
                        'categoria' => $nombreCategoriaOriginal,
                    ],
                ]);
            }

            // Lotes de creación
            $resultados = [];
            foreach (array_chunk($productosParaCrear, 50) as $lote) {
                Log::info("⏳ Enviando lote con " . count($lote) . " productos a WooCommerce");

                $res = Http::retry(3, 2000)
                    ->withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(120)
                    ->post("{$credWoo->base_url}/products/batch", ['create' => $lote]);

                if ($res->successful()) {
                    $resultados[] = ['status' => '✅ Lote creado', 'response' => $res->json()];
                } else {
                    $resultados[] = ['status' => '❌ Error al crear lote', 'response' => $res->body()];
                    $this->notificarErrorTelegram($clienteNombre, 'Error creando lote en WooCommerce: ' . $res->body());
                    Log::warning("❌ Error al crear lote: " . $res->body());
                }
            }

            // --- Métricas extra para el cruce de SKUs
            $skusSirett = collect($productosSirett)
                ->pluck('codigo')
                ->map(fn($v) => is_string($v) ? trim($v) : (string) $v)
                ->filter(fn($v) => $v !== '')
                ->unique()->values();

            // Woo: separa con y sin SKU
            $wooConSku = $productosWoo->filter(function ($p) {
                $s = $p['sku'] ?? '';
                return is_string($s) && trim($s) !== '';
            });
            $wooSinSku = $productosWoo->filter(function ($p) {
                $s = $p['sku'] ?? '';
                return !is_string($s) || trim($s) === '';
            });

            $skusWoo = $wooConSku->pluck('sku')
                ->map(fn($v) => trim($v))->unique()->values();

            // SKUs que están en Woo pero NO en SiReTT
            $soloWoo = $skusWoo->diff($skusSirett)->values();

            // Detalle de “solo en Woo”
            $soloWooDetalle = $wooConSku
                ->filter(fn($p) => $soloWoo->contains(trim($p['sku'])))
                ->map(fn($p) => [
                    'id' => $p['id'] ?? null,
                    'sku' => trim($p['sku']),
                    'name' => $p['name'] ?? null,
                    'status' => $p['status'] ?? null,
                ])->values();

            // Guarda archivo de apoyo
            $reporte = [
                'solo_woocommerce' => $soloWoo,
                'woo_sin_sku' => $wooSinSku->map(fn($p) => [
                    'id' => $p['id'] ?? null,
                    'name' => $p['name'] ?? null,
                    'type' => $p['type'] ?? null,
                    'status' => $p['status'] ?? null,
                ])->values(),
            ];
            file_put_contents(
                storage_path("logs/solo_woo_{$clienteNombre}.json"),
                json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // SKUs con stock = 0 en SiReTT
            $stockCeroSirett = collect($productosSirett)
                ->filter(fn($p) => (int) ($p['stock'] ?? 0) === 0)
                ->pluck('codigo')
                ->filter(fn($v) => is_string($v) && trim($v) !== '')
                ->map(fn($v) => trim($v))
                ->unique()->values();

            // Guardar CSV de stock=0 para esta corrida
            $csvLines = "sku\n" . implode("\n", $stockCeroSirett->all());
            $csvPath = "exports/stock_cero_{$sync->id}.csv";
            Storage::disk('local')->put($csvPath, $csvLines);

            // --- Gestionar productos Woo sin SKU ---
            $gestionWooSinSku = [
                'accion' => $WOO_NO_SKU_ACTION,
                'procesados' => 0,
                'moved_ids' => [],
                'deleted_ids' => [],
                'errores' => [],
            ];

            if ($WOO_NO_SKU_ACTION !== 'none' && $wooSinSku->count() > 0) {
                if ($WOO_NO_SKU_ACTION === 'move') {
                    $catPendId = $this->ensureCategoryExists($credWoo, $WOO_NO_SKU_CATEGORY, $categoriasMap, $slugExistentes);
                    if ($catPendId) {
                        $updates = $wooSinSku->map(function ($p) use ($catPendId) {
                            return ['id' => $p['id'], 'status' => 'draft', 'categories' => [['id' => $catPendId]]];
                        })->values()->all();

                        foreach (array_chunk($updates, 50) as $lote) {
                            $up = Http::retry(3, 2000)
                                ->withBasicAuth($credWoo->user, $credWoo->password)
                                ->timeout(120)
                                ->post("{$credWoo->base_url}/products/batch", ['update' => $lote]);

                            if ($up->successful()) {
                                $gestionWooSinSku['procesados'] += count($lote);
                                $gestionWooSinSku['moved_ids'] = array_merge(
                                    $gestionWooSinSku['moved_ids'],
                                    array_column($lote, 'id')
                                );
                            } else {
                                $gestionWooSinSku['errores'][] = $up->body();
                                Log::warning("❌ Error al mover Woo sin SKU: " . $up->body());
                            }
                        }
                    } else {
                        $gestionWooSinSku['errores'][] = 'No se pudo crear/obtener categoría especial.';
                    }
                }

                if ($WOO_NO_SKU_ACTION === 'delete') {
                    foreach ($wooSinSku as $p) {
                        $pid = $p['id'];
                        $del = Http::retry(2, 1500)
                            ->withBasicAuth($credWoo->user, $credWoo->password)
                            ->timeout(60)
                            ->delete("{$credWoo->base_url}/products/{$pid}", ['force' => true]);

                        if ($del->successful()) {
                            $gestionWooSinSku['procesados']++;
                            $gestionWooSinSku['deleted_ids'][] = $pid;
                        } else {
                            $gestionWooSinSku['errores'][] = "ID {$pid}: " . $del->body();
                            Log::warning("❌ Error al eliminar producto #{$pid} sin SKU: " . $del->body());
                        }
                    }
                }
            }

            // Tiempos
            $fin = now('America/Managua');
            $duracion = $inicio->diffInSeconds($fin);
            Log::info("⏱️ Tiempo total de sincronización para {$clienteNombre}: {$duracion} segundos");

            // Persistir resumen numérico en SyncHistory
            $sync->update([
                'finished_at' => $fin,
                'total_creados' => count($creados),
                'total_actualizados' => count($actualizados),
                'total_omitidos' => count($omitidos),
                'total_fallidos_categoria' => count($fallidosPorCategoria),
            ]);

            // (AHORA SÍ) Enviar Telegram de último
            $resumenTelegram = "📦 <b>Sincronización completada</b> para <b>{$clienteNombre}</b>\n"
                . "🆕 Nuevos: <b>" . count($creados) . "</b>\n"
                . "🔄 Actualizados: <b>" . count($actualizados) . "</b>\n"
                . "⏭️ Omitidos: <b>" . count($omitidos) . "</b>\n"
                . "🛑 Ignorados por categoría: <b>" . count($fallidosPorCategoria) . "</b>\n"
                . "📤 Lotes enviados: <b>" . count($resultados) . "</b>\n"
                . "📥 Total productos SiReTT: <b>" . count($productosSirett) . "</b>\n"
                . "🛒 Total productos Woo: <b>" . $productosWoo->count() . "</b>\n"
                . "🧩 Solo en Woo (vs SiReTT): <b>" . $soloWoo->count() . "</b>\n"
                . "🚫 Woo sin SKU: <b>" . $wooSinSku->count() . "</b>";

            if ($WOO_NO_SKU_ACTION === 'move') {
                $resumenTelegram .= "\n📦 Sin SKU movidos a '{$WOO_NO_SKU_CATEGORY}': <b>" . $gestionWooSinSku['procesados'] . "</b>";
            }
            if ($WOO_NO_SKU_ACTION === 'delete') {
                $resumenTelegram .= "\n🗑️ Sin SKU eliminados: <b>" . $gestionWooSinSku['procesados'] . "</b>";
            }
            if (!empty($categoriasFallidas)) {
                $resumenTelegram .= "\n❌ Categorías no creadas:\n<code>" . implode(', ', array_unique($categoriasFallidas)) . "</code>";
            }
            $resumenTelegram .= "\n⏰ Inicio: <b>{$inicio->format('H:i:s')}</b>"
                . "\n🏁 Fin: <b>{$fin->format('H:i:s')}</b>"
                . "\n⏱️ Duración: <b>{$duracion}</b> segundos";

            $this->notificarTelegram($clienteNombre, $resumenTelegram);

            // Respuesta JSON
            return response()->json([
                'mensaje' => 'Sincronización completa.',
                'total_sirett' => count($productosSirett),
                'total_woocommerce' => $productosWoo->count(),
                'total_creados' => count($creados),
                'total_actualizados' => count($actualizados),
                'total_omitidos' => count($omitidos),
                'total_fallidos_categoria' => count($fallidosPorCategoria),
                'creados' => $creados,
                'actualizados' => $actualizados,
                'omitidos' => $omitidos,
                'fallidos_categoria' => $fallidosPorCategoria,
                'lotes_enviados' => count($resultados),
                'resultado_lotes' => $resultados,

                // Extras de conciliación
                'total_solo_woocommerce' => $soloWoo->count(),
                'solo_woocommerce' => $soloWoo,                 // SKUs
                'solo_woocommerce_detalle' => $soloWooDetalle,          // id, sku, name, status
                'total_woo_sin_sku' => $wooSinSku->count(),
                'woo_sin_sku_ids' => $wooSinSku->pluck('id')->values(),
                'total_stock_cero_sirett' => $stockCeroSirett->count(),
                'stock_cero_sirett' => $stockCeroSirett,

                // Gestión sin SKU
                'woo_sin_sku_action' => $WOO_NO_SKU_ACTION,
                'woo_sin_sku_processed' => $gestionWooSinSku['procesados'],
                'woo_sin_sku_moved_ids' => $gestionWooSinSku['moved_ids'],
                'woo_sin_sku_deleted_ids' => $gestionWooSinSku['deleted_ids'],
                'woo_sin_sku_errors' => $gestionWooSinSku['errores'],

                'categorias_no_creadas' => array_unique($categoriasFallidas),
            ]);

        } catch (\Throwable $e) {
            $this->notificarErrorTelegram($clienteNombre, 'Excepción inesperada: ' . $e->getMessage());
            Log::error("❌ Excepción no controlada: " . $e->getMessage());
            return response()->json(['error' => 'Excepción no controlada', 'detalle' => $e->getMessage()], 500);
        }
    }


    public function deleteAllWooProductsAndCategories(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

        if (!$cred) {
            return response()->json(['error' => 'Credenciales no encontradas'], 404);
        }

        $eliminadosProductos = [];
        $eliminadosCategorias = [];

        // 1️⃣ Eliminar productos en lotes
        $productos = collect();
        $page = 1;

        do {
            $response = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/products', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed())
                break;

            $batch = collect($response->json());
            $productos = $productos->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        $chunks = $productos->pluck('id')->chunk(100);

        foreach ($chunks as $chunk) {
            $payload = ['delete' => $chunk->map(fn($id) => ['id' => $id])->values()];
            $res = Http::withBasicAuth($cred->user, $cred->password)
                ->post($cred->base_url . '/products/batch', ['delete' => $chunk]);

            $eliminadosProductos[] = [
                'lote' => $chunk->count(),
                'status' => $res->status(),
                'success' => $res->successful(),
                'response' => $res->json(),
            ];
        }

        // 2️⃣ Eliminar categorías (excepto "Uncategorized")
        $page = 1;
        do {
            $response = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/products/categories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed())
                break;

            $categorias = collect($response->json());

            foreach ($categorias as $categoria) {
                if ($categoria['id'] == 1 || strtolower($categoria['name']) === 'uncategorized') {
                    continue;
                }

                $delete = Http::withBasicAuth($cred->user, $cred->password)
                    ->delete($cred->base_url . '/products/categories/' . $categoria['id'], [
                        'force' => true,
                    ]);

                $eliminadosCategorias[] = [
                    'id' => $categoria['id'],
                    'nombre' => $categoria['name'],
                    'status' => $delete->status(),
                    'success' => $delete->successful(),
                ];
            }

            $page++;
        } while ($categorias->count() > 0);

        return response()->json([
            'mensaje' => 'Todos los productos y categorías fueron eliminados correctamente',
            'productos_eliminados' => count($productos),
            'lotes_productos' => count($chunks),
            'detalles_productos' => $eliminadosProductos,
            'categorias_eliminadas' => count($eliminadosCategorias),
            'detalles_categorias' => $eliminadosCategorias,
        ]);
    }

    public function deleteAllWooProductsCategoriesAndImages(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

        if (!$cred) {
            return response()->json(['error' => 'Credenciales no encontradas'], 404);
        }

        $eliminadosProductos = [];
        $eliminadosCategorias = [];
        $eliminadasImagenes = [];

        // 1️⃣ Obtener todos los productos
        $productos = collect();
        $page = 1;

        do {
            $response = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/products', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed())
                break;

            $batch = collect($response->json());
            $productos = $productos->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        // 2️⃣ Recolectar media_ids asociados a productos
        $mediaIds = $productos
            ->pluck('images')
            ->flatten(1)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        // 3️⃣ Eliminar productos por lote
        $chunks = $productos->pluck('id')->chunk(100);

        foreach ($chunks as $chunk) {
            $res = Http::withBasicAuth($cred->user, $cred->password)
                ->post("{$cred->base_url}/products/batch", ['delete' => $chunk]);

            $eliminadosProductos[] = [
                'lote' => $chunk->count(),
                'status' => $res->status(),
                'success' => $res->successful(),
                'response' => $res->json(),
            ];
        }

        // 4️⃣ Eliminar imágenes individualmente (del WP Media Library)
        foreach ($mediaIds as $id) {
            $res = Http::withBasicAuth($cred->user, $cred->password)
                ->delete($cred->base_url . "/media/{$id}", [
                    'force' => true
                ]);

            $eliminadasImagenes[] = [
                'media_id' => $id,
                'status' => $res->status(),
                'success' => $res->successful(),
            ];
        }

        // 5️⃣ Eliminar categorías excepto la "uncategorized"
        $page = 1;
        do {
            $response = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/products/categories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed())
                break;

            $categorias = collect($response->json());

            foreach ($categorias as $categoria) {
                if ($categoria['id'] == 1 || strtolower($categoria['name']) === 'uncategorized') {
                    continue;
                }

                $delete = Http::withBasicAuth($cred->user, $cred->password)
                    ->delete($cred->base_url . '/products/categories/' . $categoria['id'], [
                        'force' => true,
                    ]);

                $eliminadosCategorias[] = [
                    'id' => $categoria['id'],
                    'nombre' => $categoria['name'],
                    'status' => $delete->status(),
                    'success' => $delete->successful(),
                ];
            }

            $page++;
        } while ($categorias->count() > 0);

        return response()->json([
            'mensaje' => 'Todos los productos, imágenes y categorías fueron eliminados correctamente',
            'productos_eliminados' => $productos->count(),
            'imagenes_eliminadas' => $mediaIds->count(),
            'categorias_eliminadas' => count($eliminadosCategorias),
            'lotes_productos' => count($chunks),
            'detalles_productos' => $eliminadosProductos,
            'detalles_imagenes' => $eliminadasImagenes,
            'detalles_categorias' => $eliminadosCategorias,
        ]);
    }










    public function verificarPermisosWooImages(string $clienteNombre)
    {
        try {
            $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

            if (!$cred) {
                return response()->json(['error' => 'Credenciales no encontradas'], 404);
            }

            // 🔍 Intentar obtener lista de medios
            $resLista = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/wp/v2/media', [
                    'per_page' => 5,
                ]);

            if ($resLista->status() === 403 || $resLista->status() === 401) {
                return response()->json([
                    'estado' => '❌ Sin permisos para listar medios (imágenes)',
                    'codigo_http' => $resLista->status(),
                    'mensaje' => $resLista->json(),
                ]);
            }

            $imagenes = $resLista->json();

            if (empty($imagenes)) {
                return response()->json([
                    'estado' => '✅ Puedes listar medios, pero no hay imágenes actualmente',
                ]);
            }

            // 🔥 Intentar eliminar una imagen (solo prueba)
            $imagenId = $imagenes[0]['id'];
            $resDelete = Http::withBasicAuth($cred->user, $cred->password)
                ->delete($cred->base_url . "/wp/v2/media/{$imagenId}", [
                    'force' => true,
                ]);

            return response()->json([
                'estado' => $resDelete->successful() ? '✅ Permiso para eliminar imágenes confirmado' : '❌ No se pudo eliminar imagen',
                'imagen_procesada' => $imagenId,
                'codigo_http' => $resDelete->status(),
                'respuesta' => $resDelete->json(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Excepción capturada',
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
            ], 500);
        }
    }






    public function telegram(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'telegram');

        if (!$cred)
            return response()->json(['error' => 'Credenciales no encontradas'], 404);

        $res = Http::post("{$cred->base_url}/bot{$cred->user}/sendMessage", [
            'chat_id' => $cred->extra,
            'text' => "Hola desde Laravel, cliente: {$clienteNombre} v2",
        ]);

        return response()->json([
            'status' => 'Mensaje enviado a Telegram',
            'telegram_response' => $res->json()
        ]);
    }



    private function notificarTelegram(string $clienteNombre, string $mensaje)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'telegram');

        if (!$cred) {
            Log::warning("⚠️ Credenciales de Telegram no encontradas para {$clienteNombre}");
            return;
        }

        try {
            Log::debug("📡 Enviando mensaje a Telegram: base_url={$cred->base_url}, token={$cred->user}, chat_id={$cred->extra}");

            $response = Http::post("{$cred->base_url}/bot{$cred->user}/sendMessage", [
                'chat_id' => $cred->extra,
                'text' => $mensaje,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::error("❌ Error Telegram ({$response->status()}): " . $response->body());
            } else {
                Log::info("✅ Telegram enviado: {$mensaje}");
            }
        } catch (\Throwable $e) {
            Log::error("❌ Excepción Telegram: " . $e->getMessage());
        }
    }





    private function notificarErrorTelegram(string $clienteNombre, string $error)
    {
        $mensaje = "❌ <b>Error sincronizando productos para {$clienteNombre}</b>\n"
            . "🧨 Detalle: <code>" . e($error) . "</code>";

        $this->notificarTelegram($clienteNombre, $mensaje);
    }






    public function woocommerce(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

        if (!$cred)
            return response()->json(['error' => 'Credenciales no encontradas'], 404);

        $response = Http::withBasicAuth($cred->user, $cred->password)
            ->get($cred->base_url . '/products');

        return response()->json($response->json());
    }
    public function woocommerceCategories(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

        if (!$cred) {
            return response()->json(['error' => 'Credenciales no encontradas'], 404);
        }

        $response = Http::withBasicAuth($cred->user, $cred->password)
            ->get($cred->base_url . '/products/categories');

        if ($response->failed()) {
            return response()->json(['error' => 'Error al conectar con WooCommerce'], 500);
        }

        return response()->json($response->json());
    }
    public function deleteAllCategories(string $clienteNombre)
    {
        $cred = ApiConnector::getCredentials($clienteNombre, 'woocommerce');

        if (!$cred) {
            return response()->json(['error' => 'Credenciales no encontradas'], 404);
        }

        $resultados = [];
        $page = 1;

        do {
            // Obtener 100 categorías por página
            $response = Http::withBasicAuth($cred->user, $cred->password)
                ->get($cred->base_url . '/products/categories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener categorías en página ' . $page], 500);
            }

            $categorias = $response->json();

            // Eliminar cada categoría individualmente
            foreach ($categorias as $categoria) {

                if ($categoria['id'] == 1 || strtolower($categoria['name']) === 'Uncategorized') {
                    continue; // ❌ No intentamos eliminar la categoría por defecto
                }

                $delete = Http::withBasicAuth($cred->user, $cred->password)
                    ->delete($cred->base_url . '/products/categories/' . $categoria['id'], [
                        'force' => true,
                    ]);

                $resultados[] = [
                    'categoria_id' => $categoria['id'],
                    'nombre' => $categoria['name'],
                    'status' => $delete->status(),
                    'success' => $delete->successful(),
                ];
            }

            $page++;
        } while (count($categorias) > 0); // Mientras sigan habiendo categorías...

        return response()->json([
            'mensaje' => 'Todas las categorías fueron eliminadas',
            'total_eliminadas' => count($resultados),
            'detalles' => $resultados
        ]);
    }







    // ---- Helpers de texto ----
    private function normalizeText(?string $s): string
    {
        if ($s === null)
            return '';
        // 1) Unificar encoding y entidades HTML
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // &amp; -> &
        $s = strip_tags($s);

        // 2) Reemplazar espacios raros y colapsar
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D"], ' ', $s); // nbsp, ZWSP, etc.
        $s = preg_replace('/\s+/u', ' ', $s);

        // 3) Recortes
        $s = trim($s);

        // 4) Normalización Unicode (NFC). Si intl no está, se omite en silencio.
        if (class_exists(\Normalizer::class) && \Normalizer::isNormalized($s, \Normalizer::FORM_C) === false) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }

        return $s;
    }

    /**
     * Regresa info del primer char distinto (índice, códigos, contexto)
     */
    private function firstDiff(string $a, string $b): array
    {
        $lenA = mb_strlen($a, 'UTF-8');
        $lenB = mb_strlen($b, 'UTF-8');
        $min = min($lenA, $lenB);

        for ($i = 0; $i < $min; $i++) {
            $ca = mb_substr($a, $i, 1, 'UTF-8');
            $cb = mb_substr($b, $i, 1, 'UTF-8');
            if ($ca !== $cb) {
                $ctxA = mb_substr($a, max(0, $i - 5), 10, 'UTF-8');
                $ctxB = mb_substr($b, max(0, $i - 5), 10, 'UTF-8');
                return [
                    'index' => $i,
                    'char_a' => $ca,
                    'char_b' => $cb,
                    'ord_a' => strtoupper(bin2hex($ca)),
                    'ord_b' => strtoupper(bin2hex($cb)),
                    'ctx_a' => $ctxA,
                    'ctx_b' => $ctxB,
                ];
            }
        }

        return [
            'index' => $lenA === $lenB ? -1 : $min, // -1 = iguales, si no, divergen por longitud
            'char_a' => $lenA > $min ? mb_substr($a, $min, 1, 'UTF-8') : '',
            'char_b' => $lenB > $min ? mb_substr($b, $min, 1, 'UTF-8') : '',
            'ord_a' => $lenA > $min ? strtoupper(bin2hex(mb_substr($a, $min, 1, 'UTF-8'))) : '',
            'ord_b' => $lenB > $min ? strtoupper(bin2hex(mb_substr($b, $min, 1, 'UTF-8'))) : '',
            'ctx_a' => mb_substr($a, max(0, $min - 5), 10, 'UTF-8'),
            'ctx_b' => mb_substr($b, max(0, $min - 5), 10, 'UTF-8'),
        ];
    }

    /**
     * Arma un reporte detallado por campo
     */
    private function fieldDiffReport(string $label, string $old, string $new): array
    {
        $normOld = $this->normalizeText($old);
        $normNew = $this->normalizeText($new);
        $equal = ($normOld === $normNew);

        $diff = $this->firstDiff($normOld, $normNew);

        return [
            'campo' => $label,
            'igual' => $equal,
            'old_raw' => $old,
            'new_raw' => $new,
            'old_norm' => $normOld,
            'new_norm' => $normNew,
            'len_old_norm' => mb_strlen($normOld, 'UTF-8'),
            'len_new_norm' => mb_strlen($normNew, 'UTF-8'),
            'first_diff' => $diff, // index, char_a/b, ord_a/b, ctx_a/b
        ];
    }







    private function normalizeSpaces(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s));
    }

    // // 🔑 Clave de comparación (siempre minúsculas)
    // private function categoryKey(?string $name): string
    // {
    //     return mb_strtolower($this->normalizeSpaces($name ?? ''), 'UTF-8');
    // }

    // // 🏷️ Formato visible “Oración”: primera letra mayúscula, resto minúsculas
    // private function categoryDisplay(?string $name): string
    // {
    //     $s = mb_strtolower($this->normalizeSpaces($name ?? ''), 'UTF-8');
    //     if ($s === '')
    //         return $s;
    //     return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    // }

    // // 🔗 Slug siempre minúsculas
    // private function categorySlug(?string $name): string
    // {
    //     // Opcional: podrías basarlo en la clave (100% minúsculas)
    //     return Str::slug($this->categoryKey($name));
    // }

    private function ensureCategoryExists($credWoo, string $nameVisible, array &$categoriasMap, array &$slugExistentes): ?int
    {
        $keyDeseado = $this->categoryKey($nameVisible);
        if (isset($categoriasMap[$keyDeseado])) {
            return $categoriasMap[$keyDeseado];
        }

        $slugDeseado = $this->categorySlug($nameVisible);
        $slugFinal = isset($slugExistentes[$slugDeseado]) ? $slugDeseado . '-' . uniqid() : $slugDeseado;

        $res = Http::retry(3, 2000)
            ->withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(120)
            ->post("{$credWoo->base_url}/products/categories", [
                'name' => $this->categoryDisplay($nameVisible),
                'slug' => $slugFinal,
            ]);

        if ($res->failed()) {
            Log::warning("❌ No se pudo crear la categoría especial '{$nameVisible}': " . $res->body());
            return null;
        }

        $id = $res->json('id');
        $categoriasMap[$keyDeseado] = $id;
        $slugExistentes[$slugFinal] = $id;
        Log::info("🆕 Categoría especial creada #{$id} '{$nameVisible}'");
        return $id;
    }






}
