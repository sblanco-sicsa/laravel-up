<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiTestController;

Route::middleware('auth.api')->group(function () {
    Route::get('/{cliente}/woocommerce', [ApiTestController::class, 'woocommerce']);
    Route::get('/{cliente}/sirett', [ApiTestController::class, 'sirett']);
    Route::get('/{cliente}/sirett_promo', [ApiTestController::class, 'sirett_promo']);

    Route::get('/{cliente}/sirett/filtrado', [ApiTestController::class, 'sirettFiltrado']);

    Route::get('/{cliente}/telegram', [ApiTestController::class, 'telegram']);
    Route::get('/{cliente}/woocommerce/categories', [ApiTestController::class, 'woocommerceCategories']);
    Route::delete('/{cliente}/woocommerce/categories/delete-all', [ApiTestController::class, 'deleteAllCategories']);

    Route::get('/{cliente}/sirett/familias', [ApiTestController::class, 'getUniqueFamiliesFromSirett']);

    Route::post('/{cliente}/woocommerce/products/sync-from-sirett', [ApiTestController::class, 'sincronizarProductosConCategorias']);
    
    Route::delete('/{cliente}/woocommerce/delete-all', [ApiTestController::class, 'deleteAllWooProductsCategoriesAndImages']);
    Route::get('/{cliente}/woocommerce/verificar-permisos-imagenes', [ApiTestController::class, 'verificarPermisosWooImages']);

    Route::get('/telegram/{clienteNombre}', [ApiTestController::class, 'telegram']);



    Route::get('/{cliente}/woocommerce/categories', [ApiTestController::class, 'listWooCategories'])
        ->name('api.categories.index');

    Route::delete('/{cliente}/woocommerce/categories/zero', [ApiTestController::class, 'deleteAllZeroCountCategories'])
        ->name('api.categories.delete-zero');

    Route::delete('/{cliente}/woocommerce/categories/{id}', [ApiTestController::class, 'deleteOneZeroCountCategory'])
        ->whereNumber('id')
        ->name('api.categories.delete-one');

    Route::post('/{cliente}/woocommerce/categories/sync-from-sirett', [ApiTestController::class, 'syncSirettCategoriesToWoo'])
        ->name('api.categories.sync-from-sirett');



});


