<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportLogController;
use App\Http\Controllers\CategoriaSincronizadaController;
use App\Http\Controllers\SyncHistoryController;
use App\Http\Controllers\SyncErrorController;
use App\Http\Controllers\WooCategoriesPageController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/logs', [ExportLogController::class, 'index'])->name('logs.index');
Route::get('/logs/export', [ExportLogController::class, 'export'])->name('logs.export');

Route::get('/admin/categorias-sincronizadas/{cliente}', [CategoriaSincronizadaController::class, 'index'])->name('categorias.sincronizadas'); 


Route::get('/historial-sincronizaciones', [SyncHistoryController::class, 'index'])->name('sync-history.index');

Route::get('/sync-errors', [SyncErrorController::class, 'index'])->name('sync-errors.index');
Route::get('/sync-errors/export/excel', [SyncErrorController::class, 'exportExcel'])->name('sync-errors.export');


Route::delete('/sync-errors/delete-all', [SyncErrorController::class, 'deleteAll'])->name('sync-errors.delete-all');
Route::get('/sync/{sync}/stock-cero', [SyncHistoryController::class, 'descargarStockCero'])->name('sync.stock_cero');




Route::middleware(['web','auth'])->group(function () {
    // Página
    Route::get('/admin/{cliente}/woocommerce/categories', [WooCategoriesPageController::class, 'index'])
        ->name('woo.categories.index');

    // Proxies (AJAX desde el Blade) -> llaman al API con token tomado desde BD
    Route::get('/admin/{cliente}/woocommerce/categories/data', [WooCategoriesPageController::class, 'data'])
        ->name('woo.categories.data');

    Route::delete('/admin/{cliente}/woocommerce/categories/zero', [WooCategoriesPageController::class, 'deleteZeros'])
        ->name('woo.categories.delete-zero.web');

    Route::delete('/admin/{cliente}/woocommerce/categories/{id}', [WooCategoriesPageController::class, 'deleteOne'])
        ->whereNumber('id')
        ->name('woo.categories.delete-one.web');

    Route::post('/admin/{cliente}/woocommerce/categories/sync', [WooCategoriesPageController::class, 'sync'])
        ->name('woo.categories.sync.web');
});