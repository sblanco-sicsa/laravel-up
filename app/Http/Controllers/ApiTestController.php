<?php

namespace App\Http\Controllers;
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


class ApiTestController extends Controller
{




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


    public function syncSirettCategoriesToWoo(string $clienteNombre)
    {
        $credWoo = ApiConnector::getCredentials($clienteNombre, 'woocommerce');
        $credSirett = ApiConnector::getCredentials($clienteNombre, 'sirett');

        if (!$credWoo || !$credSirett) {
            return response()->json(['error' => 'Credenciales no encontradas para WooCommerce o SiReTT'], 404);
        }

        // 1. Obtener productos desde SiReTT
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

        // 2. Obtener familias únicas de SiReTT
        $familiasSiReTT = collect($items)
            ->pluck('familia')
            ->filter()
            ->unique()
            ->values()
            ->map(fn($familia) => trim($familia));

        // 3. Obtener categorías existentes en WooCommerce (paginadas)
        $categoriasExistentes = collect();
        $page = 1;

        do {
            $response = Http::withBasicAuth($credWoo->user, $credWoo->password)
                ->get($credWoo->base_url . '/products/categories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener categorías desde WooCommerce'], 500);
            }

            $batch = collect($response->json());
            $categoriasExistentes = $categoriasExistentes->merge($batch);
            $page++;
        } while ($batch->count() > 0);

        // 4. Comparar y filtrar solo las nuevas categorías
        $nombresExistentes = $categoriasExistentes->pluck('name')->map(fn($name) => trim($name))->values();

        $familiasParaCrear = $familiasSiReTT->filter(fn($familia) => !$nombresExistentes->contains($familia));

        if ($familiasParaCrear->isEmpty()) {
            return response()->json([
                'mensaje' => 'No hay categorías nuevas para crear. Todo está sincronizado.',
                'total_existentes' => $nombresExistentes->count(),
                'total_familias_sirett' => $familiasSiReTT->count(),
            ]);
        }

        // 5. Crear en lote (batch)
        $payload = [
            'create' => $familiasParaCrear->map(fn($f) => ['name' => $f])->values()
        ];

        $response = Http::withBasicAuth($credWoo->user, $credWoo->password)
            ->post($credWoo->base_url . '/products/categories/batch', $payload);

        // Guardar en la base de datos solo las categorías creadas
        if ($response->successful() && isset($response['create'])) {
            foreach ($response['create'] as $created) {
                CategoriaSincronizada::create([
                    'cliente' => $clienteNombre,
                    'nombre' => $created['name'],
                    'woocommerce_id' => $created['id'] ?? null,
                    'respuesta' => $created,
                ]);
            }
        }


        if ($response->failed()) {
            return response()->json(['error' => 'Error al crear categorías en lote'], 500);
        }

        return response()->json([
            'mensaje' => 'Categorías sincronizadas desde SiReTT a WooCommerce',
            'total_creadas' => $familiasParaCrear->count(),
            'detalles' => $response->json(),
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

    // 🔑 Clave de comparación (siempre minúsculas)
    private function categoryKey(?string $name): string
    {
        return mb_strtolower($this->normalizeSpaces($name ?? ''), 'UTF-8');
    }

    // 🏷️ Formato visible “Oración”: primera letra mayúscula, resto minúsculas
    private function categoryDisplay(?string $name): string
    {
        $s = mb_strtolower($this->normalizeSpaces($name ?? ''), 'UTF-8');
        if ($s === '')
            return $s;
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }

    // 🔗 Slug siempre minúsculas
    private function categorySlug(?string $name): string
    {
        // Opcional: podrías basarlo en la clave (100% minúsculas)
        return Str::slug($this->categoryKey($name));
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
