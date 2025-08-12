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



    public function syncSirettCategoriesToWoo(string $clienteNombre, Request $request)
    {
        // === COMPARACIÓN ESTRICTA (sin acentos) ===
        $normalize = function (?string $s): string {
            $s = is_string($s) ? $s : '';
            // minúsculas + colapsar espacios
            $s = Str::of($s)->lower()->squish()->toString();
            // quitar acentos/tildes (sin cambiar el texto original en Woo/SiReTT, solo para COMPARAR)
            $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($noAcc !== false && $noAcc !== null)
                $s = $noAcc;
            // normalizar espacios
            $s = preg_replace('/\s+/', ' ', $s);
            return trim($s);
        };

        // === Zona horaria a nivel de sesión MySQL (timestamps por defecto) ===
        try {
            DB::statement("SET time_zone = '-06:00'");
            Log::info('MySQL time_zone establecido', ['tz' => '-06:00', 'cliente' => $clienteNombre]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo establecer time_zone de la sesión MySQL', ['error' => $e->getMessage(), 'cliente' => $clienteNombre]);
        }

        $LOG_SAMPLE_LIMIT = 60;
        $inicio = now('America/Managua');
        $sync = SyncHistory::create([
            'cliente' => $clienteNombre,
            'started_at' => $inicio,
        ]);

        try {
            // === 0) Credenciales ===
            $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
            $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

            Log::info('Credenciales detectadas', [
                'cliente' => $clienteNombre,
                'woo_base_url' => $credWoo->base_url ?? null,
                'sirett_base_url' => $credSirett->base_url ?? null,
                'sirett_user_set' => !empty($credSirett->user),
                'sirett_pass_set' => !empty($credSirett->password),
                'sirett_bid' => $credSirett->extra ?? null,
            ]);

            if (!$credWoo || !$credSirett) {
                Log::error('Credenciales faltantes para WooCommerce o SiReTT', ['cliente' => $clienteNombre]);
                return response()->json(['error' => 'Credenciales no encontradas para WooCommerce o SiReTT'], 404);
            }

            // ------------------------------------------------------------------
// === 1) SiReTT: obtener ITEMS (productos) — robusto ===
// ------------------------------------------------------------------
            $items = [];
            $sirettDiag = [
                'attempts' => [],
                'wsdl_functions' => [],
            ];

            try {
                $wsdl = rtrim($credSirett->base_url ?? '', '/') . '?wsdl';
                Log::info('Conectando a SiReTT SOAP', ['wsdl' => $wsdl, 'cliente' => $clienteNombre]);

                $soapOpts = [
                    'trace' => 1,
                    'exceptions' => true,
                    'connection_timeout' => 90,
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'soap_version' => SOAP_1_1,
                    'encoding' => 'UTF-8',
                    'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                    'stream_context' => stream_context_create([
                        'http' => ['user_agent' => 'PHP-SOAP/CategorySync'],
                    ]),
                ];
                $client = new \SoapClient($wsdl, $soapOpts);

                // Loguea métodos disponibles (firma exacta)
                try {
                    $funcs = $client->__getFunctions();
                    $sirettDiag['wsdl_functions'] = $funcs;
                    Log::debug('SiReTT __getFunctions()', ['methods' => $funcs]);
                } catch (\Throwable $e) {
                    Log::debug('__getFunctions() falló', ['error' => $e->getMessage()]);
                }

                // Helpers de parseo
                $parseToArray = function ($resp) {
                    // 1) stdClass/array -> array
                    $arr = json_decode(json_encode($resp), true);

                    // 2) Si trae data como array directo
                    if (isset($arr['data']) && is_array($arr['data'])) {
                        return $arr['data'];
                    }

                    // 3) Si trae data como string (JSON o XML)
                    if (isset($arr['data']) && is_string($arr['data'])) {
                        $s = trim($arr['data']);
                        // JSON
                        if ($s !== '' && ($s[0] === '{' || $s[0] === '[')) {
                            $j = json_decode($s, true);
                            if (is_array($j)) {
                                // algunos devuelven { data: [...] } otra vez
                                if (isset($j['data']) && is_array($j['data']))
                                    return $j['data'];
                                return $j;
                            }
                        }
                        // XML (a veces)
                        if (stripos($s, '<') === 0) {
                            try {
                                $xml = @simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NOCDATA);
                                if ($xml) {
                                    $json = json_decode(json_encode($xml), true);
                                    // Busca un nodo que luzca como lista de items
                                    foreach (['items', 'item', 'rows', 'row', 'Data', 'data'] as $k) {
                                        if (isset($json[$k])) {
                                            return is_array($json[$k]) ? $json[$k] : [$json[$k]];
                                        }
                                    }
                                    return is_array($json) ? $json : [];
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    }

                    // 4) Algunos RPC devuelven <MethodResult>...
                    foreach ($arr as $k => $v) {
                        if (is_string($k) && str_ends_with(strtolower($k), 'result')) {
                            if (is_string($v)) {
                                $s = trim($v);
                                if ($s !== '' && ($s[0] === '{' || $s[0] === '[')) {
                                    $j = json_decode($s, true);
                                    if (isset($j['data']) && is_array($j['data']))
                                        return $j['data'];
                                    if (is_array($j))
                                        return $j;
                                }
                            } elseif (is_array($v)) {
                                if (isset($v['data']) && is_array($v['data']))
                                    return $v['data'];
                                return $v;
                            }
                        }
                    }

                    // 5) fallback
                    return isset($arr['data']) && is_array($arr['data']) ? $arr['data'] : [];
                };

                $saveRaw = function (\SoapClient $client, string $label, int $syncId) {
                    try {
                        $req = $client->__getLastRequest();
                        $res = $client->__getLastResponse();
                        $base = "sirett_{$syncId}_" . $label;
                        @file_put_contents(storage_path("logs/{$base}_request.xml"), $req);
                        @file_put_contents(storage_path("logs/{$base}_response.xml"), $res);
                        return [
                            'request_bytes' => is_string($req) ? strlen($req) : null,
                            'response_bytes' => is_string($res) ? strlen($res) : null,
                            'raw_files' => ["{$base}_request.xml", "{$base}_response.xml"],
                        ];
                    } catch (\Throwable $e) {
                        return ['raw_error' => $e->getMessage()];
                    }
                };

                $user = (string) $credSirett->user;
                $pass = (string) $credSirett->password;
                $bid = (string) ($credSirett->extra ?? '0');

                $struct = ['ws_pid' => $user, 'ws_passwd' => $pass, 'bid' => $bid];
                $positional = [$user, $pass, $bid];
                $soapParams = [
                    new \SoapParam($user, 'ws_pid'),
                    new \SoapParam($pass, 'ws_passwd'),
                    new \SoapParam($bid, 'bid'),
                ];

                // Intentos: dos nombres de método x cuatro firmas
                $methodNames = ['wsp_request_items', 'wsp_request_bodega_all_items'];
                $attempts = [];

                foreach ($methodNames as $m) {
                    $attempts[] = ['label' => "{$m}_struct_array", 'call' => fn() => $client->__soapCall($m, [$struct])];
                    $attempts[] = ['label' => "{$m}_positional_array", 'call' => fn() => $client->__soapCall($m, [$positional])];
                    $attempts[] = ['label' => "{$m}_soapparams", 'call' => fn() => $client->__soapCall($m, $soapParams)];
                    $attempts[] = ['label' => "{$m}_direct_struct", 'call' => fn() => $client->$m($struct)];
                }

                foreach ($attempts as $a) {
                    $t0 = microtime(true);
                    $label = $a['label'];
                    try {
                        $resp = $a['call']();
                        $elapsed = (microtime(true) - $t0) * 1000.0;
                        $rawInfo = $saveRaw($client, $label, $sync->id);

                        $data = $parseToArray($resp);
                        $count = is_array($data) ? count($data) : 0;

                        $sirettDiag['attempts'][] = [
                            'label' => $label,
                            'ok' => true,
                            'items' => $count,
                            'elapsed_ms' => round($elapsed, 2)
                        ] + $rawInfo;

                        Log::info('SiReTT intento OK', ['label' => $label, 'items' => $count, 'elapsed_ms' => round($elapsed, 2)]);

                        if ($count > 0) {
                            $items = $data;
                            break;
                        }
                    } catch (\Throwable $ex) {
                        $elapsed = (microtime(true) - $t0) * 1000.0;
                        $rawInfo = $saveRaw($client, $label . '_ERR', $sync->id);

                        $sirettDiag['attempts'][] = [
                            'label' => $label,
                            'ok' => false,
                            'error' => $ex->getMessage(),
                            'elapsed_ms' => round($elapsed, 2)
                        ] + $rawInfo;

                        Log::warning('SiReTT intento fallido', ['label' => $label, 'error' => $ex->getMessage(), 'elapsed_ms' => round($elapsed, 2)]);
                    }
                }

                // Muestra de campos clave para validar que realmente vienen productos
                if (is_array($items) && count($items) > 0) {
                    Log::debug('SiReTT items (sample familia/codigo/descripcion)', [
                        'cliente' => $clienteNombre,
                        'sample' => array_map(function ($row) {
                            return [
                                'familia' => $row['familia'] ?? null,
                                'codigo' => $row['codigo'] ?? ($row['item_code'] ?? null),
                                'descripcion' => $row['descripcion'] ?? ($row['name'] ?? null),
                            ];
                        }, array_slice($items, 0, min(60, count($items)))),
                    ]);
                }

            } catch (\Throwable $e) {
                Log::error('Error general al conectar/leer SiReTT', [
                    'cliente' => $clienteNombre,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'error' => 'Error al conectar con SiReTT',
                    'detalle' => $e->getMessage()
                ], 500);
            }

            if (!is_array($items) || count($items) === 0) {
                // deja rastro útil en la respuesta para depurar rápido
                return response()->json([
                    'error' => 'SiReTT no devolvió productos.',
                    'diagnostico' => [
                        'wsdl' => rtrim($credSirett->base_url ?? '', '/') . '?wsdl',
                        'metodos' => $sirettDiag['wsdl_functions'],
                        'intentos' => $sirettDiag['attempts'],
                        'credenciales_set' => [
                            'user' => !empty($credSirett->user),
                            'pass' => !empty($credSirett->password),
                            'bid' => (string) ($credSirett->extra ?? '0'),
                        ],
                    ],
                ], 502);
            }

            // === 1.b) Familias únicas (TAL CUAL vienen en la clave "familia")
            $familiasSiReTT = collect($items)
                ->map(function ($row) {
                    // SOLO aceptar 'familia'; si no existe o no es string, se ignora
                    $fam = $row['familia'] ?? null;
                    return is_string($fam) ? trim($fam) : '';
                })
                ->filter(fn($v) => $v !== '')
                ->unique()
                ->values();

            // Mapa normalizado -> nombre original EXACTO de SiReTT
            $familiasMapNormToOriginal = $familiasSiReTT
                ->mapWithKeys(fn($f) => [$normalize($f) => $f])
                ->all();

            Log::info('SiReTT: familias únicas (por nombre original)', [
                'cliente' => $clienteNombre,
                'total' => $familiasSiReTT->count(),
                'sample' => $familiasSiReTT->take($LOG_SAMPLE_LIMIT)->values()->all(),
            ]);

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
                        'page' => $page,
                        'orderby' => 'id',
                        'order' => 'asc'
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
                'total' => $categoriasWoo->count(),
                'sample' => $categoriasWoo->take($LOG_SAMPLE_LIMIT)->map(function ($c) use ($normalize) {
                    return [
                        'id' => $c['id'] ?? null,
                        'name' => $c['name'] ?? null,
                        'name_norm' => $normalize($c['name'] ?? ''),
                        'slug' => $c['slug'] ?? null,
                        'parent' => (int) ($c['parent'] ?? 0),
                        'count' => (int) ($c['count'] ?? 0),
                    ];
                })->values()->all(),
            ]);

            // Índices: por nombre NORMALIZADO (sin acentos)
            $wooByNormName = $categoriasWoo->keyBy(fn($c) => $normalize($c['name'] ?? ''));

            // ------------------------------------------------------------------
            // === 3) BASELINE LOCAL: upsert de TODAS las categorías con match EXACTO (sin acentos)
            // ------------------------------------------------------------------
            foreach ($categoriasWoo as $cat) {
                $name = $cat['name'] ?? '';
                $norm = $normalize($name);
                $familiaMatch = $familiasMapNormToOriginal[$norm] ?? null; // SOLO exacto sin acentos

                $this->ensureCatSync($clienteNombre, $familiaMatch, [
                    'id' => $cat['id'] ?? null,
                    'name' => $name,
                    'slug' => $cat['slug'] ?? null,
                    'parent' => (int) ($cat['parent'] ?? 0),
                    'count' => (int) ($cat['count'] ?? 0),
                ]);
            }

            // ------------------------------------------------------------------
            // === 4) PRE-LIMPIEZA: eliminar duplicadas con count==0 (mismo nombre sin acentos)
            // ------------------------------------------------------------------
            $byId = $categoriasWoo->keyBy('id');
            $childrenByParent = $categoriasWoo->groupBy(fn($c) => (int) ($c['parent'] ?? 0));
            $groups = $categoriasWoo->groupBy(fn($c) => $normalize($c['name'] ?? ''));

            $dup_groups = 0;
            $dup_candidates = 0;
            $toDelete = collect();

            foreach ($groups as $key => $list) {
                if ($key === '' || $list->count() <= 1)
                    continue;

                $dup_groups++;
                $keep = $list->sortBy([
                    fn($a, $b) => ($b['count'] <=> $a['count']),
                    fn($a, $b) => ($a['id'] <=> $b['id']),
                ])->first();

                $list->each(function ($c) use ($keep, &$toDelete, &$dup_candidates) {
                    if ($c['id'] === $keep['id'])
                        return;
                    if ((int) ($c['count'] ?? 0) === 0) {
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
                $max = 10;
                while ($toDelete->isNotEmpty() && $passes < $max) {
                    $passes++;
                    $set = $toDelete->flip();

                    $leafIds = $toDelete->filter(function ($id) use ($childrenByParent, $set) {
                        $children = $childrenByParent->get($id, collect());
                        foreach ($children as $child) {
                            if (isset($set[$child['id']]))
                                return false;
                        }
                        return true;
                    })->values();

                    if ($leafIds->isEmpty())
                        break;

                    foreach ($leafIds as $id) {
                        $orig = $byId[$id] ?? null;

                        $del = Http::retry(2, 1500)
                            ->withBasicAuth($credWoo->user, $credWoo->password)
                            ->timeout(60)
                            ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

                        if ($del->successful()) {
                            $deleted[] = $id;

                            SyncHistoryDetail::create([
                                'sync_history_id' => $sync->id,
                                'sku' => "CAT:{$id}",
                                'tipo' => 'categoria_eliminada',
                                'datos_anteriores' => [
                                    'id' => $id,
                                    'name' => $orig['name'] ?? null,
                                    'slug' => $orig['slug'] ?? null,
                                    'count' => (int) ($orig['count'] ?? 0),
                                ],
                                'datos_nuevos' => [],
                                'deltas' => [],
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
                    'cliente' => $clienteNombre,
                    'grupos_dup' => $dup_groups,
                    'candidatas_dup' => $dup_candidates,
                    'eliminadas' => count($deleted),
                    'errores' => $deleteErrors,
                ]);
            }

            // Resumen por categoría (post-limpieza)
            $productosPorCategoria = $categoriasWoo->map(fn($c) => [
                'id' => $c['id'],
                'name' => $c['name'] ?? '',
                'slug' => $c['slug'] ?? '',
                'count' => (int) ($c['count'] ?? 0),
            ])->values();

            // Recalcular índices
            $wooByNormName = $categoriasWoo->keyBy(fn($c) => $normalize($c['name'] ?? ''));
            $slugExistente = [];
            foreach ($categoriasWoo as $cat) {
                $slug = $cat['slug'] ?? '';
                if ($slug !== '')
                    $slugExistente[$slug] = $cat['id'];
            }

            // ------------------------------------------------------------------
            // === 5) (REMOVIDO) Renombrado: NO se renombra nada
            // ------------------------------------------------------------------

            // ------------------------------------------------------------------
            // === 6) Asegurar tracking local para familias SiReTT que YA existen en Woo (match EXACTO sin acentos)
            // ------------------------------------------------------------------
            $familiasYaEnWoo = $familiasSiReTT->filter(fn($f) => $wooByNormName->has($normalize($f)));
            Log::info('Familias SiReTT que ya existen en Woo (match exacto sin acentos)', [
                'cliente' => $clienteNombre,
                'total' => $familiasYaEnWoo->count(),
                'sample' => $familiasYaEnWoo->take(30)->values()->all(),
            ]);

            foreach ($familiasYaEnWoo as $familia) {
                $wooCat = $wooByNormName->get($normalize($familia));
                $wooArr = is_array($wooCat) ? $wooCat : (array) $wooCat;

                $this->ensureCatSync($clienteNombre, $familia, [
                    'id' => $wooArr['id'] ?? null,
                    'name' => $wooArr['name'] ?? '',
                    'slug' => $wooArr['slug'] ?? null,
                    'parent' => (int) ($wooArr['parent'] ?? 0),
                    'count' => (int) ($wooArr['count'] ?? 0),
                ]);
            }

            // ------------------------------------------------------------------
            // === 7) Crear categorías faltantes desde familias SiReTT (nombre EXACTO de SiReTT)
            // ------------------------------------------------------------------
            $familiasParaCrear = $familiasSiReTT
                ->map(fn($f) => [
                    'original' => $f,
                    'norm' => $normalize($f),
                    'slug' => \Illuminate\Support\Str::slug($f), // slug estándar, nombre intacto
                ])
                ->filter(fn($row) => !$wooByNormName->has($row['norm']))
                ->values();

            Log::info('Familias SiReTT que requieren creación en Woo', [
                'cliente' => $clienteNombre,
                'total' => $familiasParaCrear->count(),
                'sample' => $familiasParaCrear->take(40)->values()->all(),
            ]);

            $creadas = [];
            $erroresCreacion = [];

            if ($familiasParaCrear->isNotEmpty()) {
                foreach ($familiasParaCrear->chunk(100) as $chunkIndex => $chunk) {
                    $expectedBySlug = [];

                    $createPayload = $chunk->map(function ($row) use (&$slugExistente, &$expectedBySlug) {
                        $slugFinal = $row['slug'] !== '' ? $row['slug'] : 'cat-' . uniqid();
                        if (isset($slugExistente[$slugFinal])) {
                            $slugFinal = $slugFinal . '-' . uniqid();
                        }
                        $slugExistente[$slugFinal] = -1; // reservar
                        $expectedBySlug[$slugFinal] = ['familia' => $row['original']];
                        return ['name' => $row['original'], 'slug' => $slugFinal]; // nombre EXACTO de SiReTT
                    })->values()->all();

                    Log::debug('Payload creación Woo desde familias SiReTT (chunk)', [
                        'cliente' => $clienteNombre,
                        'chunk' => $chunkIndex,
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
                                'id' => $cat['id'] ?? null,
                                'name' => $cat['name'] ?? null,
                                'slug' => $cat['slug'] ?? null
                            ];

                            $slugCreated = $cat['slug'] ?? null;
                            $familia = ($slugCreated && isset($expectedBySlug[$slugCreated]))
                                ? $expectedBySlug[$slugCreated]['familia']
                                : ($cat['name'] ?? null);

                            SyncHistoryDetail::create([
                                'sync_history_id' => $sync->id,
                                'sku' => "CAT:" . ($cat['id'] ?? 'new'),
                                'tipo' => 'categoria_creada',
                                'datos_anteriores' => [],
                                'datos_nuevos' => $cat ?? [],
                                'deltas' => [],
                            ]);

                            $this->ensureCatSync($clienteNombre, $familia, [
                                'id' => $cat['id'] ?? null,
                                'name' => $cat['name'] ?? null,
                                'slug' => $cat['slug'] ?? null,
                                'parent' => (int) ($cat['parent'] ?? 0),
                                'count' => (int) ($cat['count'] ?? 0),
                            ]);
                        }
                    } else {
                        $body = $res->body();
                        $erroresCreacion[] = $body;
                        Log::error('Error creando categorías en Woo desde familias SiReTT', [
                            'cliente' => $clienteNombre,
                            'status' => $res->status(),
                            'body' => $body,
                        ]);
                    }
                }
            }

            // ------------------------------------------------------------------
            // === 7.1) BACKFILL FINAL de familias NULL en tracking local (match EXACTO sin acentos)
            // ------------------------------------------------------------------
            $ahora = now('America/Managua');
            $backfillUpdated = 0;

            CategoriaSincronizada::where('cliente', $clienteNombre)
                ->where(function ($q) {
                    $q->whereNull('familia_sirett')->orWhereNull('familia_sirett_key');
                })
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($normalize, $familiasMapNormToOriginal, $ahora, &$backfillUpdated) {
                    foreach ($rows as $row) {
                        $familia = null;

                        if (!empty($row->nombre)) {
                            $norm = $normalize($row->nombre);
                            $familia = $familiasMapNormToOriginal[$norm] ?? null;
                        }
                        if (!$familia && !empty($row->slug)) {
                            $norm = $normalize(str_replace('-', ' ', $row->slug));
                            $familia = $familiasMapNormToOriginal[$norm] ?? null;
                        }
                        if (!$familia && !empty($row->key_normalized)) {
                            $norm = $normalize($row->key_normalized);
                            $familia = $familiasMapNormToOriginal[$norm] ?? null;
                        }

                        if ($familia) {
                            $row->familia_sirett = $familia;
                            $row->familia_sirett_key = $normalize($familia);
                            $row->updated_at = $ahora;
                            $row->save();
                            $backfillUpdated++;
                        }
                    }
                });

            Log::info('Backfill familias completado', [
                'cliente' => $clienteNombre,
                'actualizadas' => $backfillUpdated,
            ]);

            // === 8) Cerrar historial ===
            $fin = now('America/Managua');
            $duracion = $inicio->floatDiffInSeconds($fin);

            $sync->update([
                'finished_at' => $fin,
                'total_creados' => count($creadas),
                'total_actualizados' => 0, // NO renombramos
                'total_omitidos' => 0,
                'total_fallidos_categoria' => count($deleteErrors) + count($erroresCreacion),
            ]);

            if (method_exists($this, 'notificarTelegram')) {
                $msg = "🗂 <b>Sync Categorías</b> <b>{$clienteNombre}</b>\n"
                    . "🧹 Duplicados: grupos={$dup_groups}, candidatas={$dup_candidates}, eliminadas=" . count($deleted) . "\n"
                    . "✏️ Renombradas: <b>0</b>\n"
                    . "🆕 Creadas: <b>" . count($creadas) . "</b>\n"
                    . "⏱️ Duración: <b>" . number_format($duracion, 2) . "s</b>";
                $this->notificarTelegram($clienteNombre, $msg);
            }

            // Totales y lista de categorías con/sin productos en Woo
            $totalConProductos = $categoriasWoo->filter(fn($c) => (int) ($c['count'] ?? 0) > 0)->count();
            $totalSinProductos = $categoriasWoo->filter(fn($c) => (int) ($c['count'] ?? 0) === 0)->count();

            // Lista detallada de categorías sin productos + posible match EXACTO (sin acentos)
            $categoriasSinProductos = $categoriasWoo
                ->filter(fn($c) => (int) ($c['count'] ?? 0) === 0)
                ->map(function ($c) use ($normalize, $familiasMapNormToOriginal) {
                    $norm = $normalize($c['name'] ?? '');
                    $fam = $familiasMapNormToOriginal[$norm] ?? null;
                    $exact = $fam !== null;
                    return [
                        'id' => $c['id'] ?? null,
                        'name' => $c['name'] ?? '',
                        'slug' => $c['slug'] ?? '',
                        'posible_familia_sirett' => $fam,
                        'match_method' => $exact ? 'exact_no_accents' : 'none',
                        'match_score' => null,
                        // Mantengo tu semántica previa (eliminable si hay match exacto)
                        'eliminable' => $exact,
                    ];
                })
                ->sortBy(fn($c) => strtolower($c['name']))
                ->values();

            // Guardar resumen de eliminables
            $categoriasEliminables = $categoriasSinProductos
                ->filter(fn($c) => $c['eliminable'] === true)
                ->values();

            if ($categoriasEliminables->isNotEmpty()) {
                SyncHistoryDetail::create([
                    'sync_history_id' => $sync->id,
                    'sku' => "CAT:ELIMINABLES",
                    'tipo' => 'categorias_sin_productos_eliminables',
                    'datos_anteriores' => [],
                    'datos_nuevos' => [
                        'total_eliminables' => $categoriasEliminables->count(),
                        'categorias' => $categoriasEliminables->all()
                    ],
                    'deltas' => [],
                ]);

                Log::info('Resumen de categorías sin productos eliminables guardado en historial', [
                    'cliente' => $clienteNombre,
                    'total_eliminables' => $categoriasEliminables->count(),
                    'categorias' => $categoriasEliminables->all()
                ]);
            }

            return response()->json([
                'sync_history_id' => $sync->id,
                'mensaje' => 'Baseline local, limpieza de duplicados, backfill de familias y sincronización completada (match exacto sin acentos, sin renombrar).',
                'cliente' => $clienteNombre,
                'duplicados' => [
                    'grupos_encontrados' => $dup_groups,
                    'candidatas_a_borrar' => $dup_candidates,
                    'eliminadas_total' => count($deleted),
                    'eliminadas_ids' => $deleted,
                    'errores_eliminacion' => $deleteErrors,
                ],
                'resumen_categorias_woo' => [
                    'total' => $categoriasWoo->count(),
                    'total_con_productos' => $totalConProductos,
                    'total_sin_productos' => $totalSinProductos,
                    'categorias_sin_productos' => $categoriasSinProductos,
                    'por_categoria' => $productosPorCategoria,
                ],
                'creadas_total' => count($creadas),
                'creadas' => $creadas,
                'total_familias_sirett' => $familiasSiReTT->count(),
                'inicio' => $inicio->format('Y-m-d H:i:s'),
                'fin' => $fin->format('Y-m-d H:i:s'),
                'duracion' => number_format($duracion, 2) . 's',
            ]);

        } catch (\Throwable $e) {
            $fin = now('America/Managua');
            $sync->update([
                'finished_at' => $fin,
                'total_creados' => 0,
                'total_actualizados' => 0,
                'total_omitidos' => 0,
                'total_fallidos_categoria' => 1,
            ]);

            if (method_exists($this, 'notificarErrorTelegram')) {
                $this->notificarErrorTelegram($clienteNombre, 'Excepción categorías: ' . $e->getMessage());
            }

            Log::error('Sync categorías: excepción no controlada', [
                'cliente' => $clienteNombre,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Excepción no controlada',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }



    public function listWooCategories(string $clienteNombre)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo) {
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);
        }

        // 1) Woo: obtener todas las categorías
        $cats = collect();
        $page = 1;
        do {
            $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(60)
                ->get("{$credWoo->base_url}/products/categories", [
                    'per_page' => 100,
                    'page' => $page,
                    'orderby' => 'id',
                    'order' => 'asc'
                ]);
            if ($res->failed()) {
                return response()->json(['error' => 'Woo error', 'detalle' => $res->body()], 500);
            }
            $batch = collect($res->json());
            $cats = $cats->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        // 2) BD local: cargar filas del cliente (solo columnas útiles)
        $localRows = CategoriaSincronizada::where('cliente', $clienteNombre)
            ->whereNotNull('woocommerce_id')
            ->get([
                'id',
                'woocommerce_id',
                'nombre',
                'slug',
                'familia_sirett',
                'familia_sirett_key',
                'key_normalized',
                'productos_woo'
            ]);

        // 3) Key map por woo_id normalizado (int) para tolerar VARCHAR/espacios
        $normalize = fn($v) => (int) preg_replace('/\D+/', '', (string) $v);
        $localByWoo = $localRows->keyBy(fn($r) => $normalize($r->woocommerce_id));

        // 4) Intersección Woo ↔ Local por woocommerce_id
        $data = $cats
            ->filter(fn($c) => $localByWoo->has($normalize($c['id'] ?? null)))
            ->map(function ($c) use ($localByWoo, $normalize) {
                $row = $localByWoo->get($normalize($c['id']));

                return [
                    // Woo
                    'woo_id' => (int) ($c['id'] ?? 0),
                    'woo_name' => $c['name'] ?? '',
                    'woo_slug' => $c['slug'] ?? '',
                    'woo_count' => (int) ($c['count'] ?? 0),
                    'woo_parent' => (int) ($c['parent'] ?? 0),

                    // Local
                    'row_id' => $row->woocommerce_id,
                    'nombre_local' => $row->nombre,
                    'slug_local' => $row->slug,
                    'productos_woo_local' => (int) $row->productos_woo,
                    'familia_sirett' => $row->familia_sirett,
                    'familia_sirett_key' => $row->familia_sirett_key,
                    'key_normalized' => $row->key_normalized,
                ];
            })
            ->values();

        return response()->json([
            'total' => $data->count(),
            'categories' => $data,
        ]);
    }




    public function deleteAllZeroCountCategories(string $clienteNombre)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo) {
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);
        }

        // 1) Traer todas de Woo
        $cats = collect();
        $page = 1;
        do {
            $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->timeout(60)
                ->get("{$credWoo->base_url}/products/categories", [
                    'per_page' => 100,
                    'page' => $page,
                    'orderby' => 'id',
                    'order' => 'asc'
                ]);
            if ($res->failed()) {
                return response()->json(['error' => 'Woo error', 'detalle' => $res->body()], 500);
            }
            $batch = collect($res->json());
            $cats = $cats->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        $childrenByParent = $cats->groupBy(fn($c) => (int) ($c['parent'] ?? 0));
        $toDelete = $cats->filter(fn($c) => (int) ($c['count'] ?? 0) === 0)->pluck('id')->values();

        $deletedDb = [];      // primero BD
        $deletedWoo = [];     // luego Woo
        $errors = [];
        $passes = 0;
        $max = 10;

        while ($toDelete->isNotEmpty() && $passes < $max) {
            $passes++;
            $set = $toDelete->flip();

            $leafIds = $toDelete->filter(function ($id) use ($childrenByParent, $set) {
                $children = $childrenByParent->get($id, collect());
                foreach ($children as $ch) {
                    if (!isset($set[$ch['id']]))
                        return false;
                }
                return true;
            })->values();

            if ($leafIds->isEmpty())
                break;

            foreach ($leafIds as $id) {
                // 2) BD primero
                $dbDeleted = $this->deleteLocalByWooId($clienteNombre, (int) $id);
                if ($dbDeleted === 0) {
                    $errors[] = ['id' => $id, 'stage' => 'db', 'msg' => 'No se eliminó en BD local; se omite Woo'];
                    // NO continuar con Woo
                    $toDelete = $toDelete->reject(fn($x) => $x === $id)->values();
                    continue;
                }
                $deletedDb[] = $id;

                // 3) Intentar eliminar en Woo
                $del = Http::withBasicAuth($credWoo->user, $credWoo->password)
                    ->timeout(60)
                    ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

                if ($del->successful() || $del->status() === 404) {
                    $deletedWoo[] = $id;
                } else {
                    $errors[] = ['id' => $id, 'stage' => 'woo', 'http' => $del->status(), 'body' => $del->body()];
                }

                $toDelete = $toDelete->reject(fn($x) => $x === $id)->values();
            }
        }

        return response()->json([
            'mensaje' => 'Eliminación masiva (BD primero) de categorías con count==0 finalizada',
            'eliminadas_db' => $deletedDb,
            'eliminadas_woo' => $deletedWoo,
            'errores' => $errors,
            'intentadas' => count($deletedDb) + count($errors),
        ]);
    }



    public function deleteOneZeroCountCategory(string $clienteNombre, int $id)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        if (!$credWoo) {
            return response()->json(['error' => 'Credenciales Woo no encontradas'], 404);
        }

        // 1) BD primero
        $dbDeleted = $this->deleteLocalByWooId($clienteNombre, $id);
        if ($dbDeleted === 0) {
            // No continuar con Woo
            return response()->json([
                'error' => 'No se pudo eliminar en la BD local (categoria_sincronizadas). Operación abortada.',
                'woo_id' => $id
            ], 422);
        }

        // 2) Validaciones en Woo (existe / count / hijos)
        $res = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(30)
            ->get("{$credWoo->base_url}/products/categories/{$id}");

        if ($res->status() === 404) {
            return response()->json([
                'mensaje' => 'Categoría ya no existe en Woo; eliminada en BD.',
                'id' => $id,
                'db_rows_deleted' => $dbDeleted,
                'woo' => ['status' => 'not_found', 'stage' => 'get']
            ]);
        }
        if ($res->failed()) {
            return response()->json([
                'error' => 'No se pudo leer categoría en Woo tras borrar en BD.',
                'detalle' => $res->body(),
                'db_rows_deleted' => $dbDeleted,
                'id' => $id
            ], 500);
        }

        $cat = $res->json();
        if ((int) ($cat['count'] ?? 0) !== 0) {
            return response()->json([
                'error' => 'La categoría en Woo tiene productos (count>0). No se elimina en Woo.',
                'id' => $id,
                'db_rows_deleted' => $dbDeleted
            ], 422);
        }

        // hijos
        $h = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(30)
            ->get("{$credWoo->base_url}/products/categories", ['per_page' => 1, 'parent' => $id]);
        if ($h->successful() && collect($h->json())->isNotEmpty()) {
            return response()->json([
                'error' => 'Tiene subcategorías en Woo; elimínalas primero.',
                'id' => $id,
                'db_rows_deleted' => $dbDeleted
            ], 422);
        }

        // 3) Eliminar en Woo
        $del = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->timeout(30)
            ->delete("{$credWoo->base_url}/products/categories/{$id}", ['force' => true]);

        if ($del->status() === 404) {
            return response()->json([
                'mensaje' => 'Woo reporta no encontrada al eliminar; ya eliminada probablemente.',
                'id' => $id,
                'db_rows_deleted' => $dbDeleted,
                'woo' => ['status' => 'not_found', 'stage' => 'delete']
            ]);
        }
        if ($del->failed()) {
            return response()->json([
                'error' => 'Woo error al eliminar (BD ya eliminada).',
                'detalle' => $del->body(),
                'id' => $id,
                'db_rows_deleted' => $dbDeleted
            ], 500);
        }

        return response()->json([
            'mensaje' => 'Categoría eliminada (BD primero, luego Woo).',
            'id' => $id,
            'db_rows_deleted' => $dbDeleted,
            'woo' => ['status' => 'deleted']
        ]);
    }


    private function credWoo(string $cliente)
    {
        return ApiConnector::getCredentials($cliente, 'woocommerce');
    }


    public function deleteOne(string $cliente, int $wooId)
    {
        $cred = $this->credWoo($cliente);
        if (!$cred) {
            return response()->json(['ok' => false, 'msg' => 'Sin credenciales Woo'], 404);
        }

        // 1) BD primero
        $dbDeleted = $this->deleteLocalByWooId($cliente, $wooId);
        if ($dbDeleted === 0) {
            return response()->json([
                'ok' => false,
                'msg' => 'No se pudo eliminar en BD local. Operación abortada.',
                'id' => $wooId
            ], 422);
        }

        // 2) Leer en Woo
        $cat = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories/{$wooId}");

        if ($cat->status() === 404) {
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'get'],
                'db_rows_deleted' => $dbDeleted,
            ]);
        }
        if ($cat->failed()) {
            return response()->json([
                'ok' => false,
                'msg' => 'No se pudo leer categoría en Woo tras borrar en BD.',
                'det' => $cat->body(),
                'db_rows_deleted' => $dbDeleted
            ], 500);
        }

        $catJ = $cat->json();
        if ((int) ($catJ['count'] ?? 0) !== 0) {
            return response()->json([
                'ok' => false,
                'msg' => 'La categoría tiene productos (count>0). No se elimina en Woo.',
                'db_rows_deleted' => $dbDeleted
            ], 422);
        }

        // hijos
        $hijos = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->get("{$cred->base_url}/products/categories", ['per_page' => 1, 'parent' => $wooId]);
        if ($hijos->successful() && collect($hijos->json())->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Tiene subcategorías. Elimínalas primero.',
                'db_rows_deleted' => $dbDeleted
            ], 422);
        }

        // 3) Eliminar en Woo
        $del = Http::withBasicAuth($cred->user, $cred->password)
            ->timeout(30)
            ->delete("{$cred->base_url}/products/categories/{$wooId}", ['force' => true]);

        if ($del->status() === 404) {
            return response()->json([
                'ok' => true,
                'id' => $wooId,
                'woo' => ['status' => 'not_found', 'stage' => 'delete'],
                'db_rows_deleted' => $dbDeleted
            ]);
        }
        if ($del->failed()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Woo error al eliminar (BD ya eliminada).',
                'det' => $del->body(),
                'db_rows_deleted' => $dbDeleted
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $wooId,
            'woo' => ['status' => 'deleted'],
            'db_rows_deleted' => $dbDeleted
        ]);
    }




    // Helper: borrar en BD por woocommerce_id y cliente. Devuelve filas afectadas.
    private function deleteLocalByWooId(string $cliente, int $wooId): int
    {
        return CategoriaSincronizada::where('cliente', $cliente)
            ->where('woocommerce_id', $wooId)
            ->delete();
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




    // public function sincronizarProductosConCategorias(string $clienteNombre)
    // {
    //     // === Comportamiento para productos Woo sin SKU ===
    //     $WOO_NO_SKU_ACTION = 'none';                  // 'move' | 'delete' | 'none'
    //     $WOO_NO_SKU_CATEGORY = 'Pendiente de revisión'; // usada si action = move

    //     try {
    //         $inicio = now('America/Managua');

    //         $sync = SyncHistory::create([
    //             'cliente' => $clienteNombre,
    //             'started_at' => $inicio,
    //         ]);

    //         $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
    //         $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

    //         if (!$credWoo || !$credSirett) {
    //             $this->notificarErrorTelegram($clienteNombre, 'Credenciales no encontradas para WooCommerce o SiReTT.');
    //             return response()->json(['error' => 'Credenciales no encontradas'], 404);
    //         }

    //         // 1) SiReTT
    //         try {
    //             $client = new \SoapClient($credSirett->base_url . '?wsdl', ['trace' => 1, 'exceptions' => true]);
    //             $params = ['ws_pid' => $credSirett->user, 'ws_passwd' => $credSirett->password, 'bid' => $credSirett->extra];
    //             $response = $client->__soapCall('wsp_request_items', $params);
    //             $productosSirett = json_decode(json_encode($response), true)['data'] ?? [];
    //         } catch (\Exception $e) {
    //             $this->notificarErrorTelegram($clienteNombre, 'Error al conectar con SiReTT: ' . $e->getMessage());
    //             return response()->json(['error' => 'Error al conectar con SiReTT', 'detalle' => $e->getMessage()], 500);
    //         }

    //         if (empty($productosSirett)) {
    //             $this->notificarErrorTelegram($clienteNombre, 'No se obtuvieron productos desde SiReTT.');
    //             return response()->json(['error' => 'No se obtuvieron productos desde SiReTT'], 500);
    //         }

    //         file_put_contents(
    //             storage_path("logs/productos_sirett_{$clienteNombre}.json"),
    //             json_encode($productosSirett, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    //         );
    //         Log::info("✅ Total productos recibidos desde SiReTT: " . count($productosSirett));

    //         // 2) Woo productos
    //         $productosWoo = collect();
    //         $page = 1;
    //         do {
    //             $res = Http::retry(3, 2000)
    //                 ->withBasicAuth($credWoo->user, $credWoo->password)
    //                 ->timeout(120)
    //                 ->get("{$credWoo->base_url}/products", ['per_page' => 100, 'page' => $page]);

    //             if ($res->failed())
    //                 break;

    //             $batch = collect($res->json());
    //             $productosWoo = $productosWoo->merge($batch);
    //             $page++;
    //         } while ($batch->count() > 0);

    //         // Evitar colisiones por SKU vacío
    //         $wooPorSku = $productosWoo
    //             ->filter(fn($p) => isset($p['sku']) && is_string($p['sku']) && trim($p['sku']) !== '')
    //             ->keyBy(fn($p) => trim($p['sku']));

    //         // 3) familias únicas SiReTT (referencia)
    //         $familiasSiReTT = collect($productosSirett)
    //             ->pluck('familia')->filter()->unique()
    //             ->map(fn($f) => $this->categoryKey($f))
    //             ->values();

    //         // 4) Woo categorías (traer todas)
    //         $categoriasWoo = collect();
    //         $page = 1;
    //         do {
    //             $res = Http::retry(3, 2000)
    //                 ->withBasicAuth($credWoo->user, $credWoo->password)
    //                 ->timeout(120)
    //                 ->get("{$credWoo->base_url}/products/categories", ['per_page' => 100, 'page' => $page]);

    //             if ($res->failed())
    //                 break;

    //             $batch = collect($res->json());
    //             $categoriasWoo = $categoriasWoo->merge($batch);
    //             $page++;
    //         } while ($batch->count() > 0);

    //         // Índices para comparación y colisiones
    //         $categoriasMap = []; // key normalizado => id
    //         $slugExistentes = []; // slug => id
    //         foreach ($categoriasWoo as $cat) {
    //             $id = $cat['id'];
    //             $name = $cat['name'] ?? '';
    //             $slug = $cat['slug'] ?? '';
    //             $key = $this->categoryKey($name);
    //             $categoriasMap[$key] = $id;
    //             $slugExistentes[$slug] = $id;
    //         }

    //         // (Opcional) Renombrar categorías en Woo (si lo mantienes)
    //         foreach ($categoriasWoo as $cat) {
    //             $id = $cat['id'];
    //             $name = $cat['name'] ?? '';
    //             $slug = $cat['slug'] ?? '';

    //             $nameDeseado = $this->categoryDisplay($name); // “Oración”
    //             $slugDeseado = $this->categorySlug($name);    // minúsculas

    //             $needsRename = ($name !== $nameDeseado) || ($slug !== $slugDeseado);
    //             if (!$needsRename)
    //                 continue;

    //             // Evitar colisiones de slug
    //             $slugFinal = $slugDeseado;
    //             if (isset($slugExistentes[$slugDeseado]) && $slugExistentes[$slugDeseado] !== $id) {
    //                 $slugFinal = $slugDeseado . '-' . $id;
    //             }

    //             $payload = ['name' => $nameDeseado, 'slug' => $slugFinal];

    //             $up = Http::retry(3, 2000)
    //                 ->withBasicAuth($credWoo->user, $credWoo->password)
    //                 ->timeout(120)
    //                 ->put("{$credWoo->base_url}/products/categories/{$id}", $payload);

    //             if ($up->successful()) {
    //                 unset($slugExistentes[$slug]);
    //                 $slugExistentes[$slugFinal] = $id;

    //                 $oldKey = $this->categoryKey($name);
    //                 if (isset($categoriasMap[$oldKey]) && $categoriasMap[$oldKey] === $id) {
    //                     unset($categoriasMap[$oldKey]);
    //                 }
    //                 $categoriasMap[$this->categoryKey($nameDeseado)] = $id;

    //                 Log::info("✏️ Categoría #{$id} => name='{$nameDeseado}', slug='{$slugFinal}'");
    //             } else {
    //                 Log::warning("❌ No se pudo renombrar categoría #{$id}: " . $up->body());
    //             }
    //         }

    //         // --------------- LOOP PRINCIPAL ---------------
    //         $creados = [];
    //         $omitidos = [];
    //         $actualizados = [];
    //         $categoriasFallidas = [];
    //         $fallidosPorCategoria = [];
    //         $productosParaCrear = [];

    //         foreach ($productosSirett as $producto) {
    //             $sku = trim($producto['codigo'] ?? '');
    //             if ($sku === '')
    //                 continue;

    //             $wooProducto = $wooPorSku[$sku] ?? null;

    //             $nombreCategoriaOriginal = trim($producto['familia'] ?? '');
    //             if ($nombreCategoriaOriginal === '') {
    //                 Log::warning("❌ Producto con SKU $sku no tiene familia. Se omite.");
    //                 SyncError::create([
    //                     'sync_history_id' => $sync->id,
    //                     'sku' => $sku,
    //                     'tipo_error' => 'familia_vacia',
    //                     'detalle' => json_encode($producto, JSON_UNESCAPED_UNICODE),
    //                 ]);
    //                 $categoriasFallidas[] = '(sin familia)';
    //                 $fallidosPorCategoria[] = $sku;
    //                 continue;
    //             }

    //             // Normalización de categoría para mapa y display
    //             $keyDeseado = $this->categoryKey($nombreCategoriaOriginal);
    //             $nameVisible = $this->categoryDisplay($nombreCategoriaOriginal);

    //             $categoriaId = $categoriasMap[$keyDeseado] ?? null;
    //             if (!$categoriaId) {
    //                 // Crear categoría si falta
    //                 $slugDeseado = $this->categorySlug($nombreCategoriaOriginal);
    //                 $slugFinal = $slugDeseado;
    //                 if (isset($slugExistentes[$slugDeseado])) {
    //                     $slugFinal = $slugDeseado . '-' . uniqid();
    //                 }

    //                 $resCategoria = Http::retry(3, 2000)
    //                     ->withBasicAuth($credWoo->user, $credWoo->password)
    //                     ->timeout(120)
    //                     ->post("{$credWoo->base_url}/products/categories", [
    //                         'name' => $nameVisible,
    //                         'slug' => $slugFinal,
    //                     ]);

    //                 if ($resCategoria->successful()) {
    //                     $categoriaId = $resCategoria->json('id');
    //                     $categoriasMap[$keyDeseado] = $categoriaId;
    //                     $slugExistentes[$slugFinal] = $categoriaId;
    //                 } else {
    //                     Log::warning("❌ No se pudo crear categoría: $nombreCategoriaOriginal");
    //                     $categoriasFallidas[] = $nombreCategoriaOriginal;
    //                     $fallidosPorCategoria[] = $sku;
    //                     continue;
    //                 }
    //             }

    //             // ====== EXISTE EN WOO: comparar usando NORMALIZACIÓN ======
    //             if ($wooProducto) {
    //                 $nombre = trim($producto['descripcion'] ?? '');
    //                 $precioNew = number_format((float) ($producto['precio'] ?? 0), 2, '.', '');
    //                 $precioOldN = number_format((float) ($wooProducto['regular_price'] ?? 0), 2, '.', '');
    //                 $stockNew = (int) ($producto['stock'] ?? 0);
    //                 $stockOld = (int) ($wooProducto['stock_quantity'] ?? 0);

    //                 $nameOld = $wooProducto['name'] ?? '';
    //                 $nameNew = $nombre;

    //                 // Categoría actual del producto en Woo
    //                 $catOldId = isset($wooProducto['categories'][0]['id']) ? (int) $wooProducto['categories'][0]['id'] : null;
    //                 $catOldName = $wooProducto['categories'][0]['name'] ?? '';
    //                 $catNewName = $nombreCategoriaOriginal;

    //                 // ¿La categoría del producto cambia?
    //                 $catChanged = false;
    //                 if (!is_null($catOldId)) {
    //                     $catChanged = ($catOldId !== (int) $categoriaId); // por ID
    //                 } else {
    //                     // respaldo por nombre normalizado
    //                     $catChanged = ($this->categoryKey($catOldName) !== $this->categoryKey($catNewName));
    //                 }

    //                 // Comparaciones de texto sin acentos y minúsculas
    //                 $nameChanged = ($this->normalizeText($nameOld) !== $this->normalizeText($nameNew));
    //                 $priceChanged = ($precioOldN !== $precioNew);
    //                 $stockChanged = ($stockOld !== $stockNew);

    //                 // Construir payload SOLO con cambios reales
    //                 $payload = [];
    //                 if ($nameChanged) {
    //                     $payload['name'] = $nameNew;
    //                 }
    //                 if ($priceChanged) {
    //                     $payload['regular_price'] = $precioNew;
    //                 }
    //                 if ($stockChanged) {
    //                     $payload['stock_quantity'] = $stockNew;
    //                     $payload['manage_stock'] = true;
    //                 }
    //                 if ($catChanged) {
    //                     $payload['categories'] = [['id' => $categoriaId]];
    //                 }

    //                 if (!empty($payload)) {
    //                     $resUpdate = Http::retry(3, 2000)
    //                         ->withBasicAuth($credWoo->user, $credWoo->password)
    //                         ->timeout(120)
    //                         ->put("{$credWoo->base_url}/products/{$wooProducto['id']}", $payload);

    //                     if ($resUpdate->successful()) {
    //                         $actualizados[] = $sku;

    //                         // Reporte de diffs (opcional, si tienes estas helpers)
    //                         $rName = $this->fieldDiffReport('name', $nameOld, $nameNew);
    //                         $rCat = $this->fieldDiffReport('categoria', $catOldName, $catNewName);
    //                         $rPrecio = [
    //                             'campo' => 'precio',
    //                             'igual' => ($precioOldN === $precioNew),
    //                             'old_raw' => $precioOldN,
    //                             'new_raw' => $precioNew,
    //                         ];
    //                         $rStock = [
    //                             'campo' => 'stock',
    //                             'igual' => ($stockOld === $stockNew),
    //                             'old_raw' => $stockOld,
    //                             'new_raw' => $stockNew,
    //                         ];

    //                         SyncHistoryDetail::create([
    //                             'sync_history_id' => $sync->id,
    //                             'sku' => $sku,
    //                             'tipo' => 'actualizado',
    //                             'datos_anteriores' => [
    //                                 'name' => $nameOld,
    //                                 'precio' => $precioOldN,
    //                                 'stock' => $stockOld,
    //                                 'categoria' => $catOldName,
    //                             ],
    //                             'datos_nuevos' => [
    //                                 'name' => $nameNew,
    //                                 'precio' => $precioNew,
    //                                 'stock' => $stockNew,
    //                                 'categoria' => $catNewName,
    //                             ],
    //                             'deltas' => [
    //                                 'name' => $rName,
    //                                 'categoria' => $rCat,
    //                                 'precio' => $rPrecio,
    //                                 'stock' => $rStock,
    //                             ],
    //                         ]);

    //                         Log::info("🔄 SKU {$sku} actualizado", [
    //                             'name_changed' => $nameChanged,
    //                             'price_changed' => $priceChanged,
    //                             'stock_changed' => $stockChanged,
    //                             'cat_changed' => $catChanged,
    //                         ]);
    //                     } else {
    //                         $this->notificarErrorTelegram($clienteNombre, "Error actualizando SKU $sku: " . $resUpdate->body());
    //                         Log::warning("❌ Error actualizando SKU $sku: " . $resUpdate->body());
    //                     }
    //                 } else {
    //                     // Nada cambió en términos reales (ignorando tildes/mayúsculas)
    //                     $omitidos[] = $sku;
    //                 }

    //                 continue; // ya procesado este SKU
    //             }

    //             // ====== NO EXISTE EN WOO: preparar creación ======
    //             $productosParaCrear[] = [
    //                 'name' => $producto['descripcion'] ?? '',
    //                 'sku' => $sku,
    //                 'regular_price' => number_format((float) ($producto['precio'] ?? 0), 2, '.', ''),
    //                 'stock_quantity' => (int) ($producto['stock'] ?? 0),
    //                 'manage_stock' => true,
    //                 'description' => $producto['caracteristicas'] ?? '',
    //                 'categories' => [['id' => $categoriaId]],
    //                 'images' => $this->mapearImagenes($producto),
    //             ];

    //             $creados[] = $sku;
    //             SyncHistoryDetail::create([
    //                 'sync_history_id' => $sync->id,
    //                 'sku' => $sku,
    //                 'tipo' => 'creado',
    //                 'datos_nuevos' => [
    //                     'name' => $producto['descripcion'] ?? '',
    //                     'sku' => $sku,
    //                     'precio' => $producto['precio'] ?? 0,
    //                     'stock' => $producto['stock'] ?? 0,
    //                     'categoria' => $nombreCategoriaOriginal,
    //                 ],
    //             ]);
    //         }

    //         // Lotes de creación
    //         $resultados = [];
    //         foreach (array_chunk($productosParaCrear, 50) as $lote) {
    //             Log::info("⏳ Enviando lote con " . count($lote) . " productos a WooCommerce");

    //             $res = Http::retry(3, 2000)
    //                 ->withBasicAuth($credWoo->user, $credWoo->password)
    //                 ->timeout(120)
    //                 ->post("{$credWoo->base_url}/products/batch", ['create' => $lote]);

    //             if ($res->successful()) {
    //                 $resultados[] = ['status' => '✅ Lote creado', 'response' => $res->json()];
    //             } else {
    //                 $resultados[] = ['status' => '❌ Error al crear lote', 'response' => $res->body()];
    //                 $this->notificarErrorTelegram($clienteNombre, 'Error creando lote en WooCommerce: ' . $res->body());
    //                 Log::warning("❌ Error al crear lote: " . $res->body());
    //             }
    //         }

    //         // --- Métricas extra para el cruce de SKUs
    //         $skusSirett = collect($productosSirett)
    //             ->pluck('codigo')
    //             ->map(fn($v) => is_string($v) ? trim($v) : (string) $v)
    //             ->filter(fn($v) => $v !== '')
    //             ->unique()->values();

    //         // Woo: separa con y sin SKU
    //         $wooConSku = $productosWoo->filter(function ($p) {
    //             $s = $p['sku'] ?? '';
    //             return is_string($s) && trim($s) !== '';
    //         });
    //         $wooSinSku = $productosWoo->filter(function ($p) {
    //             $s = $p['sku'] ?? '';
    //             return !is_string($s) || trim($s) === '';
    //         });

    //         $skusWoo = $wooConSku->pluck('sku')
    //             ->map(fn($v) => trim($v))->unique()->values();

    //         // SKUs que están en Woo pero NO en SiReTT
    //         $soloWoo = $skusWoo->diff($skusSirett)->values();

    //         // Detalle de “solo en Woo”
    //         $soloWooDetalle = $wooConSku
    //             ->filter(fn($p) => $soloWoo->contains(trim($p['sku'])))
    //             ->map(fn($p) => [
    //                 'id' => $p['id'] ?? null,
    //                 'sku' => trim($p['sku']),
    //                 'name' => $p['name'] ?? null,
    //                 'status' => $p['status'] ?? null,
    //             ])->values();

    //         // Guarda archivo de apoyo
    //         $reporte = [
    //             'solo_woocommerce' => $soloWoo,
    //             'woo_sin_sku' => $wooSinSku->map(fn($p) => [
    //                 'id' => $p['id'] ?? null,
    //                 'name' => $p['name'] ?? null,
    //                 'type' => $p['type'] ?? null,
    //                 'status' => $p['status'] ?? null,
    //             ])->values(),
    //         ];
    //         file_put_contents(
    //             storage_path("logs/solo_woo_{$clienteNombre}.json"),
    //             json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    //         );

    //         // SKUs con stock = 0 en SiReTT
    //         $stockCeroSirett = collect($productosSirett)
    //             ->filter(fn($p) => (int) ($p['stock'] ?? 0) === 0)
    //             ->pluck('codigo')
    //             ->filter(fn($v) => is_string($v) && trim($v) !== '')
    //             ->map(fn($v) => trim($v))
    //             ->unique()->values();

    //         // Guardar CSV de stock=0 para esta corrida
    //         $csvLines = "sku\n" . implode("\n", $stockCeroSirett->all());
    //         $csvPath = "exports/stock_cero_{$sync->id}.csv";
    //         Storage::disk('local')->put($csvPath, $csvLines);

    //         // --- Gestionar productos Woo sin SKU ---
    //         $gestionWooSinSku = [
    //             'accion' => $WOO_NO_SKU_ACTION,
    //             'procesados' => 0,
    //             'moved_ids' => [],
    //             'deleted_ids' => [],
    //             'errores' => [],
    //         ];

    //         if ($WOO_NO_SKU_ACTION !== 'none' && $wooSinSku->count() > 0) {
    //             if ($WOO_NO_SKU_ACTION === 'move') {
    //                 $catPendId = $this->ensureCategoryExists($credWoo, $WOO_NO_SKU_CATEGORY, $categoriasMap, $slugExistentes);
    //                 if ($catPendId) {
    //                     $updates = $wooSinSku->map(function ($p) use ($catPendId) {
    //                         return ['id' => $p['id'], 'status' => 'draft', 'categories' => [['id' => $catPendId]]];
    //                     })->values()->all();

    //                     foreach (array_chunk($updates, 50) as $lote) {
    //                         $up = Http::retry(3, 2000)
    //                             ->withBasicAuth($credWoo->user, $credWoo->password)
    //                             ->timeout(120)
    //                             ->post("{$credWoo->base_url}/products/batch", ['update' => $lote]);

    //                         if ($up->successful()) {
    //                             $gestionWooSinSku['procesados'] += count($lote);
    //                             $gestionWooSinSku['moved_ids'] = array_merge(
    //                                 $gestionWooSinSku['moved_ids'],
    //                                 array_column($lote, 'id')
    //                             );
    //                         } else {
    //                             $gestionWooSinSku['errores'][] = $up->body();
    //                             Log::warning("❌ Error al mover Woo sin SKU: " . $up->body());
    //                         }
    //                     }
    //                 } else {
    //                     $gestionWooSinSku['errores'][] = 'No se pudo crear/obtener categoría especial.';
    //                 }
    //             }

    //             if ($WOO_NO_SKU_ACTION === 'delete') {
    //                 foreach ($wooSinSku as $p) {
    //                     $pid = $p['id'];
    //                     $del = Http::retry(2, 1500)
    //                         ->withBasicAuth($credWoo->user, $credWoo->password)
    //                         ->timeout(60)
    //                         ->delete("{$credWoo->base_url}/products/{$pid}", ['force' => true]);

    //                     if ($del->successful()) {
    //                         $gestionWooSinSku['procesados']++;
    //                         $gestionWooSinSku['deleted_ids'][] = $pid;
    //                     } else {
    //                         $gestionWooSinSku['errores'][] = "ID {$pid}: " . $del->body();
    //                         Log::warning("❌ Error al eliminar producto #{$pid} sin SKU: " . $del->body());
    //                     }
    //                 }
    //             }
    //         }

    //         // Tiempos
    //         $fin = now('America/Managua');
    //         $duracion = $inicio->diffInSeconds($fin);
    //         Log::info("⏱️ Tiempo total de sincronización para {$clienteNombre}: {$duracion} segundos");

    //         // Persistir resumen
    //         $sync->update([
    //             'finished_at' => $fin,
    //             'total_creados' => count($creados),
    //             'total_actualizados' => count($actualizados),
    //             'total_omitidos' => count($omitidos),
    //             'total_fallidos_categoria' => count($fallidosPorCategoria),
    //         ]);

    //         // Telegram
    //         $resumenTelegram = "📦 <b>Sincronización completada</b> para <b>{$clienteNombre}</b>\n"
    //             . "🆕 Nuevos: <b>" . count($creados) . "</b>\n"
    //             . "🔄 Actualizados: <b>" . count($actualizados) . "</b>\n"
    //             . "⏭️ Omitidos: <b>" . count($omitidos) . "</b>\n"
    //             . "🛑 Ignorados por categoría: <b>" . count($fallidosPorCategoria) . "</b>\n"
    //             . "📥 Total productos SiReTT: <b>" . count($productosSirett) . "</b>\n"
    //             . "🛒 Total productos Woo: <b>" . $productosWoo->count() . "</b>";
    //         $this->notificarTelegram($clienteNombre, $resumenTelegram);

    //         // Respuesta JSON
    //         return response()->json([
    //             'mensaje' => 'Sincronización completa.',
    //             'total_sirett' => count($productosSirett),
    //             'total_woocommerce' => $productosWoo->count(),
    //             'total_creados' => count($creados),
    //             'total_actualizados' => count($actualizados),
    //             'total_omitidos' => count($omitidos),
    //             'total_fallidos_categoria' => count($fallidosPorCategoria),
    //             'creados' => $creados,
    //             'actualizados' => $actualizados,
    //             'omitidos' => $omitidos,
    //             'fallidos_categoria' => $fallidosPorCategoria,
    //         ]);

    //     } catch (\Throwable $e) {
    //         $this->notificarErrorTelegram($clienteNombre, 'Excepción inesperada: ' . $e->getMessage());
    //         Log::error("❌ Excepción no controlada: " . $e->getMessage());
    //         return response()->json(['error' => 'Excepción no controlada', 'detalle' => $e->getMessage()], 500);
    //     }
    // }







    // private function normalizeNoAccents(?string $s): string
    // {
    //     $s = is_string($s) ? trim($s) : '';
    //     // minúsculas + espacios
    //     $s = \Illuminate\Support\Str::of($s)->lower()->squish()->toString();

    //     // quitar tildes/acentos
    //     $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    //     if ($noAcc !== false && $noAcc !== null) {
    //         $s = $noAcc;
    //     } else {
    //         // respaldo por si iconv falla en tu hosting
    //         $s = strtr($s, [
    //             'á' => 'a',
    //             'é' => 'e',
    //             'í' => 'i',
    //             'ó' => 'o',
    //             'ú' => 'u',
    //             'ü' => 'u',
    //             'ñ' => 'n'
    //         ]);
    //     }

    //     // normaliza espacios múltiples
    //     $s = preg_replace('/\s+/', ' ', $s);
    //     return trim($s);
    // }

    // private function categoryKey(?string $name): string
    // {
    //     return $this->normalizeNoAccents($name);
    // }

    // private function normalizeText(?string $text): string
    // {
    //     return $this->normalizeNoAccents($text);
    // }





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
