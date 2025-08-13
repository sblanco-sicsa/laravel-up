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




Route::get('/admin/categorias-sincronizadas/{cliente}', [CategoriaSincronizadaController::class, 'index'])
    ->name('catsync.index');

Route::post('/admin/categorias-sincronizadas/{cliente}/eliminar-seleccion', [CategoriaSincronizadaController::class, 'deleteSelected'])
    ->name('catsync.deleteSelected');

Route::post('/admin/categorias-sincronizadas/{cliente}/eliminar-todas-huerfanas', [CategoriaSincronizadaController::class, 'deleteAllOrphans'])
    ->name('catsync.deleteAllOrphans');

Route::delete('/admin/categorias-sincronizadas/{cliente}/{wooId}', [CategoriaSincronizadaController::class, 'deleteOne'])
    ->name('catsync.deleteOne');




Route::post('/admin/categorias-sincronizadas/{cliente}/sincronizar',
    [CategoriaSincronizadaController::class, 'syncNow']
)->name('catsync.syncNow');



Route::prefix('{cliente}/categorias')->group(function () {
    // Tabla existente (tu index actual):
    Route::get('/', [CategoriaSincronizadaController::class, 'index'])->name('categorias.index');
    // Nueva vista: árbol
    Route::get('/arbol', [CategoriaSincronizadaController::class, 'tree'])->name('categorias.tree');
    // Endpoints AJAX para el árbol
    Route::get('/api/tree', [CategoriaSincronizadaController::class, 'apiTree'])->name('categorias.api.tree');
    Route::post('/api/move', [CategoriaSincronizadaController::class, 'apiMove'])->name('categorias.api.move');
    Route::post('/api/reset', [CategoriaSincronizadaController::class, 'apiResetToWoo'])->name('categorias.api.reset');
});



Route::post(
    '{cliente}/woocommerce/categories/apply-manual-hierarchy',
    [CategoriaSincronizadaController::class, 'applyManualHierarchyToWoo']
)->name('woo.categories.applyHierarchy');
